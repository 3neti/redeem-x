<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\DisbursementFailedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Wallet\Events\DisbursementFailed;
use LBHurtado\Wallet\Services\SystemUserResolverService;

class NotifyAdminOfDisbursementFailure
{
    public function __construct(
        protected SystemUserResolverService $systemUserResolver
    ) {}
    /**
     * Handle the event.
     */
    public function handle(DisbursementFailed $event): void
    {
        // Check if alerts are enabled
        if (!config('disbursement.alerts.enabled', true)) {
            return;
        }

        // Throttle notifications to prevent alert spam during outages
        if ($this->shouldThrottle($event)) {
            return;
        }

        // Collect all recipients
        $recipients = collect();
        
        // 1. System user (primary admin)
        try {
            $systemUser = $this->systemUserResolver->resolve();
            $recipients->push($systemUser);
        } catch (\Throwable $e) {
            // System user not found, continue with other recipients
        }
        
        // 2. Users with admin role
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();
        $recipients = $recipients->merge($admins)->unique('id');

        // Send notification to all user recipients
        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                DisbursementFailedNotification::fromException(
                    $event->voucher,
                    $event->exception,
                    $event->mobile
                )
            );
        }

        // 3. Additional email addresses from config (if no user recipients)
        $emails = config('disbursement.alerts.emails', []);
        if ($recipients->isEmpty() && !empty($emails)) {
            Notification::route('mail', $emails)
                ->notify(
                    DisbursementFailedNotification::fromException(
                        $event->voucher,
                        $event->exception,
                        $event->mobile
                    )
                );
        }
    }

    /**
     * Check if notification should be throttled.
     * 
     * Strategy: Allow first alert, then suppress duplicate alerts for same error type
     * within the cooldown window. This prevents alert spam during outages.
     */
    protected function shouldThrottle(DisbursementFailed $event): bool
    {
        $throttleMinutes = config('disbursement.alerts.throttle_minutes', 30);
        
        // Disabled throttling
        if ($throttleMinutes <= 0) {
            return false;
        }

        // Generate cache key based on error signature
        // Group by exception class to catch "network timeout", "gateway error", etc.
        $errorSignature = get_class($event->exception);
        $cacheKey = "disbursement_alert_throttle:{$errorSignature}";

        // Check if we've recently sent an alert for this error type
        if (Cache::has($cacheKey)) {
            // Increment suppressed count for metrics
            Cache::increment("disbursement_alert_suppressed:{$errorSignature}");
            return true; // Throttle this alert
        }

        // Allow this alert and set cooldown
        Cache::put($cacheKey, now(), now()->addMinutes($throttleMinutes));
        
        return false; // Don't throttle
    }
}
