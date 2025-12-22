<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\DisbursementFailedNotification;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Wallet\Events\DisbursementFailed;

class NotifyAdminOfDisbursementFailure
{
    /**
     * Handle the event.
     */
    public function handle(DisbursementFailed $event): void
    {
        // Check if alerts are enabled
        if (!config('disbursement.alerts.enabled', true)) {
            return;
        }

        // Get admin emails from config
        $emails = config('disbursement.alerts.emails', []);
        
        // Also notify users with admin role (if role system exists)
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        // Send notification to admins
        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                DisbursementFailedNotification::fromException(
                    $event->voucher,
                    $event->exception
                )
            );
        }

        // Send to configured email addresses if no admin users found
        if ($admins->isEmpty() && !empty($emails)) {
            Notification::route('mail', $emails)
                ->notify(
                    DisbursementFailedNotification::fromException(
                        $event->voucher,
                        $event->exception
                    )
                );
        }
    }
}
