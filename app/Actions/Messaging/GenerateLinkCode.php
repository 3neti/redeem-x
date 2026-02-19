<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate a one-time code for linking a messaging account.
 *
 * The code is stored in cache for 15 minutes.
 * User sends "/link CODE" to the bot to complete linking.
 */
class GenerateLinkCode
{
    use AsAction;

    public function handle(User $user, string $platform = 'telegram'): array
    {
        // Generate a 6-character alphanumeric code
        $code = strtoupper(Str::random(6));

        // Store in cache for 15 minutes
        $cacheKey = "link_code:{$code}";
        Cache::put($cacheKey, $user->id, now()->addMinutes(15));

        return [
            'code' => $code,
            'platform' => $platform,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'instructions' => "Send /link {$code} to the bot",
        ];
    }
}
