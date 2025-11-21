<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SendFeedbacksNotification;
use Illuminate\Console\Command;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use Tests\Helpers\VoucherTestHelper;

class ShowWebhookPayloadCommand extends Command
{
    protected $signature = 'webhook:show-payload';
    protected $description = 'Display example webhook payload with all enhanced features';

    public function handle(): int
    {
        $this->info('Creating sample voucher with all enhanced features...');
        $this->newLine();

        // Create test user and contact
        $user = User::first() ?? User::factory()->create(['email' => 'demo@example.com']);
        $user->depositFloat(10000);
        $contact = Contact::firstOrCreate(['mobile' => '09178251991'], [
            'name' => 'John Doe',
        ]);

        // Create voucher with inputs
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'DEMO', [
            'cash' => [
                'amount' => 500,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => ['name', 'email', 'location', 'signature'],
            ],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => 'https://webhook.site/unique-id',
            ],
            'rider' => ['message' => null, 'url' => null],
            'count' => 1,
            'prefix' => 'DEMO',
            'mask' => '****',
            'ttl' => null,
        ]);

        $voucher = $vouchers->first();

        // 1. Set external metadata (QuestPay scenario)
        $voucher->external_metadata = ExternalMetadataData::from([
            'external_id' => 'quest-12345',
            'external_type' => 'questpay',
            'reference_id' => 'quest-ref-67890',
            'user_id' => 'player-abc123',
            'custom' => [
                'level' => 25,
                'mission' => 'complete-delivery',
                'reward_type' => 'cash',
            ],
        ]);
        $voucher->save();

        // 2. Track timing events
        $voucher->trackClick();
        sleep(1);
        $voucher->trackRedemptionStart();
        sleep(2);
        $voucher->trackRedemptionSubmit();

        // 3. Store validation results
        $location = LocationValidationResultData::from([
            'validated' => true,
            'distance_meters' => 125.8,
            'should_block' => false,
        ]);

        $time = TimeValidationResultData::from([
            'within_window' => true,
            'within_duration' => true,
            'duration_seconds' => 180,
            'should_block' => false,
        ]);

        $voucher->storeValidationResults($location, $time);

        // 4. Add collected inputs
        $voucher->forceSetInput('name', 'John Doe');
        $voucher->forceSetInput('email', 'john.doe@example.com');
        $voucher->forceSetInput('location', json_encode([
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'accuracy' => 15,
            'altitude' => 20.5,
            'address' => [
                'formatted' => '1234 Rizal Avenue, Manila, Philippines',
            ],
            'snapshot' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg...',
        ]));
        $voucher->forceSetInput('signature', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg...[SIGNATURE_DATA]');

        // Redeem voucher
        RedeemVoucher::run($contact, $voucher->code);

        // Generate webhook payload
        $notification = new SendFeedbacksNotification($voucher->code);
        $notifiable = ['webhook' => 'https://webhook.site/unique-id'];
        $webhookData = $notification->toWebhook($notifiable);

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('WEBHOOK ENDPOINT:');
        $this->line($webhookData['url']);
        $this->newLine();

        $this->info('HEADERS:');
        foreach ($webhookData['headers'] as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        $this->newLine();

        $this->info('PAYLOAD (JSON):');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(json_encode($webhookData['payload'], JSON_PRETTY_PRINT));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->newLine();
        $this->info('✅ Complete webhook payload with all enhanced features');
        $this->comment('This payload includes:');
        $this->line('  • Core voucher data (code, amount, status)');
        $this->line('  • External metadata (QuestPay integration)');
        $this->line('  • Timing data (click → start → submit)');
        $this->line('  • Validation results (location & time)');
        $this->line('  • Collected inputs (name, email, GPS, signature)');

        return self::SUCCESS;
    }
}
