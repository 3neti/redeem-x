<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * VoucherMediaController
 *
 * Serves voucher input images (signature, selfie, location map) via temporary signed URLs.
 * Used by SendFeedbacksNotification to provide magic links in SMS instead of attachments.
 *
 * Security:
 * - Requires signed URL (24-hour expiry)
 * - Only serves images from voucher inputs
 * - Returns 403 if signature invalid/expired
 * - Returns 404 if voucher/media not found
 */
class VoucherMediaController extends Controller
{
    /**
     * Supported media types
     */
    private const SUPPORTED_TYPES = ['signature', 'selfie', 'location', 'map'];

    /**
     * Display the specified media from voucher inputs.
     *
     * @param  string  $code  Voucher code
     * @param  string  $type  Media type (signature|selfie|location|map)
     * @return Response|StreamedResponse
     */
    public function show(Request $request, string $code, string $type)
    {
        // Validate signature (handled by 'signed' middleware in route)
        // If we get here, signature is valid

        // Validate media type
        if (! in_array($type, self::SUPPORTED_TYPES)) {
            abort(404, "Invalid media type: {$type}");
        }

        // Load voucher with inputs
        $voucher = Voucher::with('inputs')
            ->where('code', $code)
            ->firstOrFail();

        // Find the requested input
        $input = $voucher->inputs->firstWhere('name', $type);

        if (! $input) {
            abort(404, "Media not found: {$type}");
        }

        // Extract image data
        $imageData = $this->extractImageData($input->value, $type);

        if (! $imageData) {
            abort(404, "Invalid image data for: {$type}");
        }

        // Return image response
        return response($imageData['binary'], 200, [
            'Content-Type' => $imageData['mime'],
            'Content-Disposition' => "inline; filename=\"{$type}.{$imageData['extension']}\"",
            'Cache-Control' => 'private, max-age=3600', // Cache for 1 hour
        ]);
    }

    /**
     * Extract binary image data from input value.
     *
     * @return array|null ['binary' => string, 'mime' => string, 'extension' => string]
     */
    protected function extractImageData(string $value, string $type): ?array
    {
        // Handle location type (extract snapshot from JSON)
        if ($type === 'location') {
            return $this->extractLocationSnapshot($value);
        }

        // Handle signature/selfie/map (data URL format)
        return $this->extractDataUrl($value);
    }

    /**
     * Extract image from data URL (signature, selfie).
     */
    protected function extractDataUrl(string $dataUrl): ?array
    {
        // Validate data URL format
        if (! str_starts_with($dataUrl, 'data:image/')) {
            return null;
        }

        // Extract mime type and extension
        preg_match('/^data:image\/(\w+);base64/', $dataUrl, $matches);
        if (! $matches) {
            return null;
        }

        $extension = $matches[1];
        $mime = "image/{$extension}";

        // Extract base64 data
        [, $encodedImage] = explode(',', $dataUrl, 2);
        $binary = base64_decode($encodedImage);

        if ($binary === false) {
            return null;
        }

        return [
            'binary' => $binary,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }

    /**
     * Extract location snapshot from JSON.
     */
    protected function extractLocationSnapshot(string $locationJson): ?array
    {
        try {
            $locationData = json_decode($locationJson, true);
            $snapshot = $locationData['snapshot'] ?? null;

            if (! $snapshot) {
                return null;
            }

            // Snapshot is a data URL
            return $this->extractDataUrl($snapshot);

        } catch (\Exception $e) {
            return null;
        }
    }
}
