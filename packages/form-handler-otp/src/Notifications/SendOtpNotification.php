<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use LBHurtado\EngageSpark\EngageSparkMessage;

class SendOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $mobile,
        protected string $otp,
        protected string $appName
    ) {}

    public function via(object $notifiable): array
    {
        $notifiable->route('engage_spark', $this->mobile);

        return ['engage_spark'];
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $message = "{$this->otp} is your authentication code. Do not share.\n- {$this->appName}";

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mobile' => $this->mobile,
            'otp' => $this->otp,
        ];
    }
}
