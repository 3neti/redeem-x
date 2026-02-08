<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validates a voucher code for redemption eligibility.
 *
 * Checks:
 * - Voucher exists
 * - Voucher is not expired
 * - Voucher is not already redeemed
 */
class ValidateVoucherCode
{
    use AsAction;

    /**
     * Validate a voucher code.
     *
     * @param  string  $code  Voucher code to validate
     * @return array{valid: bool, voucher?: Voucher, error?: string}
     */
    public function handle(string $code): array
    {
        // Normalize code (uppercase, trim)
        $normalizedCode = strtoupper(trim($code));

        Log::info('[ValidateVoucherCode] Validating voucher', [
            'code' => $normalizedCode,
        ]);

        // Check if voucher exists
        $voucher = Voucher::where('code', $normalizedCode)->first();

        if (! $voucher) {
            Log::warning('[ValidateVoucherCode] Voucher not found', [
                'code' => $normalizedCode,
            ]);

            return [
                'valid' => false,
                'error' => 'Voucher code not found.',
            ];
        }

        // Check if voucher is expired
        if ($voucher->isExpired()) {
            Log::warning('[ValidateVoucherCode] Voucher expired', [
                'code' => $normalizedCode,
                'expires_at' => $voucher->expires_at?->toISOString(),
            ]);

            return [
                'valid' => false,
                'voucher' => $voucher,
                'error' => 'This voucher has expired.',
            ];
        }

        // Check if voucher is already redeemed
        if ($voucher->isRedeemed()) {
            Log::warning('[ValidateVoucherCode] Voucher already redeemed', [
                'code' => $normalizedCode,
                'redeemed_at' => $voucher->redeemed_at?->toISOString(),
            ]);

            return [
                'valid' => false,
                'voucher' => $voucher,
                'error' => 'This voucher has already been redeemed.',
            ];
        }

        // Voucher is valid!
        Log::info('[ValidateVoucherCode] Voucher is valid', [
            'code' => $normalizedCode,
            'amount' => $voucher->instructions->cash->amount,
            'currency' => $voucher->instructions->cash->currency,
        ]);

        return [
            'valid' => true,
            'voucher' => $voucher,
        ];
    }

    /**
     * Validate and return voucher or throw exception.
     *
     * @param  string  $code  Voucher code
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateOrFail(string $code): Voucher
    {
        $result = $this->handle($code);

        if (! $result['valid']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => [$result['error']],
            ]);
        }

        return $result['voucher'];
    }
}
