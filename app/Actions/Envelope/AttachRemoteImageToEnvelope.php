<?php

declare(strict_types=1);

namespace App\Actions\Envelope;

use App\Events\RemoteImageAttached;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Download a remote image and attach it to an envelope.
 *
 * This action consolidates:
 * - Direct invocation via handle()
 * - Async job dispatch via asJob() on 'high' queue
 * - Artisan command via asCommand()
 */
class AttachRemoteImageToEnvelope
{
    use AsAction;

    public string $jobQueue = 'high';

    public string $commandSignature = 'envelope:attach-remote-image
                            {envelope_id : The envelope ID}
                            {url : The image URL to download}
                            {doc_type : Document type (e.g., ID_CARD, SELFIE)}
                            {--filename= : Optional filename for the attachment}';

    public string $commandDescription = 'Download a remote image and attach it to an envelope';

    public function __construct(
        protected EnvelopeService $envelopeService
    ) {}

    /**
     * Core logic - download image and attach to envelope.
     */
    public function handle(
        Envelope $envelope,
        string $url,
        string $docType,
        ?string $filename = null
    ): ?EnvelopeAttachment {
        Log::info('[AttachRemoteImageToEnvelope] Starting download', [
            'envelope_id' => $envelope->id,
            'doc_type' => $docType,
            'url_length' => strlen($url),
        ]);

        // Check if envelope already has this document type attached
        $existingAttachment = $envelope->attachments()
            ->where('doc_type', $docType)
            ->where('review_status', '!=', 'rejected')
            ->first();

        if ($existingAttachment) {
            Log::info('[AttachRemoteImageToEnvelope] Skipping - attachment already exists', [
                'envelope_id' => $envelope->id,
                'doc_type' => $docType,
                'existing_attachment_id' => $existingAttachment->id,
            ]);

            return null;
        }

        // Store original status to restore after attachment
        $originalStatus = $envelope->status;
        $statusChanged = false;

        try {
            // Download image with timeout and retry
            $response = Http::timeout(30)
                ->retry(3, 200)
                ->get($url);

            if (! $response->successful()) {
                Log::error('[AttachRemoteImageToEnvelope] Failed to download image', [
                    'envelope_id' => $envelope->id,
                    'doc_type' => $docType,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // Determine filename and mime type
            $filename = $filename ?? $this->generateFilename($docType);
            $mimeType = $response->header('Content-Type') ?? 'image/jpeg';

            // Create temporary file
            $tempPath = sys_get_temp_dir().'/'.uniqid('remote_image_', true).'_'.$filename;
            file_put_contents($tempPath, $response->body());

            // Create UploadedFile
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
                $envelope->saveQuietly(); // Avoid triggering observers/events
                $statusChanged = true;

                Log::info('[AttachRemoteImageToEnvelope] Temporarily set envelope to editable', [
                    'envelope_id' => $envelope->id,
                    'original_status' => $originalStatus->value,
                ]);
            }

            // Upload attachment
            $attachment = $this->envelopeService->uploadAttachment(
                envelope: $envelope,
                docType: $docType,
                file: $file
            );

            // Update metadata separately if needed
            if ($attachment) {
                $attachment->update([
                    'metadata' => array_merge($attachment->metadata ?? [], [
                        'source' => 'remote_url',
                        'original_url' => $url,
                        'downloaded_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            // Clean up temp file
            @unlink($tempPath);

            Log::info('[AttachRemoteImageToEnvelope] Image attached successfully', [
                'envelope_id' => $envelope->id,
                'doc_type' => $docType,
                'attachment_id' => $attachment->id,
                'size' => $attachment->size,
            ]);

            // Dispatch event
            event(new RemoteImageAttached($envelope, $attachment, $url, $docType));

            return $attachment;

        } catch (\Throwable $e) {
            Log::error('[AttachRemoteImageToEnvelope] Exception while attaching image', [
                'envelope_id' => $envelope->id,
                'doc_type' => $docType,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            // Always restore original status
            if ($statusChanged) {
                $envelope->status = $originalStatus;
                $envelope->saveQuietly();

                Log::info('[AttachRemoteImageToEnvelope] Restored envelope status', [
                    'envelope_id' => $envelope->id,
                    'status' => $originalStatus->value,
                ]);
            }
        }
    }

    /**
     * Job execution - called when dispatched asynchronously.
     */
    public function asJob(
        Envelope $envelope,
        string $url,
        string $docType,
        ?string $filename = null
    ): void {
        Log::info('[AttachRemoteImageToEnvelope] Job started', [
            'envelope_id' => $envelope->id,
            'doc_type' => $docType,
        ]);

        $this->handle($envelope, $url, $docType, $filename);
    }

    /**
     * Artisan command - for testing and manual operations.
     */
    public function asCommand(Command $command): int
    {
        $envelopeId = $command->argument('envelope_id');
        $url = $command->argument('url');
        $docType = $command->argument('doc_type');
        $filename = $command->option('filename');

        $envelope = Envelope::find($envelopeId);
        if (! $envelope) {
            $command->error("Envelope not found: {$envelopeId}");

            return Command::FAILURE;
        }

        $command->info("Downloading image for envelope {$envelope->reference_code}...");
        $command->line("  Doc Type: {$docType}");
        $command->line('  URL: '.substr($url, 0, 80).'...');

        $attachment = $this->handle($envelope, $url, $docType, $filename);

        if ($attachment) {
            $command->info('✓ Image attached successfully');
            $command->line("  Attachment ID: {$attachment->id}");
            $command->line("  Filename: {$attachment->original_filename}");
            $command->line("  Size: {$attachment->size} bytes");

            return Command::SUCCESS;
        }

        $command->warn('Image was not attached (may already exist or download failed)');

        return Command::SUCCESS;
    }

    /**
     * Generate filename based on document type.
     */
    protected function generateFilename(string $docType): string
    {
        $timestamp = now()->format('YmdHis');

        return strtolower($docType)."_{$timestamp}.jpg";
    }
}
