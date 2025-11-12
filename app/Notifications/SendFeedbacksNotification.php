<?php

namespace App\Notifications;

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
        return ['mail', 'engage_spark', WebhookChannel::class, 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Get amount from instructions (always available) or cash object (if redeemed)
        $amount = $this->voucher->cash?->amount ?? $this->voucher->instructions?->cash?->amount;
        $formattedAmount = $amount ? $amount->formatTo(Number::defaultLocale()) : 'N/A';
        $formattedAddress = $this->getFormattedAddress() ?? 'somewhere';
        
        $mail_message = (new MailMessage)
            ->subject('Voucher Code Redeemed')
            ->greeting('Hello,')
            ->line("The voucher code **{$this->voucher->code}** with the amount of **{$formattedAmount}** has been successfully redeemed.")
            ->line("It was claimed by **{$this->voucher->contact?->mobile}** from {$formattedAddress}.")
            ->line('If you did not authorize this transaction, please contact support immediately.')
            ->salutation('Thank you for using our service!');

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
        // Get amount from instructions or cash object
        $amount = $this->voucher->cash?->amount ?? $this->voucher->instructions?->cash?->amount;
        $amountText = $amount ? "{$amount->getCurrency()->getCurrencyCode()} {$amount->getAmount()}" : 'N/A';
        
        $message = "Voucher {$this->voucher->code} with amount {$amountText} was redeemed by {$this->voucher->contact?->mobile}.";
        if ($formattedAddress = $this->getFormattedAddress()) {
            $message .= "\nAddress: {$formattedAddress}";
        }

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toWebhook(object $notifiable): array
    {
        // Get amount from instructions or cash object
        $amount = $this->voucher->cash?->amount ?? $this->voucher->instructions?->cash?->amount;
        $formattedAddress = $this->getFormattedAddress();

        $payload = [
            'event' => 'voucher.redeemed',
            'voucher' => [
                'code' => $this->voucher->code,
                'amount' => $amount?->getAmount()->toFloat(),
                'currency' => $amount?->getCurrency()->getCurrencyCode(),
                'redeemed_at' => $this->voucher->redeemed_at?->toIso8601String(),
            ],
            'redeemer' => [
                'mobile' => $this->voucher->contact?->mobile,
                'address' => $formattedAddress,
            ],
        ];

        // Add signature if present
        $signature = $this->voucher->inputs
            ->first(fn(InputData $input) => $input->name === 'signature')
            ?->value;

        if ($signature) {
            $payload['redeemer']['signature'] = $signature;
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
        // Get amount from instructions or cash object
        $amount = $this->voucher->cash?->amount ?? $this->voucher->instructions?->cash?->amount;
        
        return [
            'code' => $this->voucher->code,
            'mobile' => $this->voucher->contact?->mobile,
            'amount' => $amount?->getAmount()->toFloat(),
            'currency' => $amount?->getCurrency()->getCurrencyCode(),
        ];
    }

    protected function getFormattedAddress(): string|null
    {
        if ($location_json = $this->voucher->inputs->first(fn($input) => $input->name === 'location')?->value) {
            $location_array = json_decode($location_json, true);
            if ($formatted_address = Arr::get($location_array, 'address.formatted')) {
                return $formatted_address;
            }
        }

        return null;
    }
}
