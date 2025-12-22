<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DisbursementFailedNotification;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Console\Command;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Wallet\Events\DisbursementFailed;

class TestDisbursementFailureCommand extends Command
{
    protected $signature = 'test:disbursement-failure 
                            {--type=timeout : Failure type: timeout, gateway_error, insufficient_funds}
                            {--email= : Send alert to specific email (overrides config)}';

    protected $description = 'Test disbursement failure alerting system by simulating failures';

    public function handle(): int
    {
        $type = $this->option('type');
        $email = $this->option('email');

        if ($email) {
            config(['disbursement.alerts.emails' => [$email]]);
        }

        $this->info('🧪 Testing Disbursement Failure Alerting System');
        $this->newLine();

        // 1. Create a test voucher
        $user = User::first() ?? User::factory()->create();
        $instructions = VoucherInstructionsData::generateFromScratch();
        $instructions->cash->amount = 100; // ₱100
        
        $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
            ->withOwner($user)
            ->create();

        $this->info("✓ Created test voucher: {$voucher->code}");

        // 2. Create contact for redemption context
        $contact = Contact::factory()->create([
            'mobile' => '09171234567',
            'bank_account' => 'GXCHPHM2XXX:09171234567',
        ]);

        $this->info("✓ Created contact: {$contact->mobile}");

        // 3. Create disbursement attempt record (simulating gateway call)
        $attempt = DisbursementAttempt::create([
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'user_id' => $user->id,
            'amount' => 100.00,
            'currency' => 'PHP',
            'mobile' => $contact->mobile,
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'settlement_rail' => 'INSTAPAY',
            'gateway' => 'netbank',
            'reference_id' => "TEST-{$voucher->code}-{$contact->mobile}",
            'status' => 'pending',
            'attempted_at' => now(),
        ]);

        $this->info("✓ Created disbursement attempt: {$attempt->reference_id}");
        $this->newLine();

        // 4. Simulate failure based on type
        $exception = match($type) {
            'timeout' => new \RuntimeException('Connection timeout: Gateway did not respond within 15 seconds'),
            'gateway_error' => new \RuntimeException('Gateway error: Transaction processing failed (Error code: 5001)'),
            'insufficient_funds' => new \RuntimeException('Insufficient funds in system wallet'),
            default => new \RuntimeException('Unknown error occurred during disbursement'),
        };

        // Update attempt with failure
        $errorType = match($type) {
            'timeout' => 'network_timeout',
            'gateway_error' => 'gateway_error',
            'insufficient_funds' => 'insufficient_funds',
            default => 'unknown_error',
        };

        $attempt->update([
            'status' => 'failed',
            'error_type' => $errorType,
            'error_message' => $exception->getMessage(),
            'error_details' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'simulated' => true,
            ],
            'completed_at' => now(),
        ]);

        $this->warn("✗ Simulated {$type} failure");
        $this->line("  Error: {$exception->getMessage()}");
        $this->newLine();

        // 5. Fire the DisbursementFailed event (triggers notification)
        event(new DisbursementFailed($voucher, $exception));

        $this->info('✓ Fired DisbursementFailed event');
        $this->newLine();

        // 6. Show results
        $this->info('📊 Results:');
        $this->line("  Voucher Code: {$voucher->code}");
        $this->line("  Amount: ₱100.00");
        $this->line("  Redeemer: {$contact->mobile}");
        $this->line("  Error Type: {$errorType}");
        $this->line("  Attempt ID: {$attempt->id}");
        $this->newLine();

        // 7. Check if alerts are enabled
        if (config('disbursement.alerts.enabled')) {
            $emails = config('disbursement.alerts.emails');
            if (!empty($emails)) {
                $this->info('📧 Email notification queued to:');
                foreach ($emails as $recipientEmail) {
                    $this->line("  • {$recipientEmail}");
                }
            } else {
                $this->warn('⚠️  No alert emails configured in DISBURSEMENT_ALERT_EMAILS');
            }
        } else {
            $this->warn('⚠️  Alerts are disabled (DISBURSEMENT_ALERT_ENABLED=false)');
        }

        $this->newLine();
        $this->info('✅ Test complete!');
        $this->newLine();
        $this->comment('💡 Tips:');
        $this->comment('  • Run queue worker to process email: php artisan queue:work');
        $this->comment('  • Check logs: tail -f storage/logs/laravel.log');
        $this->comment("  • View attempt: php artisan tinker → DisbursementAttempt::find({$attempt->id})");
        $this->comment("  • Query failures: php artisan tinker → DisbursementAttempt::failed()->get()");

        return self::SUCCESS;
    }
}
