<?php

namespace App\Notifications\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toWebhook')) {
            return;
        }

        $data = $notification->toWebhook($notifiable);

        if (! isset($data['url'])) {
            Log::warning('[WebhookChannel] No webhook URL provided', [
                'notification' => get_class($notification),
            ]);

            return;
        }

        $url = $data['url'];
        $payload = $data['payload'] ?? [];
        $headers = $data['headers'] ?? [];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('[WebhookChannel] Webhook sent successfully', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('[WebhookChannel] Webhook request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('[WebhookChannel] Webhook request exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
