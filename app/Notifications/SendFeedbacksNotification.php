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
use Illuminate\Support\Facades\Log;

class SendFeedbacksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Toggle debug logging: set to true to enable, false to disable
    private const DEBUG_ENABLED = false;

    protected VoucherData $voucher;

    /**
     * Log debug message if debugging is enabled
     */
    private function debug(string $message, array $context = []): void
    {
        if (self::DEBUG_ENABLED) {
            Log::debug("[SendFeedbacksNotification] {$message}", $context);
        }
    }

    public function __construct(string $voucherCode)
    {
        $this->debug("Constructing notification for voucher: {$voucherCode}");
        $model = Voucher::with('inputs')->where('code', $voucherCode)->firstOrFail();
        $this->voucher = VoucherData::fromModel($model);
        $this->debug("Voucher loaded with " . $this->voucher->inputs->count() . " inputs");
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark', 'database'];
        // TODO: Re-enable webhook channel once properly tested
        // return ['mail', 'engage_spark', WebhookChannel::class, 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->debug("Building email notification");
        
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

        $this->debug("Signature input", ['found' => $signature !== null, 'is_data_url' => $signature && str_starts_with($signature, 'data:image/')]);

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
            $this->debug("Attached signature", ['extension' => $extension, 'mime' => $mime]);
        }
        
        // Attach selfie if present
        $selfie = $this->voucher->inputs
            ->first(fn(InputData $input) => $input->name === 'selfie')
            ?->value;

        $this->debug("Selfie input", ['found' => $selfie !== null, 'is_data_url' => $selfie && str_starts_with($selfie, 'data:image/')]);

        if ($selfie && str_starts_with($selfie, 'data:image/')) {
            // Extract the actual base64 data
            [, $encodedImage] = explode(',', $selfie, 2);

            // Determine mime and file extension
            preg_match('/^data:image\/(\w+);base64/', $selfie, $matches);
            $extension = $matches[1] ?? 'png'; // fallback to png
            $mime = "image/{$extension}";

            $mail_message->attachData(
                base64_decode($encodedImage),
                "selfie.{$extension}",
                ['mime' => $mime]
            );
            $this->debug("Attached selfie", ['extension' => $extension, 'mime' => $mime]);
        }
        
        // Attach location snapshot if present
        $locationInput = $this->voucher->inputs
            ->first(fn(InputData $input) => $input->name === 'location')
            ?->value;
        
        $this->debug("Location input", ['found' => $locationInput !== null]);
            
        if ($locationInput) {
            try {
                $locationData = json_decode($locationInput, true);
                $snapshot = $locationData['snapshot'] ?? null;
                
                $this->debug("Location data parsed", [
                    'has_snapshot' => $snapshot !== null,
                    'snapshot_length' => $snapshot ? strlen($snapshot) : 0,
                    'is_data_url' => $snapshot && str_starts_with($snapshot, 'data:image/'),
                    'location_keys' => array_keys($locationData)
                ]);
                
                if ($snapshot && str_starts_with($snapshot, 'data:image/')) {
                    // Extract the actual base64 data
                    [, $encodedImage] = explode(',', $snapshot, 2);

                    // Determine mime and file extension
                    preg_match('/^data:image\/(\w+);base64/', $snapshot, $matches);
                    $extension = $matches[1] ?? 'png'; // fallback to png
                    $mime = "image/{$extension}";

                    $mail_message->attachData(
                        base64_decode($encodedImage),
                        "location-map.{$extension}",
                        ['mime' => $mime]
                    );
                    $this->debug("Attached location snapshot", ['extension' => $extension, 'mime' => $mime]);
                }
            } catch (\Exception $e) {
                $this->debug("Failed to attach location snapshot", ['error' => $e->getMessage()]);
            }
        }
        
        $this->debug("Email notification built successfully");

        return $mail_message;
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $this->debug("Building SMS notification");
        
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);
        
        // Choose template based on whether address is available
        $templateKey = $context['formatted_address']
            ? 'notifications.voucher_redeemed.sms.message_with_address'
            : 'notifications.voucher_redeemed.sms.message';
        
        $template = __($templateKey);
        $message = TemplateProcessor::process($template, $context);
        
        $this->debug("SMS notification built", ['template_key' => $templateKey, 'message_length' => strlen($message)]);

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toWebhook(mixed $notifiable): array
    {
        // Build template context from voucher data
        $context = VoucherTemplateContextBuilder::build($this->voucher);

        // Load full voucher model for trait methods
        $voucherModel = Voucher::where('code', $this->voucher->code)->firstOrFail();

        $payload = [
            'event' => 'voucher.redeemed',
            'voucher' => [
                'code' => $context['code'],
                'amount' => $context['amount'],
                'currency' => $context['currency'],
                'formatted_amount' => $context['formatted_amount'],
                'redeemed_at' => $context['redeemed_at'],
                'status' => $context['status'],
            ],
            'redeemer' => [
                'mobile' => $context['mobile'],
                'name' => $context['contact_name'],
                'address' => $context['formatted_address'],
            ],
        ];

        // Add external metadata (for QuestPay integration)
        if ($voucherModel->external_metadata) {
            $external = $voucherModel->external_metadata;
            $payload['external'] = [
                'id' => $external->external_id,
                'type' => $external->external_type,
                'reference_id' => $external->reference_id,
                'user_id' => $external->user_id,
                'custom' => $external->custom,
            ];
        }

        // Add timing data
        if ($voucherModel->timing) {
            $timing = $voucherModel->timing;
            $payload['timing'] = [
                'clicked_at' => $timing->clicked_at,
                'started_at' => $timing->started_at,
                'submitted_at' => $timing->submitted_at,
                'duration_seconds' => $timing->duration_seconds,
            ];
        }

        // Add validation results
        $results = $voucherModel->getValidationResults();
        if ($results) {
            $payload['validation'] = [
                'passed' => $results->passed,
                'blocked' => $results->blocked,
            ];

            // Add location validation details
            if ($results->hasLocationResults()) {
                $payload['validation']['location'] = [
                    'validated' => $results->location->validated,
                    'distance_meters' => $results->location->distance_meters,
                    'should_block' => $results->location->should_block,
                ];
            }

            // Add time validation details
            if ($results->hasTimeResults()) {
                $payload['validation']['time'] = [
                    'within_window' => $results->time->within_window,
                    'within_duration' => $results->time->within_duration,
                    'duration_seconds' => $results->time->duration_seconds,
                    'should_block' => $results->time->should_block,
                ];
            }
        }

        // Add input fields with special handling for photos/location
        $payload['inputs'] = [];
        foreach ($this->voucher->inputs as $input) {
            $name = $input->name;
            $value = $input->value;

            // Handle location field
            if ($name === 'location') {
                try {
                    $locationData = json_decode($value, true);
                    $payload['inputs']['location'] = [
                        'latitude' => $locationData['latitude'] ?? null,
                        'longitude' => $locationData['longitude'] ?? null,
                        'accuracy' => $locationData['accuracy'] ?? null,
                        'altitude' => $locationData['altitude'] ?? null,
                        'formatted_address' => $locationData['address']['formatted'] ?? null,
                        'has_snapshot' => isset($locationData['snapshot']),
                    ];
                } catch (\Exception $e) {
                    $payload['inputs']['location'] = $value;
                }
            }
            // Handle signature/selfie - indicate presence but don't send full data URL
            elseif (in_array($name, ['signature', 'selfie'])) {
                $payload['inputs'][$name] = [
                    'present' => !empty($value),
                    'size_bytes' => strlen($value),
                ];
            }
            // Regular fields
            else {
                $payload['inputs'][$name] = $value;
            }
        }

        // Add metadata section for additional voucher info
        $payload['metadata'] = [
            'created_at' => $context['created_at'],
            'owner' => [
                'name' => $context['owner_name'],
                'email' => $context['owner_email'],
            ],
        ];

        // Get webhook URL from notifiable routes
        $webhookUrl = is_array($notifiable) ? ($notifiable['webhook'] ?? null) : null;

        return [
            'url' => $webhookUrl,
            'payload' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Redeem-X/1.0',
                'X-Webhook-Event' => 'voucher.redeemed',
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
