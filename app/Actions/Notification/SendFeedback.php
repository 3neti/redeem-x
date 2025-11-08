<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Send feedback notifications after voucher redemption.
 *
 * Sends notifications via:
 * - Email (if configured)
 * - SMS (if configured)
 * - Webhook (if configured)
 *
 * TODO: Implement actual email/SMS sending
 * This is a stub implementation for Phase 2.
 */
class SendFeedback
{
    use AsAction;

    /**
     * Send feedback notifications.
     *
     * @param  Voucher  $voucher  The redeemed voucher
     * @param  Contact  $contact  The redeemer
     * @return bool  True if at least one notification sent successfully
     */
    public function handle(Voucher $voucher, Contact $contact): bool
    {
        $feedback = $voucher->instructions->feedback;
        $sentCount = 0;

        Log::info('[SendFeedback] Sending feedback notifications', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'has_email' => ! empty($feedback->email),
            'has_mobile' => ! empty($feedback->mobile),
            'has_webhook' => ! empty($feedback->webhook),
        ]);

        // Send email notification
        if (! empty($feedback->email)) {
            if ($this->sendEmailNotification($voucher, $contact, $feedback->email)) {
                $sentCount++;
            }
        }

        // Send SMS notification
        if (! empty($feedback->mobile)) {
            if ($this->sendSmsNotification($voucher, $contact, $feedback->mobile)) {
                $sentCount++;
            }
        }

        // Send webhook notification
        if (! empty($feedback->webhook)) {
            if ($this->sendWebhookNotification($voucher, $contact, $feedback->webhook)) {
                $sentCount++;
            }
        }

        Log::info('[SendFeedback] Feedback notifications sent', [
            'voucher' => $voucher->code,
            'sent_count' => $sentCount,
        ]);

        return $sentCount > 0;
    }

    /**
     * Send email notification.
     *
     * @param  Voucher  $voucher
     * @param  Contact  $contact
     * @param  string  $email
     * @return bool
     */
    protected function sendEmailNotification(Voucher $voucher, Contact $contact, string $email): bool
    {
        // TODO: Implement email sending
        // - Use Laravel Mail
        // - Create mailable class
        // - Queue for async sending

        Log::info('[SendFeedback] Email notification (stub)', [
            'voucher' => $voucher->code,
            'email' => $email,
        ]);

        return true;
    }

    /**
     * Send SMS notification.
     *
     * @param  Voucher  $voucher
     * @param  Contact  $contact
     * @param  string  $mobile
     * @return bool
     */
    protected function sendSmsNotification(Voucher $voucher, Contact $contact, string $mobile): bool
    {
        // TODO: Implement SMS sending
        // - Use omnichannel package
        // - Queue for async sending

        Log::info('[SendFeedback] SMS notification (stub)', [
            'voucher' => $voucher->code,
            'mobile' => $mobile,
        ]);

        return true;
    }

    /**
     * Send webhook notification.
     *
     * @param  Voucher  $voucher
     * @param  Contact  $contact
     * @param  string  $webhookUrl
     * @return bool
     */
    protected function sendWebhookNotification(Voucher $voucher, Contact $contact, string $webhookUrl): bool
    {
        try {
            $payload = [
                'event' => 'voucher.redeemed',
                'voucher' => [
                    'code' => $voucher->code,
                    'amount' => $voucher->instructions->cash->amount,
                    'currency' => $voucher->instructions->cash->currency,
                    'redeemed_at' => $voucher->redeemed_at?->toISOString(),
                ],
                'contact' => [
                    'mobile' => $contact->mobile,
                    'name' => $contact->name ?? null,
                ],
            ];

            $response = Http::timeout(5)->post($webhookUrl, $payload);

            Log::info('[SendFeedback] Webhook notification sent', [
                'voucher' => $voucher->code,
                'webhook_url' => $webhookUrl,
                'status_code' => $response->status(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('[SendFeedback] Webhook notification failed', [
                'voucher' => $voucher->code,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
