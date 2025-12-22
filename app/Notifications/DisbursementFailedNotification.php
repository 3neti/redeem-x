<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LBHurtado\Voucher\Models\Voucher;

class DisbursementFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Voucher $voucher,
        protected \Throwable $exception
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $contact = $this->voucher->contacts->first();
        $mobile = $contact?->mobile ?? 'Unknown';
        $amount = $this->voucher->cash?->formatted_amount ?? 'Unknown';
        
        return (new MailMessage)
            ->error()
            ->subject('🚨 Disbursement Failed: ' . $this->voucher->code)
            ->greeting('Disbursement Failure Alert')
            ->line('A disbursement has failed and requires immediate attention.')
            ->line('**Voucher Code:** ' . $this->voucher->code)
            ->line('**Amount:** ' . $amount)
            ->line('**Redeemer Mobile:** ' . $mobile)
            ->line('**Error:** ' . $this->exception->getMessage())
            ->line('**Time:** ' . now()->format('F j, Y g:i A'))
            ->action('View Voucher Details', url('/vouchers/' . $this->voucher->id))
            ->line('Please investigate and take appropriate action for customer support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->code,
            'amount' => $this->voucher->cash?->amount,
            'error_message' => $this->exception->getMessage(),
            'error_type' => get_class($this->exception),
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
