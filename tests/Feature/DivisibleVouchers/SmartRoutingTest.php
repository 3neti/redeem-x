<?php

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    // Disable Inertia middleware to avoid 409 version mismatch.
    // The controller only checks the raw X-Inertia header, not the middleware.
    $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
});

// ---------------------------------------------------------------------------
// Smart routing: /disburse?code=CODE
// ---------------------------------------------------------------------------

test('disburse redirects to /withdraw for redeemed divisible voucher with remaining slices', function () {
    $user = User::factory()->create();
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    $voucher = GenerateVouchers::run($instructions)->first();

    // Redeem first slice
    $contact = Contact::factory()->create();
    Vouchers::redeem($voucher->code, $contact);

    // Access /disburse?code=CODE via Inertia → should redirect to /withdraw
    $response = $this->get('/disburse?code='.$voucher->code, ['X-Inertia' => 'true']);

    $response->assertRedirect('/withdraw?code='.$voucher->code);
});

test('disburse shows error for redeemed non-divisible voucher', function () {
    $user = User::factory()->create();
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    $voucher = GenerateVouchers::run($instructions)->first();

    // Redeem fully
    $contact = Contact::factory()->create();
    Vouchers::redeem($voucher->code, $contact);

    // Access /disburse?code=CODE via Inertia → should show "already redeemed" error (unchanged behavior)
    $response = $this->get('/disburse?code='.$voucher->code, ['X-Inertia' => 'true']);

    $response->assertRedirect(route('disburse.start'));
    $response->assertSessionHasErrors('code');
});

test('disburse proceeds normally for unredeemed voucher', function () {
    $user = User::factory()->create();
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    $voucher = GenerateVouchers::run($instructions)->first();

    // Access /disburse?code=CODE via Inertia with unredeemed voucher → should redirect to form flow
    $response = $this->get('/disburse?code='.$voucher->code, ['X-Inertia' => 'true']);

    $response->assertRedirectContains('/form-flow/');
});
