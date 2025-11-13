<?php

namespace App\Notifications;

use App\Services\TemplateProcessor;
use App\Services\VoucherTemplateContextBuilder;
use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\{Arr, Number};
use Illuminate\Bus\Queueable;
use App\Notifications\Channels\WebhookChannel;

class SendFeedbacksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected VoucherData $voucher;

    public function __construct(string $voucherCode)
    {
        $model = Voucher::where('code', $voucherCode)->firstOrFail();
        $this->voucher = VoucherData::fromModel($model);
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark', 'database'];
        // TODO: Re-enable webhook channel once properly tested
        // return ['mail', 'engage_spark', WebhookChannel::class, 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);
        
        // Get templates from translations
        $subject = __('notifications.voucher_redeemed.email.subject');
        $greeting = __('notifications.voucher_redeemed.email.greeting');
        $body = __('notifications.voucher_redeemed.email.body');
        $warning = __('notifications.voucher_redeemed.email.warning');
        $salutation = __('notifications.voucher_redeemed.email.salutation');
        
        // Process templates with context
        $processedSubject = TemplateProcessor::process($subject, $context);
        $processedGreeting = TemplateProcessor::process($greeting, $context);
        $processedBody = TemplateProcessor::process($body, $context);
        $processedWarning = TemplateProcessor::process($warning, $context);
        $processedSalutation = TemplateProcessor::process($salutation, $context);
        
        $mail_message = (new MailMessage)
            ->subject($processedSubject)
            ->greeting($processedGreeting)
            ->line($processedBody)
            ->line($processedWarning)
            ->salutation($processedSalutation);

        // Attach signature if present
        $signature = $this->voucher->inputs
            ->first(fn(InputData $input) => $input->name === 'signature')
            ?->value;

        if ($signature && str_starts_with($signature, 'data:image/')) {
            // Extract the actual base64 data
            [, $encodedImage] = explode(',', $signature, 2);

            // Determine mime and file extension
            preg_match('/^data:image\/(\w+);base64/', $signature, $matches);
            $extension = $matches[1] ?? 'png'; // fallback to png
            $mime = "image/{$extension}";

            $mail_message->attachData(
                base64_decode($encodedImage),
                "signature.{$extension}",
                ['mime' => $mime]
            );
        }

        return $mail_message;
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);
        
        // Choose template based on whether address is available
        $templateKey = $context['formatted_address']
            ? 'notifications.voucher_redeemed.sms.message_with_address'
            : 'notifications.voucher_redeemed.sms.message';
        
        $template = __($templateKey);
        $message = TemplateProcessor::process($template, $context);

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toWebhook(object $notifiable): array
    {
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);

        $payload = [
            'event' => 'voucher.redeemed',
            'voucher' => [
                'code' => $context['code'],
                'amount' => $context['amount'],
                'currency' => $context['currency'],
                'redeemed_at' => $context['redeemed_at'],
            ],
            'redeemer' => [
                'mobile' => $context['mobile'],
                'address' => $context['formatted_address'],
            ],
        ];

        // Add signature if present
        if (isset($context['signature'])) {
            $payload['redeemer']['signature'] = $context['signature'];
        }

        // Get webhook URL from notifiable routes
        $webhookUrl = is_array($notifiable) ? ($notifiable['webhook'] ?? null) : null;

        return [
            'url' => $webhookUrl,
            'payload' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Redeem-X/1.0',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);
        
        return [
            'code' => $context['code'],
            'mobile' => $context['mobile'],
            'amount' => $context['amount'],
            'currency' => $context['currency'],
        ];
    }
}
