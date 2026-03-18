<?php

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createDivisibleVoucher(User $user, array $cashOverrides = []): Voucher
{
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash'] = array_merge($base['cash'], ['amount' => 5000], $cashOverrides);
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    return GenerateVouchers::run($instructions)->first()->fresh();
}

function redeemAndPrepare(Voucher $voucher, Contact $contact): Voucher
{
    Vouchers::redeem($voucher->code, $contact);
    $v = Voucher::find($voucher->id);
    $v->load('redeemers');

    // Simulate first slice withdrawal (as DisburseCash pipeline would)
    WithdrawCash::run($v->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $v->code,
    ], 100000); // ₱1000

    return $v->fresh();
}

beforeEach(function () {
    $this->withoutVite();

    // Mock payment gateway so WithdrawFromVoucher can disburse
    $mock = Mockery::mock(PaymentGatewayInterface::class);
    $mock->shouldReceive('disburse')->andReturn(
        DisburseResponseData::from([
            'transaction_id' => 'TXN-WITHDRAW-'.uniqid(),
            'uuid' => 'uuid-withdraw-'.uniqid(),
            'status' => 'pending',
        ])
    );
    $mock->shouldReceive('getRailFee')->andReturn(1000);
    app()->instance(PaymentGatewayInterface::class, $mock);

    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'bank_account' => 'BDO:1234567890',
    ]);
});

// ---------------------------------------------------------------------------
// Web: GET /withdraw?code=CODE
// ---------------------------------------------------------------------------

test('GET /withdraw renders page for withdrawable voucher', function () {
    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->get('/withdraw?code='.$voucher->code);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('withdraw/Withdraw')
        ->has('voucher')
        ->where('voucher.code', $voucher->code)
        ->where('voucher.slice_mode', 'fixed')
        ->where('voucher.remaining_slices', 4)
    );
});

test('GET /withdraw without code redirects to disburse', function () {
    $response = $this->get('/withdraw');

    $response->assertRedirect(route('disburse.start'));
});

test('GET /withdraw with non-divisible voucher redirects with error', function () {
    $this->user->deposit(1000000);
    actingAs($this->user);
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 100;
    $base['count'] = 1;
    $voucher = GenerateVouchers::run(VoucherInstructionsData::from($base))->first();

    $response = $this->get('/withdraw?code='.$voucher->code);

    $response->assertRedirect(route('disburse.start'));
    $response->assertSessionHasErrors('code');
});

// ---------------------------------------------------------------------------
// Web: POST /withdraw/{code}
// ---------------------------------------------------------------------------

test('POST /withdraw processes fixed-mode withdrawal with correct mobile', function () {
    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->post('/withdraw/'.$voucher->code, [
        'mobile' => '09171234567',
    ]);

    $response->assertRedirect(route('withdraw.success', ['voucher' => $voucher->code]));
    $response->assertSessionHas('withdrawal_result');

    // Verify wallet was debited
    $voucher->refresh();
    expect($voucher->getConsumedSlices())->toBe(2); // 1 from redemption + 1 from withdraw
    expect($voucher->getRemainingSlices())->toBe(3);
});

test('POST /withdraw rejects wrong mobile number', function () {
    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->post('/withdraw/'.$voucher->code, [
        'mobile' => '09999999999',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('mobile');
});

// ---------------------------------------------------------------------------
// API: POST /api/v1/vouchers/{code}/withdraw
// ---------------------------------------------------------------------------

test('API withdraw succeeds with correct mobile', function () {
    Sanctum::actingAs($this->user);

    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->postJson('/api/v1/vouchers/'.$voucher->code.'/withdraw', [
        'mobile' => '+639171234567',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.success', true);
    $response->assertJsonPath('data.amount', 1000);
    $response->assertJsonPath('data.slice_number', 2);
});

test('API withdraw rejects wrong mobile', function () {
    Sanctum::actingAs($this->user);

    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->postJson('/api/v1/vouchers/'.$voucher->code.'/withdraw', [
        'mobile' => '+639999999999',
    ]);

    $response->assertStatus(403);
});

test('API withdraw requires mobile field', function () {
    Sanctum::actingAs($this->user);

    $voucher = createDivisibleVoucher($this->user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $voucher = redeemAndPrepare($voucher, $this->contact);

    $response = $this->postJson('/api/v1/vouchers/'.$voucher->code.'/withdraw', []);

    $response->assertStatus(422);
});
