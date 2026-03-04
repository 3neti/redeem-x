<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Handles the /link command.
 *
 * Links a messaging account to a website user account using a verification code.
 *
 * Flow:
 * 1. User gets a code from website (Settings → Linked Accounts)
 * 2. User sends "/link CODE" to bot
 * 3. Bot verifies code and links accounts
 */
class LinkHandler extends BaseMessagingHandler
{
    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        // Extract code from message (e.g., "/link ABC123" → "ABC123")
        $parts = explode(' ', trim($update->text), 2);
        $code = $parts[1] ?? null;

        if (! $code) {
            return $this->showUsage();
        }

        $code = strtoupper(trim($code));

        // Look up the linking code in cache
        $cacheKey = "link_code:{$code}";
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            return NormalizedResponse::text(
                "❌ Invalid or expired code.\n\n".
                "Please get a new code from your account settings."
            );
        }

        // Find the user
        $user = User::find($userId);

        if (! $user) {
            return NormalizedResponse::text(
                "❌ User account not found.\n\n".
                'Please contact support.'
            );
        }

        // Check if this chat is already linked to another account
        $platform = $update->platform;
        $channelName = $platform->channelName();
        $existingUser = User::findByChannel($channelName, $update->chatId);

        if ($existingUser && $existingUser->id !== $user->id) {
            return NormalizedResponse::text(
                "⚠️ This {$platform->label()} account is already linked to another user.\n\n".
                'Please unlink it first from the other account, or contact support.'
            );
        }

        // Link the account
        $user->forceSetChannel($channelName, $update->chatId);

        // Remove the used code
        Cache::forget($cacheKey);

        $this->log('info', 'Account linked', [
            'user_id' => $user->id,
            'platform' => $platform->value,
            'chat_id' => $update->chatId,
        ]);

        return NormalizedResponse::html(
            "✅ <b>Account linked successfully!</b>\n\n".
            "Your {$platform->label()} account is now connected to:\n".
            "<b>{$user->email}</b>\n\n".
            "You can now use /balance and other account features."
        );
    }

    protected function showUsage(): NormalizedResponse
    {
        return NormalizedResponse::html(
            "<b>Link Your Account</b>\n\n".
            "To link your account:\n".
            "1. Log in to your account at the website\n".
            "2. Go to Settings → Linked Accounts\n".
            "3. Click \"Link Telegram\" to get a code\n".
            "4. Send: <code>/link YOUR_CODE</code>\n\n".
            "Example: <code>/link ABC123</code>"
        );
    }
}
