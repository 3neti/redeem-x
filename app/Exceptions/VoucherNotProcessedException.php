<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherNotProcessedException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'This voucher is still being processed. Please wait a moment and try again.')
    {
        parent::__construct($message, 425); // HTTP 425 Too Early
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'VOUCHER_NOT_PROCESSED',
            'retry_after' => 3, // Suggest retry after 3 seconds
        ], 425);
    }

    /**
     * Report the exception.
     */
    public function report(): bool
    {
        // Don't report this as an error - it's expected behavior
        return false;
    }
}
