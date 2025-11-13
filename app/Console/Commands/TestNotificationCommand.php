<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use App\Notifications\SendFeedbacksNotification;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification 
                            {--email= : Email address to send notification to (default: ordinary user)}
                            {--sms= : SMS number to send notification to (default: none)}
                            {--with-location : Include test location data}
                            {--with-signature : Include test signature image}
                            {--with-selfie : Include test selfie image}
                            {--fake : Use Notification::fake() to capture instead of sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test voucher notification system by generating and redeeming a test voucher';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Notification System');
        $this->newLine();
        
        // Temporarily disable disbursement for testing
        config(['voucher-pipeline.post-redemption' => array_filter(
            config('voucher-pipeline.post-redemption'),
            fn($class) => !str_contains($class, 'DisburseCash')
        )]);
        $this->line('💼 Disbursement temporarily disabled for testing');
        $this->newLine();

        // Check if ordinary user exists
        $ordinaryUser = User::where('email', 'lester@hurtado.ph')->first();
        
        if (!$ordinaryUser) {
            $this->error('❌ Ordinary user not found (lester@hurtado.ph)');
            $this->warn('Please run: php artisan db:seed --class=UserSeeder');
            return self::FAILURE;
        }

        $this->info("✅ Found user: {$ordinaryUser->name} ({$ordinaryUser->email})");

        // Authenticate as the user for voucher generation
        auth()->login($ordinaryUser);
        $this->line("🔐 Authenticated as {$ordinaryUser->email}");

        // Use fake notifications if requested
        if ($this->option('fake')) {
            Notification::fake();
            $this->info('📧 Using Notification::fake() - notifications will be captured, not sent');
        }

        // Step 1: Create instructions
        $this->info('📝 Creating voucher instructions...');
        
        $email = $this->option('email') ?: $ordinaryUser->email;
        $sms = $this->option('sms');
        
        $feedback = array_filter([
            'email' => $email,
            'mobile' => $sms,
        ]);

        // Determine input fields based on options
        $inputFields = [];
        if ($this->option('with-location')) {
            $inputFields[] = 'location';
        }
        if ($this->option('with-signature')) {
            $inputFields[] = 'signature';
        }
        if ($this->option('with-selfie')) {
            $inputFields[] = 'selfie';
        }
        
        $instructions = VoucherInstructionsData::from([
            'cash' => [
                'amount' => 1, // Minimal amount for testing
                'currency' => 'PHP',
                'validation' => [],
            ],
            'inputs' => [
                'fields' => $inputFields,
            ],
            'feedback' => $feedback,
            'rider' => [
                'message' => 'Test notification voucher',
            ],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '****',
            'ttl' => 'PT24H',
        ]);

        $this->line("   Amount: ₱1.00");
        $this->line("   Input fields: " . (empty($inputFields) ? 'None' : implode(', ', $inputFields)));
        $this->line("   Feedback email: {$email}");
        if ($sms) {
            $this->line("   Feedback SMS: {$sms}");
        }

        // Step 2: Generate voucher
        $this->newLine();
        $this->info('🎫 Generating voucher...');
        
        $generateAction = app(GenerateVouchers::class);
        $vouchers = $generateAction->handle($instructions, $ordinaryUser);
        $voucher = $vouchers->first();
        
        $this->line("   Code: {$voucher->code}");
        $this->line("   Owner: {$voucher->owner->name}");
        
        // Wait for cash entity to be created by queue (HandleGeneratedVouchers listener)
        if (!$this->option('fake')) {
            $this->newLine();
            $this->info('⏳ Waiting for cash entity to be created by queue worker...');
            
            $attempts = 0;
            $maxAttempts = 10; // 10 seconds max
            while ($attempts < $maxAttempts) {
                $voucher->refresh();
                if ($voucher->cash !== null) {
                    $this->info('✅ Cash entity created!');
                    break;
                }
                sleep(1);
                $attempts++;
            }
            
            if ($voucher->cash === null) {
                $this->error('❌ Timeout: Cash entity not created after 10 seconds');
                $this->warn('Make sure queue worker is running: php artisan queue:work');
                return self::FAILURE;
            }
        }

        // Step 3: Create contact and redeem
        $this->newLine();
        $this->info('👤 Creating contact...');
        
        $contact = Contact::factory()->create([
            'mobile' => '09178251991',
            'name' => 'Test Redeemer',
        ]);
        
        $this->line("   Mobile: {$contact->mobile}");
        $this->line("   Name: {$contact->name}");
        
        // Step 3.5: Add test inputs BEFORE redemption (so they're included in notifications)
        if (!empty($inputFields)) {
            $this->newLine();
            $this->info('📎 Adding test inputs to voucher...');
            
            if ($this->option('with-location')) {
                $location = file_get_contents(base_path('tests/Fixtures/test-location.json'));
                $voucher->location = $location;
                $voucher->save();
                $this->line('   ✓ Location data added');
            }
            
            if ($this->option('with-signature')) {
                $signature = trim(file_get_contents(base_path('tests/Fixtures/test-signature.txt')));
                $voucher->forceSetInput('signature', $signature);
                $this->line('   ✓ Signature image added');
            }
            
            if ($this->option('with-selfie')) {
                $selfie = trim(file_get_contents(base_path('tests/Fixtures/test-selfie.txt')));
                $voucher->forceSetInput('selfie', $selfie);
                $this->line('   ✓ Selfie image added');
            }
        }

        // Step 4: Redeem voucher (this will trigger notifications with the inputs above)
        $this->newLine();
        $this->info('💰 Redeeming voucher...');
        
        $redeemAction = app(RedeemVoucher::class);
        $redeemAction->handle($contact, $voucher->code);
        
        $voucher->refresh();
        $this->line("   Status: {$voucher->status}");
        $this->line("   Redeemed at: {$voucher->redeemed_at}");

        // Step 5: Preview notification content
        $this->newLine();
        if ($this->option('fake')) {
            $this->info('📬 Previewing notification content...');
            
            // Create notification to preview
            $notification = new SendFeedbacksNotification($voucher->code);
            $notifiable = (object) $feedback;
            
            // Preview email content
            $mailData = $notification->toMail($notifiable);
            $this->line("   Email subject: {$mailData->subject}");
            $this->line("   Email body: " . substr($mailData->introLines[0], 0, 100) . '...');
            
            // Preview SMS content
            $smsData = $notification->toEngageSpark($notifiable);
            $this->line("   SMS message: " . substr($smsData->content, 0, 100));
            
            $this->info('✅ Notifications would be sent (fake mode - not actually sent)');
        } else {
            $this->info('✅ Notifications sent!');
            $this->line('   Check your email/SMS for the notification');
        }

        // Step 6: Show template preview
        $this->newLine();
        $this->info('📄 Template Preview:');
        $this->line('   You can customize templates in: lang/en/notifications.php');
        $this->line('   Documentation: docs/NOTIFICATION_TEMPLATES.md');

        // Cleanup option (only in fake mode to avoid race condition with queue)
        $this->newLine();
        if ($this->option('fake')) {
            if ($this->confirm('🗑️  Delete test voucher and contact?', true)) {
                $contact->delete();
                $voucher->delete();
                $this->info('✅ Test data cleaned up');
            }
        } else {
            $this->warn('⚠️  Skipping cleanup - voucher needs to be processed by queue worker');
            $this->line('   Voucher ID: ' . $voucher->id);
            $this->line('   Contact ID: ' . $contact->id);
            $this->line('   Run queue worker to complete cash minting: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
