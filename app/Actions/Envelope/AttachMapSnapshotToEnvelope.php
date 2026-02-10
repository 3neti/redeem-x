<?php

declare(strict_types=1);

namespace App\Actions\Envelope;

use App\Events\RemoteImageAttached;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate a static map image from coordinates and attach to envelope.
 *
 * Uses OpenStreetMap's static map service to generate the image.
 */
class AttachMapSnapshotToEnvelope
{
    use AsAction;

    public string $jobQueue = 'high';

    public string $commandSignature = 'envelope:attach-map-snapshot
                            {voucher : Voucher code}
                            {--zoom=15 : Map zoom level (1-19)}';

    public string $commandDescription = 'Generate and attach a map snapshot from location coordinates';

    protected const DOC_TYPE = 'MAP_SNAPSHOT';

    protected const DEFAULT_ZOOM = 16;

    protected const MAP_WIDTH = 600;

    protected const MAP_HEIGHT = 300;

    public function __construct(
        protected EnvelopeService $envelopeService
    ) {}

    /**
     * Get Mapbox token from config.
     */
    protected function getMapboxToken(): ?string
    {
        $token = config('services.mapbox.token') ?? env('MAPBOX_TOKEN') ?? env('VITE_MAPBOX_TOKEN');

        // Ignore placeholder values
        if ($token && $token !== 'your_actual_token_here') {
            return $token;
        }

        return null;
    }

