<?php

namespace App\Support;

class GatewayErrorMapper
{
    /**
     * Map gateway error messages to user-friendly messages
     */
    public static function toUserFriendly(string $gatewayMessage): string
    {
        $lowercaseMessage = strtolower($gatewayMessage);

        // Timeout/Network errors
        if (str_contains($lowercaseMessage, 'timeout') ||
            str_contains($lowercaseMessage, 'timed out')) {
            return 'Connection timeout. The bank is taking too long to respond. Please try again.';
        }

        if (str_contains($lowercaseMessage, 'network') ||
            str_contains($lowercaseMessage, 'connection')) {
            return 'Network error. Please check your connection and try again.';
        }

        // Account/Validation errors
        if (str_contains($lowercaseMessage, 'insufficient funds') ||
            str_contains($lowercaseMessage, 'insufficient balance')) {
            return 'Insufficient balance in the disbursement account. Please contact support.';
        }

        if (str_contains($lowercaseMessage, 'invalid account') ||
            str_contains($lowercaseMessage, 'account not found')) {
            return 'Bank account information is invalid. Please verify and try again.';
        }

        if (str_contains($lowercaseMessage, 'invalid bank code') ||
            str_contains($lowercaseMessage, 'bank code')) {
            return 'Bank is not supported or invalid. Please check your bank selection.';
        }

        // Service errors
        if (str_contains($lowercaseMessage, 'service unavailable') ||
            str_contains($lowercaseMessage, 'temporarily unavailable')) {
            return 'Payment service is temporarily unavailable. Please try again later.';
        }

        if (str_contains($lowercaseMessage, 'maintenance')) {
            return 'Payment service is under maintenance. Please try again later.';
        }

        // Authentication/Authorization errors
        if (str_contains($lowercaseMessage, 'unauthorized') ||
            str_contains($lowercaseMessage, 'authentication failed')) {
            return 'Payment gateway authentication failed. Please contact support.';
        }

        if (str_contains($lowercaseMessage, 'forbidden') ||
            str_contains($lowercaseMessage, 'not authorized')) {
            return 'Operation not authorized. Please contact support.';
        }

        // Transaction errors
        if (str_contains($lowercaseMessage, 'duplicate') ||
            str_contains($lowercaseMessage, 'already processed')) {
            return 'This transaction has already been processed.';
        }

        if (str_contains($lowercaseMessage, 'limit exceeded') ||
            str_contains($lowercaseMessage, 'maximum limit')) {
            return 'Transaction limit exceeded. Please contact support.';
        }

        // Generic "failed to process" error
        if (str_contains($lowercaseMessage, 'failed to process')) {
            return 'Unable to process payment at this time. This may be due to temporary service issues or configuration problems. Please try again or contact support if the issue persists.';
        }

        // Default fallback - return original with context
        return 'Payment processing failed: '.$gatewayMessage.'. Please try again or contact support.';
    }
}
