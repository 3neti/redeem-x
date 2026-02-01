<?php

namespace App\Services;

use App\Settings\VoucherSettings;
use Lbhurtado\Voucher\Enums\VoucherType;
use Lbhurtado\Voucher\Models\Voucher;

class VoucherShareLinkBuilder
{
    public static function buildLinks(Voucher $voucher): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $settings = app(VoucherSettings::class);
        $disbursePath = $settings->default_redemption_endpoint;
        $payPath = $settings->default_settlement_endpoint;
        $code = $voucher->code;

        return match ($voucher->voucher_type) {
            VoucherType::REDEEMABLE => [
                'disburse_url' => "{$baseUrl}{$disbursePath}?code={$code}",
            ],
            VoucherType::PAYABLE => [
                'pay_url' => "{$baseUrl}{$payPath}?code={$code}",
            ],
            VoucherType::SETTLEMENT => [
                'pay_url' => "{$baseUrl}{$payPath}?code={$code}",
                'disburse_url' => "{$baseUrl}{$disbursePath}?code={$code}",
            ],
        };
    }

    public static function formatForSms(array $links): string
    {
        if (isset($links['pay_url']) && isset($links['disburse_url'])) {
            return "Pay: {$links['pay_url']}\nRedeem: {$links['disburse_url']}";
        }

        if (isset($links['pay_url'])) {
            return "Share: {$links['pay_url']}";
        }

        return "Share: {$links['disburse_url']}";
    }
}
