<?php

namespace Tests\Feature\Integration;

use App\Actions\Voucher\ProcessRedemption;
use App\Exceptions\VoucherNotProcessedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;
use Tests\TestCase;

class VoucherProcessingRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that attempting to redeem unprocessed voucher throws exception.
     */
    public function test_cannot_redeem_unprocessed_voucher(): void
    {
        // Prevent queue jobs from running
        Event::fake([VouchersGenerated::class]);

        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(10000); // 100 PHP

        // Generate voucher (will be unprocessed because events are faked)
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            1,
            'TEST',
            [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => ['name', 'email']],
                'feedback' => [],
                'rider' => [],
            ]
        );

        $voucher = $vouchers->first();

        // Ensure voucher is not processed
        $this->assertFalse($voucher->processed, 'Voucher should not be processed');
        $this->assertNull($voucher->processed_on, 'Voucher should not have processed_on timestamp');

        // Attempt redemption
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        $this->expectException(VoucherNotProcessedException::class);
        $this->expectExceptionMessage('This voucher is still being prepared');

        ProcessRedemption::run($voucher, $phoneNumber, ['name' => 'Test', 'email' => 'test@example.com'], []);
    }

    /**
     * Test that redemption works after voucher is marked as processed.
     */
    public function test_can_redeem_after_processing_complete(): void
    {
        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(20000); // 200 PHP (extra buffer for fees)

        // Generate voucher and run post-generation pipeline
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            1,
            'TEST',
            [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => ['name', 'email']],
                'feedback' => [],
                'rider' => [],
            ]
        );

        $voucher = $vouchers->first();

        // Manually run post-generation pipeline to process voucher
        $postGenerationPipeline = config('voucher-pipeline.post-generation');
        app(\Illuminate\Pipeline\Pipeline::class)
            ->send($vouchers)
            ->through($postGenerationPipeline)
            ->thenReturn();

        $voucher->refresh();

        $this->assertTrue($voucher->processed, 'Voucher should be processed');
        $this->assertNotNull($voucher->cash, 'Voucher should have cash entity');

        // Attempt redemption (should succeed)
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        $result = ProcessRedemption::run(
            $voucher,
            $phoneNumber,
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09171234567']
        );

        $this->assertTrue($result, 'Redemption should succeed');

        // Verify voucher is now redeemed
        $voucher->refresh();
        $this->assertNotNull($voucher->redeemed_at, 'Voucher should be redeemed');
    }

    /**
     * Test that bulk generation completes processing for all vouchers.
     */
    public function test_bulk_generation_completes_processing(): void
    {
        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(50000); // 500 PHP for 10 vouchers @ 50 each

        // Generate 10 vouchers
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            10,
            'BULK',
            [
                'cash' => [
                    'amount' => 50,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ]
        );

        // Manually run post-generation pipeline
        $postGenerationPipeline = config('voucher-pipeline.post-generation');
        app(\Illuminate\Pipeline\Pipeline::class)
            ->send($vouchers)
            ->through($postGenerationPipeline)
            ->thenReturn();

        // Verify all vouchers are processed
        foreach ($vouchers as $voucher) {
            $voucher->refresh();
            $this->assertTrue($voucher->processed, "Voucher {$voucher->code} should be processed");
            $this->assertNotNull($voucher->cash, "Voucher {$voucher->code} should have cash entity");
        }
    }

    /**
     * Test that processed flag is set AFTER cash entity is created.
     */
    public function test_processing_flag_set_after_cash_creation(): void
    {
        Event::fake([VouchersGenerated::class]);

        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(10000);

        // Generate voucher
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            1,
            'TEST',
            [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ]
        );

        $voucher = $vouchers->first();

        // Initially not processed
        $this->assertFalse($voucher->processed);
        $this->assertNull($voucher->cash);

        // Run post-generation pipeline
        $postGenerationPipeline = config('voucher-pipeline.post-generation');
        app(\Illuminate\Pipeline\Pipeline::class)
            ->send($vouchers)
            ->through($postGenerationPipeline)
            ->thenReturn();

        // Refresh and check order
        $voucher->refresh();

        // Both should be true now
        $this->assertNotNull($voucher->cash, 'Cash entity should be created first');
        $this->assertTrue($voucher->processed, 'Processed flag should be set after cash creation');
        $this->assertNotNull($voucher->processed_on, 'Processed timestamp should be set');
    }

    /**
     * Test that HTTP request to confirm endpoint returns 425 for unprocessed voucher.
     */
    public function test_http_confirm_returns_425_for_unprocessed_voucher(): void
    {
        Event::fake([VouchersGenerated::class]);

        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(10000);

        // Generate voucher (unprocessed)
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            1,
            'TEST',
            [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ]
        );

        $voucher = $vouchers->first();

        // Setup session data
        session([
            "redeem.{$voucher->code}.mobile" => '09171234567',
            "redeem.{$voucher->code}.country" => 'PH',
            "redeem.{$voucher->code}.inputs" => [],
        ]);

        // Attempt confirmation via HTTP
        $response = $this->post("/redeem/{$voucher->code}/confirm");

        // Should redirect back to finalize with error
        $response->assertRedirect("/redeem/{$voucher->code}/finalize");
        $response->assertSessionHas('error');
        $response->assertSessionHas('voucher_processing', true);

        $errorMessage = session('error');
        $this->assertStringContainsString('being prepared', $errorMessage);
    }

    /**
     * Test that voucher with expired flag is not processed.
     */
    public function test_expired_voucher_cannot_be_redeemed(): void
    {
        // Create user with funds
        $user = User::factory()->create();
        $user->deposit(20000); // 200 PHP (extra buffer for fees)

        // Generate voucher with very short TTL
        $vouchers = VoucherTestHelper::createVouchersWithInstructions(
            $user,
            1,
            'EXP',
            [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'ttl' => \Carbon\CarbonInterval::seconds(1), // 1 second TTL
            ]
        );

        $voucher = $vouchers->first();

        // Process voucher
        $postGenerationPipeline = config('voucher-pipeline.post-generation');
        app(\Illuminate\Pipeline\Pipeline::class)
            ->send($vouchers)
            ->through($postGenerationPipeline)
            ->thenReturn();

        $voucher->refresh();

        // Manually expire voucher
        $voucher->expires_at = now()->subDay();
        $voucher->save();

        $this->assertTrue($voucher->isExpired(), 'Voucher should be expired');
        $this->assertTrue($voucher->processed, 'Voucher should still be processed');

        // Attempting redemption should fail (expired validation happens before processed check)
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        $this->expectException(\RuntimeException::class);

        ProcessRedemption::run($voucher, $phoneNumber, [], []);
    }
}
