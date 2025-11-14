<?php

namespace App\Notifications;

use App\Models\AccountBalance;
use App\Models\BalanceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected AccountBalance $balance,
        protected BalanceAlert $alert
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
        return (new MailMessage)
            ->error()
            ->subject('⚠️ Low Balance Alert: ' . $this->balance->account_number)
            ->greeting('Low Balance Alert')
            ->line('Your account balance has fallen below the configured threshold.')
            ->line('**Account:** ' . $this->balance->account_number)
            ->line('**Gateway:** ' . ucfirst($this->balance->gateway))
            ->line('**Current Balance:** ' . $this->balance->formatted_balance)
            ->line('**Available Balance:** ' . $this->balance->formatted_available_balance)
            ->line('**Threshold:** ' . $this->alert->formatted_threshold)
            ->line('**Checked At:** ' . $this->balance->checked_at->format('F j, Y g:i A'))
            ->line('Please take appropriate action to ensure sufficient funds are available.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'account_number' => $this->balance->account_number,
            'gateway' => $this->balance->gateway,
            'balance' => $this->balance->balance,
            'available_balance' => $this->balance->available_balance,
            'threshold' => $this->alert->threshold,
            'currency' => $this->balance->currency,
            'checked_at' => $this->balance->checked_at->toIso8601String(),
        ];
    }
}
