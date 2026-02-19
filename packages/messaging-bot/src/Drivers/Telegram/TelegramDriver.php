<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Drivers\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\MessagingBot\Contracts\MessagingDriverInterface;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Data\Platform;

/**
 * Telegram Bot API driver.
 *
 * Handles communication with the Telegram Bot API, including
 * parsing incoming updates and sending responses.
 */
class TelegramDriver implements MessagingDriverInterface
{
    protected Client $client;

    protected string $baseUrl = 'https://api.telegram.org';

    public function __construct(
        protected ?string $token = null,
        protected ?string $webhookSecret = null,
    ) {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Get the platform this driver handles.
     */
    public function platform(): Platform
    {
        return Platform::Telegram;
    }

    /**
     * Parse an incoming webhook payload into a normalized update.
     */
    public function parseUpdate(array $payload): NormalizedUpdate
    {
        return NormalizedUpdate::fromTelegram($payload);
    }

    /**
     * Send a response message to a chat.
     */
    public function sendMessage(string $chatId, NormalizedResponse $response): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $response->text,
        ];

        if ($response->parseMode) {
            $payload['parse_mode'] = $response->parseMode;
        }

        if ($response->disableNotification) {
            $payload['disable_notification'] = true;
        }

        if ($response->hasButtons()) {
            $payload['reply_markup'] = [
                'inline_keyboard' => [$response->buttons],
            ];
        }

        try {
            $this->request('sendMessage', $payload);
        } catch (GuzzleException $e) {
            Log::error('[TelegramDriver] Failed to send message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify the authenticity of an incoming webhook request.
     */
    public function verifyWebhook(Request $request): bool
    {
        // If no secret is configured, skip verification
        if (! $this->webhookSecret) {
            return true;
        }

        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return hash_equals($this->webhookSecret, $headerSecret ?? '');
    }

    /**
     * Determine if this driver is properly configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Set the webhook URL for this bot.
     */
    public function setWebhook(string $url): array
    {
        $payload = [
            'url' => $url,
        ];

        if ($this->webhookSecret) {
            $payload['secret_token'] = $this->webhookSecret;
        }

        return $this->request('setWebhook', $payload);
    }

    /**
     * Delete the webhook for this bot.
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Get webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Get updates using long polling (for local development).
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
        ]);
    }

    /**
     * Make a request to the Telegram Bot API.
     */
    protected function request(string $method, array $payload = []): array
    {
        $response = $this->client->post("/bot{$this->token}/{$method}", [
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! ($data['ok'] ?? false)) {
            throw new \RuntimeException($data['description'] ?? 'Unknown Telegram API error');
        }

        return $data['result'] ?? [];
    }
}
