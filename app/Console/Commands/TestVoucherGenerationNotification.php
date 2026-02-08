<?php

namespace App\Console\Commands;

use App\Notifications\VouchersGeneratedSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
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
                            {--amount=100 : Amount per voucher}
                            {--format=none : Instructions format: none, json, or human}
                            {--complex : Use complex voucher instructions for testing}';

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
        $format = $this->option('format');
        $complex = $this->option('complex');

        // Validate format option
        if (! in_array($format, ['none', 'json', 'human'])) {
            $this->error("Invalid format: {$format}. Must be: none, json, or human");

            return self::FAILURE;
        }

        // Temporarily set config for this test
        config(['voucher-notifications.vouchers_generated.instructions_format' => $format]);

        $this->info('📱 Testing voucher generation notification...');
        $this->newLine();
        $this->line("   Mobile: {$mobile}");
        $this->line("   Count: {$count}");
        $this->line("   Amount: ₱{$amount}");
        $this->line("   Format: {$format}");
        $this->line('   Complex: '.($complex ? 'yes' : 'no'));
        $this->newLine();

        // Find user by mobile number
        $user = \App\Models\User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where(function ($sub) use ($mobile) {
                    $sub->where('value', 'LIKE', "%{$mobile}%")
                        ->orWhere('value', 'LIKE', '%'.ltrim($mobile, '0').'%');
                });
        })->first();

        if (! $user) {
            $this->error("❌ No user found with mobile number: {$mobile}");
            $this->line('Please provide a mobile number that exists in the system.');

            return self::FAILURE;
        }

        $this->line("   User: {$user->name} ({$user->email})");
        $this->newLine();

        // Create mock vouchers
        $mockVouchers = $complex
            ? $this->createComplexMockVouchers($count, $amount)
            : $this->createMockVouchers($count, $amount);

        try {
            // Send notification via user model (uses routeNotificationForEngageSpark)
            $user->notify(new VouchersGeneratedSummary($mockVouchers));

            $this->info("✅ Notification queued successfully to {$user->name}!");
            $this->newLine();

            // Show what the message will look like
            $notification = new VouchersGeneratedSummary($mockVouchers);
            $context = $this->invokeProtectedMethod($notification, 'buildContext');

            $hasInstructions = $format !== 'none' && isset($context['instructions_formatted']);
            $templateKey = match (true) {
                $context['count'] === 1 && $hasInstructions => 'notifications.vouchers_generated.sms.single_with_instructions',
                $context['count'] === 1 => 'notifications.vouchers_generated.sms.single',
                $context['count'] <= 3 && $hasInstructions => 'notifications.vouchers_generated.sms.multiple_with_instructions',
                $context['count'] <= 3 => 'notifications.vouchers_generated.sms.multiple',
                $hasInstructions => 'notifications.vouchers_generated.sms.many_with_instructions',
                default => 'notifications.vouchers_generated.sms.many',
            };

            $template = __($templateKey);
            $message = \App\Services\TemplateProcessor::process($template, $context);

            $channels = $notification->via($user);
            $this->line('<fg=yellow>Channels:</>  '.implode(', ', $channels));
            $this->line('<fg=yellow>Format:</>    '.$format);
            $this->line('<fg=yellow>Expected SMS message:</>');
            $this->line("   {$message}");
            $this->line('<fg=yellow>SMS Length:</>  '.mb_strlen($message).' characters');
            $this->newLine();

            if ($hasInstructions && isset($context['instructions_formatted'])) {
                $this->line('<fg=yellow>Email Instructions:</>');
                $this->line(str_repeat('-', 60));
                $this->line($context['instructions_formatted']);
                $this->line(str_repeat('-', 60));
                $this->newLine();
            }

            $this->line('Note: Check your queue worker to ensure the job is processed.');
            $this->line('Note: EngageSpark must be enabled for SMS delivery.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send notification: '.$e->getMessage());
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
            $code = 'TEST'.strtoupper(substr(md5((string) $i), 0, 4));

            // Create mock voucher with minimal required data
            $voucher = new \stdClass;
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
     * Create mock vouchers with complex instructions for testing.
     */
    protected function createComplexMockVouchers(int $count, float $amount): Collection
    {
        $vouchers = collect();

        for ($i = 1; $i <= $count; $i++) {
            $code = 'TEST'.strtoupper(substr(md5((string) $i), 0, 4));

            // Create mock voucher with complex instructions
            $voucher = new \stdClass;
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
                    'settlement_rail' => 'INSTAPAY',
                    'fee_strategy' => 'absorb',
                ],
                'inputs' => [
                    'fields' => [
                        'name',
                        'email',
                        'location',
                    ],
                ],
                'feedback' => [
                    'email' => 'support@example.com',
                    'mobile' => null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => 'Thank you for redeeming your voucher!',
                    'url' => 'https://example.com',
                    'redirect_timeout' => 5,
                    'splash' => null,
                    'splash_timeout' => null,
                ],
                'count' => 1,
                'ttl' => 'P7D', // 7 days
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
