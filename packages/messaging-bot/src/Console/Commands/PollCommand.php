<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver;
use LBHurtado\MessagingBot\Engine\MessagingKernel;

/**
 * Poll for Telegram updates in local development.
 *
 * Usage: php artisan messaging:poll
 */
class PollCommand extends Command
{
    protected $signature = 'messaging:poll
        {--timeout=10 : Long polling timeout in seconds}
        {--once : Process one batch of updates and exit}';

    protected $description = 'Poll for Telegram messages (local development)';

    public function handle(TelegramDriver $driver, MessagingKernel $kernel): int
    {
        if (! $driver->isConfigured()) {
            $this->error('Telegram bot token is not configured.');
            $this->line('Set TELEGRAM_BOT_TOKEN in your .env file.');

            return Command::FAILURE;
        }

        $this->info('🤖 Starting Telegram bot polling...');
        $this->line('Press Ctrl+C to stop.');
        $this->newLine();

        $offset = 0;
        $timeout = (int) $this->option('timeout');
        $once = $this->option('once');

        while (true) {
            try {
                $this->line('<fg=gray>Polling for updates...</>');

                $updates = $driver->getUpdates($offset, $timeout);

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;

                    $this->processUpdate($driver, $kernel, $update);
                }

                if ($once && ! empty($updates)) {
                    break;
                }

            } catch (\Exception $e) {
                $this->error('Error: '.$e->getMessage());

                if ($once) {
                    return Command::FAILURE;
                }

                sleep(5);
            }
        }

        return Command::SUCCESS;
    }

    protected function processUpdate(TelegramDriver $driver, MessagingKernel $kernel, array $update): void
    {
        $message = $update['message'] ?? $update['callback_query']['message'] ?? null;

        if (! $message) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from']['first_name'] ?? 'Unknown';

        $this->info("📨 [{$chatId}] {$from}: {$text}");

        try {
            $normalized = $driver->parseUpdate($update);
            $response = $kernel->handle($normalized);
            $driver->sendMessage($chatId, $response);

            $this->line("<fg=green>📤 Bot: {$response->text}</>");

        } catch (\Exception $e) {
            $this->error("❌ Error processing message: {$e->getMessage()}");
        }

        $this->newLine();
    }
}
