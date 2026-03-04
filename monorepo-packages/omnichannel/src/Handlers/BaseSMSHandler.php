<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Models\User;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;

/**
 * Base handler for all SMS commands.
 *
 * Provides common functionality:
 * - User authentication/lookup
 * - Logging with handler name prefix
 * - Error handling and responses
 * - Notification sending
 * - Money formatting
 * - Template method pattern
 */
abstract class BaseSMSHandler implements SMSHandlerInterface
{
    /**
     * Handle an SMS message (interface implementation).
     *
     * This is a template method that handles authentication,
     * error handling, and delegates to the handle() method.
     *
     * @param  array  $values  Parsed values from the SMS message.
     * @param  string  $from  Sender's phone number.
     * @param  string  $to  Receiver's phone number.
     * @return JsonResponse The response to send back.
     */
    final public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        $this->logInfo('Processing command', ['from' => $from, 'values' => $values]);

        // Look up user
        $user = $this->getUser($from);

        // Check authentication requirement
        if (! $user && $this->requiresAuth()) {
            $this->logWarning('Unauthenticated request', ['mobile' => $from]);

            return $this->errorResponse('No account found. Send REGISTER to create one.');
        }

        // Delegate to handler implementation
        try {
            return $this->handle($user, $values, $from, $to);
        } catch (\Throwable $e) {
            $this->logError('Handler failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Command failed: '.$e->getMessage());
        }
    }

    /**
     * Handle the SMS command (to be implemented by subclasses).
     *
     * @param  User|null  $user  The authenticated user (null if requiresAuth() = false)
     * @param  array  $values  Parsed values from the SMS message
     * @param  string  $from  Sender's phone number
     * @param  string  $to  Receiver's phone number
     * @return JsonResponse The response to send back
     */
    abstract protected function handle(?User $user, array $values, string $from, string $to): JsonResponse;

    /**
     * Whether this handler requires authentication.
     *
     * Override to return false for public handlers (e.g., REGISTER).
     */
    protected function requiresAuth(): bool
    {
        return true;
    }

    /**
     * Get user by mobile number.
     *
     * Tries request()->user() first (set by auth middleware or test command),
     * then falls back to database lookup.
     *
     * @param  string  $from  Sender's mobile number
     */
    protected function getUser(string $from): ?User
    {
        // Try request user first (set by auth middleware or test command)
        $user = request()->user();
        if ($user) {
            return $user;
        }

        // Fall back to database lookup
        return $this->findUserByMobile($from);
    }

    /**
     * Find user by mobile number in database.
     *
     * @param  string  $mobile  Mobile number
     */
    protected function findUserByMobile(string $mobile): ?User
    {
        // Try exact match first
        $user = User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where('value', $mobile);
        })->first();

        if ($user) {
            return $user;
        }

        // Try with variations (with/without country code, leading zero)
        return User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where(function ($sub) use ($mobile) {
                    $sub->where('value', 'LIKE', "%{$mobile}%")
                        ->orWhere('value', 'LIKE', '%'.ltrim($mobile, '0').'%');
                });
        })->first();
    }

    /**
     * Send a notification to a user.
     *
     * @param  User  $user  The user to notify
     * @param  Notification  $notification  The notification to send
     */
    protected function sendNotification(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }

    /**
     * Format money with thousands separator.
     *
     * @param  float  $amount  Amount to format
     * @param  string  $currency  Currency code
     * @return string Formatted money string
     */
    protected function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            $money = Money::of($amount, $currency);

            return $money->formatTo('en_PH');
        } catch (\Throwable $e) {
            // Fallback formatting
            return '₱'.number_format($amount, 2);
        }
    }

    /**
     * Log info message with handler name prefix.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $prefix = '['.class_basename($this).']';
        Log::info("{$prefix} {$message}", $context);
    }

    /**
     * Log warning message with handler name prefix.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $prefix = '['.class_basename($this).']';
        Log::warning("{$prefix} {$message}", $context);
    }

    /**
     * Log error message with handler name prefix.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logError(string $message, array $context = []): void
    {
        $prefix = '['.class_basename($this).']';
        Log::error("{$prefix} {$message}", $context);
    }

    /**
     * Create an error JSON response.
     *
     * @param  string  $message  Error message
     * @param  int  $code  HTTP status code
     */
    protected function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $message], $code);
    }
}