    /**
     * Generate and attach map snapshot from envelope's location payload.
     */
    public function handle(mixed $voucher, int $zoom = self::DEFAULT_ZOOM): ?EnvelopeAttachment
    {
        $envelope = $this->resolveEnvelope($voucher);

        if (! $envelope) {
            Log::warning('[AttachMapSnapshotToEnvelope] Could not resolve envelope', [
                'voucher' => is_string($voucher) ? $voucher : get_class($voucher),
            ]);

            return null;
        }

        $payload = $envelope->payload ?? [];
        $latitude = Arr::get($payload, 'location.latitude');
        $longitude = Arr::get($payload, 'location.longitude');

        if (! $latitude || ! $longitude) {
            Log::debug('[AttachMapSnapshotToEnvelope] No coordinates in payload', [
                'envelope_id' => $envelope->id,
            ]);

            return null;
        }

        Log::info('[AttachMapSnapshotToEnvelope] Generating map snapshot', [
            'envelope_id' => $envelope->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'zoom' => $zoom,
        ]);

        // Check if already attached
        $existingAttachment = $envelope->attachments()
            ->where('doc_type', self::DOC_TYPE)
            ->where('review_status', '!=', 'rejected')
            ->first();

        if ($existingAttachment) {
            Log::info('[AttachMapSnapshotToEnvelope] Skipping - already exists', [
                'envelope_id' => $envelope->id,
                'attachment_id' => $existingAttachment->id,
            ]);

            return null;
        }

        // Store original status
        $originalStatus = $envelope->status;
        $statusChanged = false;

        try {
            // Generate static map URL (OpenStreetMap)
            $mapUrl = $this->buildStaticMapUrl($latitude, $longitude, $zoom);

            // Download map image
            $response = Http::timeout(30)
                ->retry(3, 200)
                ->get($mapUrl);

            if (! $response->successful()) {
                Log::error('[AttachMapSnapshotToEnvelope] Failed to download map', [
                    'envelope_id' => $envelope->id,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // Create temp file
            $filename = 'map_snapshot_'.now()->format('YmdHis').'.png';
            $tempPath = sys_get_temp_dir().'/'.uniqid('map_', true).'_'.$filename;
            file_put_contents($tempPath, $response->body());

            // Create UploadedFile
            $mimeType = $response->header('Content-Type') ?? 'image/png';
            $file = new UploadedFile(
                path: $tempPath,
                originalName: $filename,
                mimeType: $mimeType,
                error: UPLOAD_ERR_OK,
                test: true
            );

            // Temporarily set envelope to editable state if needed
            if (! in_array($originalStatus, [EnvelopeStatus::DRAFT, EnvelopeStatus::IN_PROGRESS])) {
                $envelope->status = EnvelopeStatus::IN_PROGRESS;
                $envelope->saveQuietly();
                $statusChanged = true;

                Log::info('[AttachMapSnapshotToEnvelope] Temporarily set envelope to editable', [
                    'envelope_id' => $envelope->id,
                    'original_status' => $originalStatus->value,
                ]);
            }

            // Upload attachment
            $attachment = $this->envelopeService->uploadAttachment(
                envelope: $envelope,
                docType: self::DOC_TYPE,
                file: $file
            );

            // Update metadata
            if ($attachment) {
                $attachment->update([
                    'metadata' => array_merge($attachment->metadata ?? [], [
                        'source' => 'generated_map',
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'zoom' => $zoom,
                        'generated_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            // Clean up temp file
            @unlink($tempPath);

            Log::info('[AttachMapSnapshotToEnvelope] Map snapshot attached', [
                'envelope_id' => $envelope->id,
                'attachment_id' => $attachment->id,
                'size' => $attachment->size,
            ]);

            // Dispatch event
            event(new RemoteImageAttached($envelope, $attachment, $mapUrl, self::DOC_TYPE));

            return $attachment;

        } catch (\Throwable $e) {
            Log::error('[AttachMapSnapshotToEnvelope] Exception', [
                'envelope_id' => $envelope->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            // Always restore original status
            if ($statusChanged) {
                $envelope->status = $originalStatus;
                $envelope->saveQuietly();

                Log::info('[AttachMapSnapshotToEnvelope] Restored envelope status', [
                    'envelope_id' => $envelope->id,
                    'status' => $originalStatus->value,
                ]);
            }
        }
    }

    /**
     * Artisan command for testing.
     */
    public function asCommand(Command $command): int
    {
        $voucherCode = $command->argument('voucher');
        $zoom = (int) $command->option('zoom');

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            $command->error("Voucher not found: {$voucherCode}");

            return Command::FAILURE;
        }

        if (! $voucher->envelope) {
            $command->error("Voucher {$voucherCode} has no envelope");

            return Command::FAILURE;
        }

        $payload = $voucher->envelope->payload ?? [];
        $lat = Arr::get($payload, 'location.latitude');
        $lng = Arr::get($payload, 'location.longitude');

        $command->info("Processing voucher {$voucherCode}");
        $command->line("  Coordinates: {$lat}, {$lng}");
        $command->line("  Zoom: {$zoom}");

        if (! $lat || ! $lng) {
            $command->warn('No location coordinates in payload');

            return Command::SUCCESS;
        }

        $attachment = $this->handle($voucher, $zoom);

        if ($attachment) {
            $command->info('✓ Map snapshot attached');
            $command->line("  Attachment ID: {$attachment->id}");
            $command->line("  Size: {$attachment->size} bytes");
        } else {
            $command->warn('Map was not attached (may already exist or generation failed)');
        }

        return Command::SUCCESS;
    }

    /**
     * Build static map URL - prefers Mapbox, falls back to OpenStreetMap.
     */
    protected function buildStaticMapUrl(float $latitude, float $longitude, int $zoom): string
    {
        $mapboxToken = $this->getMapboxToken();

        if ($mapboxToken) {
            // Mapbox Static API (same as frontend)
            // Format: https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+ff0000(lng,lat)/lng,lat,zoom,bearing/WxH@2x?access_token=TOKEN
            return sprintf(
                'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+ff0000(%s,%s)/%s,%s,%d,0/%dx%d@2x?access_token=%s',
                $longitude,
                $latitude,
                $longitude,
                $latitude,
                $zoom,
                self::MAP_WIDTH,
                self::MAP_HEIGHT,
                $mapboxToken
            );
        }

        // Fallback: OpenStreetMap static map service
        return sprintf(
            'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=%d&size=%dx%d&markers=%s,%s,red',
            $latitude,
            $longitude,
            $zoom,
            self::MAP_WIDTH,
            self::MAP_HEIGHT,
            $latitude,
            $longitude
        );
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
}
