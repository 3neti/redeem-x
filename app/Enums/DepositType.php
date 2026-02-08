<?php

namespace App\Enums;

enum DepositType: string
{
    case MANUAL_TOPUP = 'manual_topup';
    case VOUCHER_PAYMENT = 'voucher_payment';
    case QR_PAYMENT = 'qr_payment';

    /**
     * Get display label for the deposit type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL_TOPUP => 'Manual',
            self::VOUCHER_PAYMENT => 'Voucher',
            self::QR_PAYMENT => 'InstaPay QR',
        };
    }

    /**
     * Get badge variant for UI display.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::MANUAL_TOPUP => 'secondary',
            self::VOUCHER_PAYMENT => 'default',
            self::QR_PAYMENT => 'outline',
        };
    }
}
