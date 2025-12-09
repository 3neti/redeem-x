<?php

namespace App\Actions\Notification;

use App\Notifications\SendFeedbacksNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SendFeedback Action
 * 
 * Sends post-redemption feedback notifications based on configured channels.
 * This is a standalone action that wraps the SendFeedbacks pipeline logic.
 */
class SendFeedback
{
    use AsAction;

    /**
     * Send feedback notifications to configured channels.
     *
     * @param  Voucher  $voucher  The redeemed voucher
     * @param  Contact  $contact  The contact who redeemed
     * @return bool  True if notifications were sent, false if no channels configured
     */
    public function handle(Voucher $voucher, Contact $contact): bool
    {
        // Get feedback configuration from voucher instructions
        $rawFeedbacks = $voucher->getData()->instructions->feedback->toArray() ?? [];
        
        // Filter out empty values
        $feedbacks = array_filter($rawFeedbacks, fn($value) => !empty($value));
        
        // Convert to notification routes
        $routes = $this->getRoutesFromFeedbacks($feedbacks);
        
        Log::info('[SendFeedback] Feedback routes resolved', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'routes' => array_keys($routes),
        ]);
        
        // If no feedback channels configured, return false
        if (empty($routes)) {
            Log::info('[SendFeedback] No valid feedback routes found', [
                'voucher' => $voucher->code,
            ]);
            return false;
        }
        
        // Send notification via configured channels
        Notification::routes($routes)->notify(new SendFeedbacksNotification($voucher->code));
        
        Log::info('[SendFeedback] Feedback notification sent', [
            'voucher' => $voucher->code,
            'channels' => array_keys($routes),
        ]);
        
        return true;
    }
    
    /**
     * Convert feedback map to route format for Notification::routes()
     *
     * @param  array  $feedbacks  ['email' => ..., 'mobile' => ..., 'webhook' => ...]
     * @return array  ['mail' => ..., 'engage_spark' => ..., 'webhook' => ...]
     */
    private function getRoutesFromFeedbacks(array $feedbacks): array
    {
        $routes = [];
        
        foreach ($feedbacks as $key => $value) {
            match ($key) {
                'email' => $routes['mail'] = $value,
                'mobile' => $routes['engage_spark'] = $value,
                'webhook' => $routes['webhook'] = $value,
                default => null, // skip unrecognized keys
            };
        }
        
        return $routes;
    }
}
