<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\VoucherGenerationCharge;
use Bavix\Wallet\Models\Transaction;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\Data;

class WalletTransactionData extends Data
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $type, // 'deposit' or 'withdraw'
        public float $amount,
        public string $currency,
        public bool $confirmed,
        public int $wallet_id,
        // Enhanced metadata - deposits
        public ?string $sender_name,
        public ?string $sender_identifier,
        public ?string $payment_method,
        public ?string $deposit_type, // 'manual_topup', 'voucher_payment', 'qr_payment'
        // Voucher-specific - withdrawals
        public ?string $voucher_code,
        public ?array $disbursement, // Bank, rail, status
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromTransaction(Transaction $tx): self
    {
        $meta = $tx->meta ?? [];

        // Derive metadata based on transaction type and existing data
        if ($tx->type === 'deposit') {
            [$senderName, $senderIdentifier, $paymentMethod, $depositType] = self::deriveDepositMetadata($tx, $meta);
        } else {
            // For withdrawals, derive voucher and disbursement info
            [$voucherCode, $disbursement] = self::deriveWithdrawalMetadata($tx, $meta);
        }

        return new self(
            id: $tx->id,
            uuid: $tx->uuid,
            type: $tx->type,
            amount: abs((float) $tx->amountFloat),
            currency: 'PHP',
            confirmed: $tx->confirmed,
            wallet_id: $tx->wallet_id,
            sender_name: $senderName ?? null,
            sender_identifier: $senderIdentifier ?? null,
            payment_method: $paymentMethod ?? null,
            deposit_type: $depositType ?? null,
            voucher_code: $voucherCode ?? null,
            disbursement: $disbursement ?? null,
            created_at: $tx->created_at->toIso8601String(),
            updated_at: $tx->updated_at->toIso8601String(),
        );
    }

    /**
     * Derive deposit metadata from transaction and meta data.
     * Be resourceful - extract from existing fields.
     */
    protected static function deriveDepositMetadata(Transaction $tx, array $meta): array
    {
        // Check for explicitly set new fields first
        if (! empty($meta['sender_name']) && ! empty($meta['deposit_type'])) {
            return [
                $meta['sender_name'],
                $meta['sender_identifier'] ?? null,
                $meta['payment_method'] ?? null,
                $meta['deposit_type'],
            ];
        }

        // Derive from existing meta structure
        $type = $meta['type'] ?? null;
        $gateway = $meta['gateway'] ?? null;

        // Voucher payment (from another user redeeming a voucher)
        if ($type === 'voucher_payment') {
            $voucherCode = $meta['voucher_code'] ?? null;
            $issuerName = 'Voucher Payment';

            // Try to get issuer info from voucher
            if ($voucherCode) {
                $voucher = Voucher::where('code', $voucherCode)->first();
                if ($voucher && $voucher->owner) {
                    $issuerName = $voucher->owner->name ?? $voucher->owner->email;
                }
            }

            return [
                $issuerName,
                $voucherCode,
                'voucher',
                'voucher_payment',
            ];
        }

        // Bank/NetBank top-up
        if ($type === 'top_up' || $gateway === 'netbank') {
            $wallet = $tx->wallet;
            $user = $wallet?->holder;

            return [
                $user?->name ?? $user?->email ?? 'Wallet Top-Up',
                $meta['reference_no'] ?? null,
                $gateway ?? 'bank',
                'manual_topup',
            ];
        }

        // QR payment (future)
        if ($type === 'qr_payment') {
            return [
                'QR Payment',
                $meta['reference_no'] ?? null,
                'qr',
                'qr_payment',
            ];
        }

        // Default fallback
        return [
            'Deposit',
            null,
            $gateway,
            $type,
        ];
    }

    /**
     * Derive withdrawal metadata (voucher generation charges).
     */
    protected static function deriveWithdrawalMetadata(Transaction $tx, array $meta): array
    {
        // Check for explicitly set fields first
        if (! empty($meta['voucher_code'])) {
            return [
                $meta['voucher_code'],
                $meta['disbursement'] ?? null,
            ];
        }

        // Try to find voucher generation charge by timestamp and amount
        // Voucher generations happen in batches at the same time
        $wallet = $tx->wallet;
        $userId = $wallet?->holder?->id;

        if ($userId) {
            // Look for generation charges within 1 second of this transaction
            $charge = VoucherGenerationCharge::where('user_id', $userId)
                ->whereBetween('generated_at', [
                    $tx->created_at->subSecond(),
                    $tx->created_at->addSecond(),
                ])
                ->first();

            if ($charge) {
                // Found a matching batch - use first voucher code as representative
                $codes = $charge->voucher_codes;
                $voucherCode = is_array($codes) && ! empty($codes) ? $codes[0] : null;

                // Try to get disbursement info from any voucher in this batch
                if ($voucherCode) {
                    $voucher = Voucher::where('code', $voucherCode)->first();
                    if ($voucher && ! empty($voucher->metadata['disbursement'])) {
                        return [
                            $voucherCode.($charge->voucher_count > 1 ? ' (+'.($charge->voucher_count - 1).' more)' : ''),
                            $voucher->metadata['disbursement'],
                        ];
                    }

                    return [
                        $voucherCode.($charge->voucher_count > 1 ? ' (+'.($charge->voucher_count - 1).' more)' : ''),
                        null,
                    ];
                }
            }
        }

        // Fallback - generic voucher generation charge
        return [
            null,
            null,
        ];
    }
}
