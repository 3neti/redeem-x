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

        $instructions = VoucherInstructionsData::from([
            'cash' => [
                'amount' => 1, // Minimal amount for testing
                'currency' => 'PHP',
                'validation' => [],
            ],
            'inputs' => [
                'fields' => [], // No input fields
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
        $this->line("   Input fields: None");
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

        // Step 3: Create contact and redeem
        $this->newLine();
        $this->info('👤 Creating contact...');
        
        $contact = Contact::factory()->create([
            'mobile' => '09178251991',
            'name' => 'Test Redeemer',
        ]);
        
        $this->line("   Mobile: {$contact->mobile}");
        $this->line("   Name: {$contact->name}");

        // Step 4: Redeem voucher
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

        // Cleanup option
        $this->newLine();
        if ($this->confirm('🗑️  Delete test voucher and contact?', true)) {
            $contact->delete();
            $voucher->delete();
            $this->info('✅ Test data cleaned up');
        }

        return self::SUCCESS;
    }
}
