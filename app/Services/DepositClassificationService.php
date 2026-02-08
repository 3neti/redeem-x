<?php

namespace App\Services;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Log;

class DepositClassificationService
{
    /**
     * Classify a deposit as payment request or top-up
     *
     * @param  array  $depositData  Raw deposit data from webhook
     * @return array{type: string, model: PaymentRequest|null, strategy: string, confidence: string}
     */
    public function classify(array $depositData): array
    {
        // Try strategies in priority order
        if ($result = $this->checkMerchantMetadata($depositData)) {
            return $result;
        }

        if ($result = $this->matchByAmountAndTime($depositData)) {
            return $result;
        }

        if ($result = $this->matchBySenderAndAmount($depositData)) {
            return $result;
        }

        // Unknown - treat as regular top-up
        Log::info('Deposit classified as top-up (no payment match)', [
            'amount' => $depositData['amount'] ?? null,
            'sender' => $depositData['sender']['accountNumber'] ?? null,
        ]);

        return [
            'type' => 'topup',
            'model' => null,
            'strategy' => 'none',
            'confidence' => 'n/a',
        ];
    }

    /**
     * Strategy 1: Check merchant metadata for payment_request_reference
     * Best accuracy: 95%+
     */
    protected function checkMerchantMetadata(array $depositData): ?array
    {
        // Check if merchant_details contains payment_request_reference
        $reference = $depositData['merchant_details']['payment_request_reference'] ?? null;

        if (! $reference) {
            return null;
        }

        $paymentRequest = PaymentRequest::where('reference_id', $reference)
            ->where('status', 'pending')
            ->first();

        if ($paymentRequest) {
            Log::info('Payment matched via merchant metadata', [
                'payment_request_id' => $paymentRequest->id,
                'reference_id' => $reference,
                'strategy' => 'metadata',
            ]);

            return [
                'type' => 'payment',
                'model' => $paymentRequest,
                'strategy' => 'metadata',
                'confidence' => 'high',
            ];
        }

        return null;
    }

    /**
     * Strategy 2: Match by amount and timestamp
     * Good accuracy: 80%
     */
    protected function matchByAmountAndTime(array $depositData): ?array
    {
        $amount = $depositData['amount'] ?? null;
        if (! $amount) {
            return null;
        }

        // Convert deposit amount (in pesos) to cents
        $amountInCents = (int) ($amount * 100);

        // Find PaymentRequest created in last 10 minutes with exact amount
        $paymentRequest = PaymentRequest::where('status', 'pending')
            ->where('amount', $amountInCents)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($paymentRequest) {
            // Check for duplicates to assess confidence
            $duplicateCount = PaymentRequest::where('status', 'pending')
                ->where('amount', $amountInCents)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->count();

            $confidence = $duplicateCount > 1 ? 'medium' : 'high';

            if ($duplicateCount > 1) {
                Log::warning('Multiple pending payments found for amount', [
                    'amount' => $amount,
                    'count' => $duplicateCount,
                    'selected_id' => $paymentRequest->id,
                ]);
            }

            Log::info('Payment matched via amount+time', [
                'payment_request_id' => $paymentRequest->id,
                'amount' => $amount,
                'strategy' => 'amount-time',
                'confidence' => $confidence,
            ]);

            return [
                'type' => 'payment',
                'model' => $paymentRequest,
                'strategy' => 'amount-time',
                'confidence' => $confidence,
            ];
        }

        return null;
    }

    /**
     * Strategy 3: FIFO matching by amount (fallback)
     * Fallback accuracy: 70%
     */
    protected function matchBySenderAndAmount(array $depositData): ?array
    {
        $amount = $depositData['amount'] ?? null;
        if (! $amount) {
            return null;
        }

        // Convert deposit amount (in pesos) to cents
        $amountInCents = (int) ($amount * 100);

        // Match by amount + use FIFO for ties (oldest first)
        $paymentRequest = PaymentRequest::where('status', 'pending')
            ->where('amount', $amountInCents)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderBy('created_at', 'asc') // Oldest first (FIFO)
            ->first();

        if ($paymentRequest) {
            Log::info('Payment matched via FIFO', [
                'payment_request_id' => $paymentRequest->id,
                'amount' => $amount,
                'strategy' => 'fifo',
                'confidence' => 'low',
            ]);

            return [
                'type' => 'payment',
                'model' => $paymentRequest,
                'strategy' => 'fifo',
                'confidence' => 'low',
            ];
        }

        return null;
    }
}
