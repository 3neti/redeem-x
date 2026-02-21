<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver;
use LBHurtado\MessagingBot\Engine\MessagingKernel;

/**
 * Handles incoming Telegram webhook requests.
 *
 * This controller verifies the webhook signature, parses the update,
 * routes it through the messaging kernel, and sends the response.
 */
class TelegramWebhookController
{
    public function __construct(
        protected TelegramDriver $driver,
        protected MessagingKernel $kernel,
    ) {}

    /**
     * Handle the incoming webhook request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Verify webhook authenticity
        if (! $this->driver->verifyWebhook($request)) {
            Log::warning('[TelegramWebhook] Invalid webhook signature');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get the update payload
        $payload = $request->all();

        // Skip if no valid message
        if (! $this->hasValidMessage($payload)) {
            return response()->json(['ok' => true]);
        }

        try {
            // Parse the update
            $update = $this->driver->parseUpdate($payload);

            Log::info('[TelegramWebhook] Processing update', [
                'chat_id' => $update->chatId,
                'text' => $update->text,
                'is_callback' => $update->isCallbackQuery(),
                'has_phone' => $update->hasPhoneNumber(),
                'has_location' => $update->hasLocation(),
                'has_photo' => $update->hasPhoto(),
            ]);

            // Answer callback query immediately (removes loading spinner)
            if ($update->isCallbackQuery()) {
                $this->driver->answerCallbackQuery($update->callbackQueryId());
            }

            // Process through kernel
            $response = $this->kernel->handle($update);

            // Send response
            $this->driver->sendMessage($update->chatId, $response);

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            Log::error('[TelegramWebhook] Failed to process update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Telegram from retrying
            return response()->json(['ok' => true, 'error' => 'Processing failed']);
        }
    }

    /**
     * Check if the payload contains a valid message.
     */
    protected function hasValidMessage(array $payload): bool
    {
        // Regular text message
        if (isset($payload['message']['text'])) {
            return true;
        }

        // Contact shared (phone number)
        if (isset($payload['message']['contact'])) {
            return true;
        }

        // Location shared
        if (isset($payload['message']['location'])) {
            return true;
        }

        // Photo shared
        if (isset($payload['message']['photo'])) {
            return true;
        }

        // Web app data (sent from Mini Apps via sendData())
        if (isset($payload['message']['web_app_data'])) {
            return true;
        }

        // Callback query (inline button press)
        if (isset($payload['callback_query'])) {
            return true;
        }

        return false;
    }
}
