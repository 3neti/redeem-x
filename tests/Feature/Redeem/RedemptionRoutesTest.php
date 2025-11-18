<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->depositFloat(1000);
});

test('redemption start page route exists', function () {
    $response = $this->get('/redeem');
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('redeem/Start'));
});

test('wallet page route exists and requires voucher code', function () {
    $voucher = createRedeemableVoucher($this->user);
    
    $response = $this->get("/redeem/{$voucher->code}/wallet");
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Wallet')
            ->has('voucher_code')
        );
});

test('wallet page returns 404 for invalid voucher code', function () {
    $response = $this->get('/redeem/INVALID/wallet');
    
    $response->assertNotFound();
});

test('success page route exists and requires voucher code', function () {
    $voucher = createRedeemableVoucher($this->user);
    
    // Redeem the voucher
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
    RedeemVoucher::run($contact, $voucher->code);
    
    $response = $this->get("/redeem/{$voucher->code}/success");
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Success')
            ->has('voucher')
        );
});

test('success page without voucher code returns 404', function () {
    $response = $this->get('/redeem/success');
    
    $response->assertNotFound();
});

test('success page returns error for unredeemed voucher', function () {
    $voucher = createRedeemableVoucher($this->user);
    
    $response = $this->get("/redeem/{$voucher->code}/success");
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Error')
            ->has('message')
        );
});

test('finalize page route exists', function () {
    $voucher = createRedeemableVoucher($this->user);
    
    $response = $this->get("/redeem/{$voucher->code}/finalize");
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Finalize')
        );
});

// Helper function
function createRedeemableVoucher($user)
{
    auth()->login($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);
    
    return GenerateVouchers::run($instructions)->first();
}
