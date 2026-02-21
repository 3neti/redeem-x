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
 * Controller for Telegram Mini App selfie capture.
 *
 * This controller serves the Mini App page for selfie capture
 * and handles the API endpoint for storing captured selfies.
 */
class SelfieCaptureController extends Controller
{
    /**
     * Cache key prefix for storing selfies.
     */
    protected const CACHE_PREFIX = 'bot:selfie:';

    /**
     * Cache TTL in seconds (10 minutes).
     */
    protected const CACHE_TTL = 600;

    /**
     * Display the selfie capture Mini App page.
     *
     * This page is opened inside Telegram's WebView when the user
     * taps the "Take Selfie" button in the bot.
     */
    public function show(Request $request): Response
    {
        $chatId = $request->query('chat_id', '');

        return Inertia::render('Bot/SelfieCapture', [
            'chatId' => $chatId,
            'uploadUrl' => route('bot.selfie.store'),
        ]);
    }

    /**
     * Store a captured selfie in cache.
     *
     * The selfie is stored temporarily and will be retrieved
     * by the bot flow when processing the redemption.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => 'required|string',
            'selfie_base64' => 'required|string',
        ]);

        $chatId = $validated['chat_id'];
        $selfieBase64 = $validated['selfie_base64'];

        // Validate base64 format
        if (! preg_match('/^data:image\/[a-z]+;base64,/', $selfieBase64)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image format',
            ], 422);
        }

        // Store in cache
        $cacheKey = self::CACHE_PREFIX.$chatId;
        Cache::put($cacheKey, $selfieBase64, self::CACHE_TTL);

        Log::info('[SelfieCaptureController] Selfie stored in cache', [
            'chat_id' => $chatId,
            'size' => strlen($selfieBase64),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Selfie uploaded successfully',
        ]);
    }

    /**
     * Get a cached selfie for a chat.
     *
     * This is used internally by the bot flow to retrieve
     * the selfie after the Mini App closes.
     */
    public static function getCachedSelfie(string $chatId): ?string
    {
        $cacheKey = self::CACHE_PREFIX.$chatId;

        return Cache::get($cacheKey);
    }

    /**
     * Clear a cached selfie for a chat.
     *
     * Called after the selfie has been successfully processed.
     */
    public static function clearCachedSelfie(string $chatId): void
    {
        $cacheKey = self::CACHE_PREFIX.$chatId;
        Cache::forget($cacheKey);
    }
}
