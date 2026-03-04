<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver;

/**
 * Manage Telegram webhook registration.
 *
 * Usage:
 *   php artisan messaging:webhook set    # Register webhook
 *   php artisan messaging:webhook info   # Check status
 *   php artisan messaging:webhook delete # Remove webhook
 */
class WebhookCommand extends Command
{
    protected $signature = 'messaging:webhook
        {action=info : Action to perform (set, info, delete)}
        {--url= : Custom webhook URL (defaults to APP_URL/messaging/telegram/webhook)}';

    protected $description = 'Manage Telegram webhook registration';

    public function handle(TelegramDriver $driver): int
    {
        if (! $driver->isConfigured()) {
            $this->error('Telegram bot token is not configured.');
            $this->line('Set TELEGRAM_BOT_TOKEN in your .env file.');

            return Command::FAILURE;
        }

        $action = $this->argument('action');

        return match ($action) {
            'set' => $this->setWebhook($driver),
            'delete' => $this->deleteWebhook($driver),
            'info' => $this->showInfo($driver),
            default => $this->invalidAction($action),
        };
    }

    protected function setWebhook(TelegramDriver $driver): int
    {
        $url = $this->option('url') ?? $this->buildWebhookUrl();

        if (! str_starts_with($url, 'https://')) {
            $this->error('Webhook URL must use HTTPS.');
            $this->line("Got: {$url}");

            return Command::FAILURE;
        }

        $this->info("Setting webhook to: {$url}");

        try {
            $result = $driver->setWebhook($url);
            $this->info('✅ Webhook registered successfully!');

            if (config('messaging-bot.drivers.telegram.webhook_secret')) {
                $this->line('<fg=gray>Secret token configured for verification.</>');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to set webhook: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function deleteWebhook(TelegramDriver $driver): int
    {
        $this->info('Deleting webhook...');

        try {
            $driver->deleteWebhook();
            $this->info('✅ Webhook deleted. You can now use polling mode.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to delete webhook: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function showInfo(TelegramDriver $driver): int
    {
        $this->info('Fetching webhook info...');
        $this->newLine();

        try {
            $info = $driver->getWebhookInfo();

            if (empty($info['url'])) {
                $this->warn('No webhook configured. Using polling mode.');
                $this->line('Run `php artisan messaging:webhook set` to register a webhook.');

                return Command::SUCCESS;
            }

            $this->table(
                ['Property', 'Value'],
                [
                    ['URL', $info['url']],
                    ['Has Secret', $info['has_custom_certificate'] ?? false ? 'Yes' : 'No'],
                    ['Pending Updates', $info['pending_update_count'] ?? 0],
                    ['Last Error', $info['last_error_message'] ?? 'None'],
                    ['Last Error Date', isset($info['last_error_date'])
                        ? date('Y-m-d H:i:s', $info['last_error_date'])
                        : 'N/A'],
                    ['Max Connections', $info['max_connections'] ?? 40],
                    ['Allowed Updates', implode(', ', $info['allowed_updates'] ?? ['all'])],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to get webhook info: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function buildWebhookUrl(): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $prefix = config('messaging-bot.routes.prefix', 'messaging');

        return "{$baseUrl}/{$prefix}/telegram/webhook";
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Valid actions: set, info, delete');

        return Command::FAILURE;
    }
}
