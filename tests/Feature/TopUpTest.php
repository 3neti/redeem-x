<?php

use App\Models\TopUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Mock NetBank Direct Checkout API
    Http::fake([
        '*/v1/collect/checkout' => Http::response([
            'redirect_url' => 'https://checkout.netbank.ph/pay-test-123',
            'reference_no' => 'TOPUP-TEST',
        ], 200),
    ]);
});

test('user can initiate top-up', function () {
    $result = $this->user->initiateTopUp(1000, 'netbank', 'GCASH');

    expect($result)->not->toBeNull()
        ->and($result->gateway)->toBe('netbank')
        ->and($result->amount)->toBe(1000.0)
        ->and($result->institution_code)->toBe('GCASH');

    // In fake mode (default for testing), expect local callback URL
    // In real mode, expect actual NetBank checkout URL
    if (config('payment-gateway.netbank.direct_checkout.use_fake')) {
        expect($result->redirect_url)->toContain('topup/callback')
            ->and($result->redirect_url)->toContain('mock=1');
    } else {
        expect($result->redirect_url)->toContain('checkout.netbank.ph');
    }
});

test('top-up is saved to database', function () {
    $result = $this->user->initiateTopUp(500);

    assertDatabaseHas('top_ups', [
        'user_id' => $this->user->id,
        'gateway' => 'netbank',
        'reference_no' => $result->reference_no,
        'amount' => 500,
        'payment_status' => 'PENDING',
    ]);
});

test('user can view their top-ups', function () {
    $this->user->initiateTopUp(100);
    $this->user->initiateTopUp(200);

    expect($this->user->getTopUps())->toHaveCount(2);
});

test('user can view pending top-ups only', function () {
    $this->user->initiateTopUp(100);

    // Create a paid one
    TopUp::factory()->create([
        'user_id' => $this->user->id,
        'payment_status' => 'PAID',
    ]);

    expect($this->user->getPendingTopUps())->toHaveCount(1)
        ->and($this->user->getPaidTopUps())->toHaveCount(1);
});

test('marking top-up as paid updates status', function () {
    $result = $this->user->initiateTopUp(500);

    $topUp = TopUp::where('reference_no', $result->reference_no)->first();
    $topUp->markAsPaid('PAYMENT-123');

    expect($topUp->fresh()->isPaid())->toBeTrue()
        ->and($topUp->fresh()->payment_id)->toBe('PAYMENT-123')
        ->and($topUp->fresh()->paid_at)->not->toBeNull();
});

test('crediting wallet from paid top-up adds balance', function () {
    $initialBalance = $this->user->fresh()->balanceFloat;

    $result = $this->user->initiateTopUp(1000);
    $topUp = TopUp::where('reference_no', $result->reference_no)->first();
    $topUp->markAsPaid('PAYMENT-456');

    // Credit wallet
    $this->user->creditWalletFromTopUp($topUp);

    $newBalance = $this->user->fresh()->balanceFloat;
    expect($newBalance)->toBeGreaterThan($initialBalance)
        ->and($topUp->isPaid())->toBeTrue();
});

test('cannot initiate top-up with invalid amount', function () {
    $this->user->initiateTopUp(100000); // Exceeds max
})->throws(TopUpException::class);

test('top-up implements TopUpInterface', function () {
    $result = $this->user->initiateTopUp(500);
    $topUp = TopUp::where('reference_no', $result->reference_no)->first();

    expect($topUp)->toBeInstanceOf(\LBHurtado\PaymentGateway\Contracts\TopUpInterface::class)
        ->and($topUp->getGateway())->toBe('netbank')
        ->and($topUp->getReferenceNo())->toBe($result->reference_no)
        ->and($topUp->getAmount())->toBe(500.0)
        ->and($topUp->getCurrency())->toBe('PHP')
        ->and($topUp->getStatus())->toBe('PENDING')
        ->and($topUp->getOwner()->id)->toBe($this->user->id);
});
