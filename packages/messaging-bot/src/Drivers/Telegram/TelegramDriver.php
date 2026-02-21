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

        // Handle keyboard options
        if ($response->wantsKeyboardRemoved()) {
            // Remove custom keyboard
            $payload['reply_markup'] = [
                'remove_keyboard' => true,
            ];
        } elseif ($response->wantsContactRequest()) {
            // Contact request keyboard
            $payload['reply_markup'] = [
                'keyboard' => [
                    [
                        [
                            'text' => '📱 Share Phone Number',
                            'request_contact' => true,
                        ],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
        } elseif ($response->wantsLocationRequest()) {
            // Location request keyboard
            $payload['reply_markup'] = [
                'keyboard' => [
                    [
                        [
                            'text' => '📍 Share Location',
                            'request_location' => true,
                        ],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ];
        } elseif ($response->hasButtons()) {
            // Inline keyboard buttons
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
    public function setWebhook(string $url): bool
    {
        $payload = [
            'url' => $url,
        ];

        if ($this->webhookSecret) {
            $payload['secret_token'] = $this->webhookSecret;
        }

        $this->requestRaw('setWebhook', $payload);

        return true;
    }

    /**
     * Delete the webhook for this bot.
     */
    public function deleteWebhook(): bool
    {
        $this->requestRaw('deleteWebhook');

        return true;
    }

    /**
     * Get webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Send a message with a contact request keyboard button.
     *
     * The keyboard shows a button that, when tapped, prompts the user
     * to share their phone number with the bot.
     */
    public function requestContact(string $chatId, string $text, string $buttonText = '📱 Share Phone Number'): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'keyboard' => [
                    [
                        [
                            'text' => $buttonText,
                            'request_contact' => true,
                        ],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ];

        try {
            $this->request('sendMessage', $payload);
        } catch (GuzzleException $e) {
            Log::error('[TelegramDriver] Failed to request contact', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Remove custom keyboard and return to default.
     */
    public function removeKeyboard(string $chatId, string $text): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ];

        $this->request('sendMessage', $payload);
    }

    /**
     * Answer a callback query (acknowledge inline button press).
     *
     * This removes the "loading" spinner on the button and optionally
     * shows a notification to the user.
     */
    public function answerCallbackQuery(?string $callbackQueryId, ?string $text = null, bool $showAlert = false): void
    {
        if (! $callbackQueryId) {
            return;
        }

        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $payload['text'] = $text;
            $payload['show_alert'] = $showAlert;
        }

        try {
            $this->requestRaw('answerCallbackQuery', $payload);
        } catch (GuzzleException $e) {
            Log::warning('[TelegramDriver] Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get updates using long polling (for local development).
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        // Use a separate client with extended timeout for long polling
        $response = $this->client->post("/bot{$this->token}/getUpdates", [
            'json' => [
                'offset' => $offset,
                'timeout' => $timeout,
            ],
            'timeout' => $timeout + 5, // Allow extra time for HTTP overhead
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! ($data['ok'] ?? false)) {
            throw new \RuntimeException($data['description'] ?? 'Unknown Telegram API error');
        }

        return $data['result'] ?? [];
    }

    /**
     * Make a request to the Telegram Bot API (expects array result).
     */
    protected function request(string $method, array $payload = []): array
    {
        $result = $this->requestRaw($method, $payload);

        return is_array($result) ? $result : [];
    }

    /**
     * Make a raw request to the Telegram Bot API.
     */
    protected function requestRaw(string $method, array $payload = []): mixed
    {
        $response = $this->client->post("/bot{$this->token}/{$method}", [
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! ($data['ok'] ?? false)) {
            throw new \RuntimeException($data['description'] ?? 'Unknown Telegram API error');
        }

        return $data['result'] ?? null;
    }
}
