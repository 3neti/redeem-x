<?php

namespace App\Console\Commands;

use App\Notifications\VouchersGeneratedSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class TestVoucherGenerationNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:voucher-generation-notification 
                            {mobile : Mobile number to send SMS to}
                            {--count=3 : Number of vouchers to simulate}
                            {--amount=100 : Amount per voucher}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test voucher generation SMS notification without actually generating vouchers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $count = (int) $this->option('count');
        $amount = (float) $this->option('amount');

        $this->info("📱 Testing voucher generation notification...");
        $this->newLine();
        $this->line("   Mobile: {$mobile}");
        $this->line("   Count: {$count}");
        $this->line("   Amount: ₱{$amount}");
        $this->newLine();

        // Find user by mobile number
        $user = \App\Models\User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
              ->where(function ($sub) use ($mobile) {
                  $sub->where('value', 'LIKE', "%{$mobile}%")
                      ->orWhere('value', 'LIKE', "%" . ltrim($mobile, '0') . "%");
              });
        })->first();

        if (!$user) {
            $this->error("❌ No user found with mobile number: {$mobile}");
            $this->line('Please provide a mobile number that exists in the system.');
            return self::FAILURE;
        }

        $this->line("   User: {$user->name} ({$user->email})");
        $this->newLine();

        // Create mock vouchers
        $mockVouchers = $this->createMockVouchers($count, $amount);

        try {
            // Send notification via user model (uses routeNotificationForEngageSpark)
            $user->notify(new VouchersGeneratedSummary($mockVouchers));

            $this->info("✅ Notification queued successfully to {$user->name}!");
            $this->newLine();
            
            // Show what the message will look like
            $notification = new VouchersGeneratedSummary($mockVouchers);
            $context = $this->invokeProtectedMethod($notification, 'buildContext');
            
            $templateKey = match (true) {
                $context['count'] === 1 => 'notifications.vouchers_generated.sms.single',
                $context['count'] <= 3 => 'notifications.vouchers_generated.sms.multiple',
                default => 'notifications.vouchers_generated.sms.many',
            };
            
            $template = __($templateKey);
            $message = \App\Services\TemplateProcessor::process($template, $context);
            
            $channels = $notification->via($user);
            $this->line('<fg=yellow>Channels:</>  ' . implode(', ', $channels));
            $this->line('<fg=yellow>Expected SMS message:</>');
            $this->line("   {$message}");
            $this->newLine();
            $this->line('Note: Check your queue worker to ensure the job is processed.');
            $this->line('Note: EngageSpark must be enabled for SMS delivery.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send notification: ' . $e->getMessage());
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            } else {
                $this->line('Run with -v for more details');
            }

            return self::FAILURE;
        }
    }

    /**
     * Create mock voucher objects for testing.
     */
    protected function createMockVouchers(int $count, float $amount): Collection
    {
        $vouchers = collect();

        for ($i = 1; $i <= $count; $i++) {
            $code = 'TEST' . strtoupper(substr(md5((string) $i), 0, 4));
            
            // Create mock voucher with minimal required data
            $voucher = new \stdClass();
            $voucher->code = $code;
            $voucher->instructions = VoucherInstructionsData::from([
                'cash' => [
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'payable' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                    'settlement_rail' => null,
                    'fee_strategy' => 'absorb',
                ],
                'inputs' => ['fields' => []],
                'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
                'rider' => ['message' => null, 'url' => null, 'redirect_timeout' => null, 'splash' => null, 'splash_timeout' => null],
                'count' => 1,
            ]);

            $vouchers->push($voucher);
        }

        return $vouchers;
    }

    /**
     * Invoke a protected method for testing purposes.
     */
    protected function invokeProtectedMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($object, ...$args);
    }
}
