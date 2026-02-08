<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Helpers\VoucherTestHelper;

/**
 * Browser tests for KYC redemption UI flow.
 *
 * These tests ensure the frontend properly handles KYC input field
 * and doesn't treat it as a regular text input.
 */
class KYCRedemptionUiTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that KYC field does NOT appear as text input on Inputs page.
     *
     * This test would have caught the bug where KYC was shown as a text field
     * instead of being handled on the Finalize page.
     */
    public function test_kyc_not_shown_as_text_input_on_inputs_page()
    {
        // Generate voucher with KYC and email inputs
        $user = User::factory()->create();
        $user->deposit(100000);

        $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
            ],
            'inputs' => ['fields' => ['kyc', 'email']], // KYC + email
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => ['message' => null, 'url' => null],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '****',
            'ttl' => null,
        ]);

        $voucher = $vouchers->first();

        $this->browse(function (Browser $browser) use ($voucher) {
            $browser->visit('/redeem')
                ->type('code', $voucher->code)
                ->press('Continue')
                // Wallet page
                ->type('mobile', '09171234567')
                ->press('Continue')
                // Inputs page - should only show email field, NOT KYC
                ->assertSee('Email Address')
                ->assertDontSee('Identity Verification (KYC)') // Should NOT see KYC here
                ->assertDontSee('Kyc') // Should NOT see KYC label
                ->type('email', 'test@example.com')
                ->press('Continue')
                // Finalize page - KYC should appear here
                ->assertSee('Identity Verification')
                ->assertSee('Start Identity Verification'); // Should see KYC button
        });
    }

    /**
     * Test that KYC verification card appears on Finalize page.
     */
    public function test_kyc_card_appears_on_finalize_page()
    {
        $user = User::factory()->create();
        $user->deposit(100000);

        $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
            'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null]],
            'inputs' => ['fields' => ['kyc']],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => ['message' => null, 'url' => null],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '****',
            'ttl' => null,
        ]);

        $voucher = $vouchers->first();

        $this->browse(function (Browser $browser) use ($voucher) {
            $browser->visit('/redeem')
                ->type('code', $voucher->code)
                ->press('Continue')
                ->type('mobile', '09171234567')
                ->press('Continue')
                // Should skip Inputs page (no text inputs required)
                // Should go directly to Finalize
                ->assertSee('Identity Verification')
                ->assertSee('KYC verification is required')
                ->assertSee('Start Identity Verification')
                // Confirm button should be disabled
                ->assertButtonDisabled('Confirm Redemption'); // or check for disabled attribute
        });
    }

    /**
     * Test that voucher without KYC does NOT show KYC card.
     */
    public function test_no_kyc_card_when_kyc_not_required()
    {
        $user = User::factory()->create();
        $user->deposit(100000);

        $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
            'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null]],
            'inputs' => ['fields' => ['email']], // No KYC
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => ['message' => null, 'url' => null],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '****',
            'ttl' => null,
        ]);

        $voucher = $vouchers->first();

        $this->browse(function (Browser $browser) use ($voucher) {
            $browser->visit('/redeem')
                ->type('code', $voucher->code)
                ->press('Continue')
                ->type('mobile', '09171234567')
                ->press('Continue')
                ->type('email', 'test@example.com')
                ->press('Continue')
                // Finalize page should NOT show KYC card
                ->assertDontSee('Identity Verification')
                ->assertDontSee('Start Identity Verification');
        });
    }
}
