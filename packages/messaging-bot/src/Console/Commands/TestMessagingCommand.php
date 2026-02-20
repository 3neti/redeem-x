<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Data\Platform;
use LBHurtado\MessagingBot\Engine\MessagingKernel;

/**
 * Test messaging bot commands locally.
 *
 * Similar to test:sms-router, this command simulates incoming messages
 * and routes them through the MessagingKernel for debugging.
 *
 * Usage:
 *   php artisan test:messaging "/redeem"
 *   php artisan test:messaging "ABC123"
 *   php artisan test:messaging "YES"
 */
class TestMessagingCommand extends Command
{
    protected $signature = 'test:messaging
        {message : The message to send}
        {--chat-id=12345 : Simulated chat ID (maintains conversation state)}
        {--mobile=09173011987 : Simulated mobile number for contact sharing}
        {--platform=telegram : Platform to simulate (telegram, whatsapp, viber)}
        {--contact : Simulate sharing contact (sends phone number)}';

    protected $description = 'Test messaging bot commands locally (simulates incoming messages)';

    public function handle(MessagingKernel $kernel): int
    {
        $message = $this->argument('message');
        $chatId = $this->option('chat-id');
        $mobile = $this->option('mobile');
        $platformName = $this->option('platform');
        $isContact = $this->option('contact');

        // Resolve platform
        $platform = Platform::tryFrom($platformName);
        if (! $platform) {
            $this->error("Invalid platform: {$platformName}");
            $this->line('Valid platforms: telegram, whatsapp, viber');

            return Command::FAILURE;
        }

        // Normalize mobile to E.164
        if (! str_starts_with($mobile, '+')) {
            $mobile = '+63'.ltrim($mobile, '0');
        }

        $this->info("📱 Testing {$platform->label()} message");
        $this->line("   Chat ID: {$chatId}");
        $this->line("   Message: \"{$message}\"");
        if ($isContact) {
            $this->line("   Contact: {$mobile}");
        }
        $this->newLine();

        // Create fake update
        $update = NormalizedUpdate::fake(
            text: $isContact ? null : $message,
            chatId: $chatId,
            platform: $platform,
            phoneNumber: $isContact ? $mobile : null,
            firstName: 'TestUser',
        );

        try {
            $response = $kernel->handle($update);

            $this->info('📤 Bot Response:');
            $this->newLine();

            // Strip HTML tags for console display
            $text = strip_tags($response->text);
            foreach (explode("\n", $text) as $line) {
                $this->line("   {$line}");
            }

            if ($response->hasButtons()) {
                $this->newLine();
                $this->line('   Buttons:');
                foreach ($response->buttons as $button) {
                    $this->line("   [{$button['text']}]");
                }
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Error: '.$e->getMessage());
            $this->newLine();
            $this->line("<fg=gray>File: {$e->getFile()}:{$e->getLine()}</>");
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            } else {
                $this->line('<fg=gray>Run with -v for stack trace</>');
            }

            return Command::FAILURE;
        }
    }
}
