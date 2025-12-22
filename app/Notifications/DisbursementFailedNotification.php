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
        protected string $errorMessage,
        protected string $errorType,
        protected ?string $mobile = null
    ) {}
    
    /**
     * Create from exception (convenience method)
     */
    public static function fromException(Voucher $voucher, \Throwable $exception, ?string $mobile = null): self
    {
        return new self(
            $voucher,
            $exception->getMessage(),
            get_class($exception),
            $mobile
        );
    }

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
        // Refresh voucher to get latest cash entity in queued context
        $this->voucher->refresh();
        
        $mobile = $this->mobile ?? 'Unknown';
        $amount = $this->voucher->cash?->formatted_amount ?? 'Unknown';
        
        return (new MailMessage)
            ->error()
            ->subject('🚨 Disbursement Failed: ' . $this->voucher->code)
            ->greeting('Disbursement Failure Alert')
            ->line('A disbursement has failed and requires immediate attention.')
            ->line('**Voucher Code:** ' . $this->voucher->code)
            ->line('**Amount:** ' . $amount)
            ->line('**Redeemer Mobile:** ' . $mobile)
            ->line('**Error:** ' . $this->errorMessage)
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
            'error_message' => $this->errorMessage,
            'error_type' => $this->errorType,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
