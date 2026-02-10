<?php

declare(strict_types=1);

namespace App\Actions\Envelope;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Events\PayloadUpdated;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Attach KYC images (ID card, selfie) to an envelope.
 *
 * Extracts KYC image URLs from the payload and dispatches jobs to
 * download and attach them before the S3 URLs expire (~15 minutes).
 *
 * Can be invoked via:
 * - Event listener (PayloadUpdated)
 * - Direct call with voucher
 * - Artisan command for testing
 */
class AttachKYCImagesToEnvelope
{
    use AsAction;

    public string $commandSignature = 'envelope:attach-kyc-images
                            {voucher : Voucher code}
                            {--sync : Run synchronously instead of dispatching jobs}';

    public string $commandDescription = 'Attach KYC images (ID card, selfie) to envelope from payload URLs';

    /**
     * KYC URL to document type mapping.
     */
    protected const KYC_URL_MAPPINGS = [
        'kyc.id_card_full_url' => 'ID_CARD',
        'kyc.selfie_url' => 'SELFIE',
    ];

    /**
     * Core logic - extract KYC URLs and dispatch attachment jobs.
     *
     * @param  mixed  $voucher  Voucher model, voucher code string, or Envelope
     * @param  array|null  $payload  Optional payload override (uses envelope payload if null)
     * @param  bool  $sync  Run synchronously instead of dispatching jobs
     * @return int Number of attachments dispatched/processed
     */
    public function handle(mixed $voucher, ?array $payload = null, bool $sync = false): int
    {
        $envelope = $this->resolveEnvelope($voucher);

        if (! $envelope) {
            Log::warning('[AttachKYCImagesToEnvelope] Could not resolve envelope', [
                'voucher' => is_string($voucher) ? $voucher : get_class($voucher),
            ]);

            return 0;
        }

        $payload = $payload ?? $envelope->payload ?? [];

        Log::info('[AttachKYCImagesToEnvelope] Checking for KYC images', [
            'envelope_id' => $envelope->id,
            'reference_code' => $envelope->reference_code,
            'sync' => $sync,
        ]);

        $processed = 0;

        foreach (self::KYC_URL_MAPPINGS as $payloadPath => $docType) {
            $url = Arr::get($payload, $payloadPath);

            if (empty($url) || ! is_string($url)) {
                continue;
            }

            // Validate URL looks like an S3/image URL
            if (! $this->isValidImageUrl($url)) {
                Log::warning('[AttachKYCImagesToEnvelope] Invalid URL format', [
                    'envelope_id' => $envelope->id,
                    'payload_path' => $payloadPath,
                    'url_preview' => substr($url, 0, 50),
                ]);

                continue;
            }

            Log::info('[AttachKYCImagesToEnvelope] Processing image', [
                'envelope_id' => $envelope->id,
                'doc_type' => $docType,
                'payload_path' => $payloadPath,
                'sync' => $sync,
            ]);

            if ($sync) {
                // Run synchronously
                AttachRemoteImageToEnvelope::run($envelope, $url, $docType);
            } else {
                // Dispatch job on high queue
                AttachRemoteImageToEnvelope::dispatch($envelope, $url, $docType);
            }

            $processed++;
        }

        Log::info('[AttachKYCImagesToEnvelope] Finished', [
            'envelope_id' => $envelope->id,
            'processed' => $processed,
        ]);

        return $processed;
    }

    /**
     * Event listener hook.
     */
    public function asListener(PayloadUpdated $event): void
    {
        $this->handle($event->envelope, $event->patch);
    }

    /**
     * Artisan command for testing.
     */
    public function asCommand(Command $command): int
    {
        $voucherCode = $command->argument('voucher');
        $sync = $command->option('sync');

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            $command->error("Voucher not found: {$voucherCode}");

            return Command::FAILURE;
        }

        if (! $voucher->envelope) {
            $command->error("Voucher {$voucherCode} has no envelope");

            return Command::FAILURE;
        }

        $command->info("Processing voucher {$voucherCode} (envelope: {$voucher->envelope->reference_code})");
        $command->line('  Status: '.$voucher->envelope->status->value);

        $payload = $voucher->envelope->payload;
        $idCardUrl = Arr::get($payload, 'kyc.id_card_full_url');
        $selfieUrl = Arr::get($payload, 'kyc.selfie_url');

        $command->line('  ID Card URL: '.($idCardUrl ? 'Present ('.strlen($idCardUrl).' chars)' : 'Not found'));
        $command->line('  Selfie URL: '.($selfieUrl ? 'Present ('.strlen($selfieUrl).' chars)' : 'Not found'));

        if (! $idCardUrl && ! $selfieUrl) {
            $command->warn('No KYC image URLs found in payload');

            return Command::SUCCESS;
        }

        $processed = $this->handle($voucher, null, $sync);

        if ($sync) {
            $command->info("✓ Processed {$processed} image(s) synchronously");
            $command->line('  Attachments: '.$voucher->envelope->fresh()->attachments()->count());
        } else {
            $command->info("✓ Dispatched {$processed} job(s) to high queue");
            $command->line('  Run: php artisan queue:work --queue=high --once');
        }

        return Command::SUCCESS;
    }

    /**
     * Resolve envelope from various input types.
     */
    protected function resolveEnvelope(mixed $voucher): ?Envelope
    {
        if ($voucher instanceof Envelope) {
            return $voucher;
        }

        if ($voucher instanceof Voucher) {
            return $voucher->envelope;
        }

        if (is_string($voucher)) {
            $voucherModel = Voucher::where('code', $voucher)->first();

            return $voucherModel?->envelope;
        }

        return null;
    }

    /**
     * Basic validation that the URL looks like an image URL.
     */
    protected function isValidImageUrl(string $url): bool
    {
        // Must start with https
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        // Check for common image URL patterns
        // S3 URLs, or URLs with image extensions
        $hasS3Pattern = str_contains($url, 's3.') || str_contains($url, 'amazonaws.com');
        $hasImageExtension = preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $url);
        $hasImageContentType = str_contains(strtolower($url), 'image');

        return $hasS3Pattern || $hasImageExtension || $hasImageContentType;
    }
}
