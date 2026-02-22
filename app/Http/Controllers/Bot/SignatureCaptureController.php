<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for Telegram Mini App signature capture.
 *
 * This controller serves the Mini App page for signature capture
 * and handles the API endpoint for storing captured signatures.
 */
class SignatureCaptureController extends Controller
{
    /**
     * Cache key prefix for storing signatures.
     */
    protected const CACHE_PREFIX = 'bot:signature:';

    /**
     * Cache TTL in seconds (10 minutes).
     */
    protected const CACHE_TTL = 600;

    /**
     * Display the signature capture Mini App page.
     *
     * This page is opened inside Telegram's WebView when the user
     * taps the "Sign" button in the bot.
     */
    public function show(Request $request): Response
    {
        $chatId = $request->query('chat_id', '');

        return Inertia::render('Bot/SignatureCapture', [
            'chatId' => $chatId,
            'uploadUrl' => url('/api/bot/signature-upload'),
        ]);
    }

    /**
     * Store a captured signature in cache.
     *
     * The signature is stored temporarily and will be retrieved
     * by the bot flow when processing the redemption.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => 'required|string',
            'signature_base64' => 'required|string',
        ]);

        $chatId = $validated['chat_id'];
        $signatureBase64 = $validated['signature_base64'];

        // Validate base64 format
        if (! preg_match('/^data:image\/[a-z]+;base64,/', $signatureBase64)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image format',
            ], 422);
        }

        // Store in cache
        $cacheKey = self::CACHE_PREFIX.$chatId;
        Cache::put($cacheKey, $signatureBase64, self::CACHE_TTL);

        // Verify it was actually stored
        $verified = Cache::has($cacheKey);

        Log::info('[SignatureCaptureController] Signature stored in cache', [
            'chat_id' => $chatId,
            'cache_key' => $cacheKey,
            'size' => strlen($signatureBase64),
            'verified' => $verified,
            'cache_driver' => config('cache.default'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signature uploaded successfully',
        ]);
    }

    /**
     * Get a cached signature for a chat.
     *
     * This is used internally by the bot flow to retrieve
     * the signature after the Mini App closes.
     */
    public static function getCachedSignature(string $chatId): ?string
    {
        $cacheKey = self::CACHE_PREFIX.$chatId;

        return Cache::get($cacheKey);
    }

    /**
     * Clear a cached signature for a chat.
     *
     * Called after the signature has been successfully processed.
     */
    public static function clearCachedSignature(string $chatId): void
    {
        $cacheKey = self::CACHE_PREFIX.$chatId;
        Cache::forget($cacheKey);
    }
}
