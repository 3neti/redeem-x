<?php

declare(strict_types=1);

namespace App\OgResolvers;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\OgMeta\Data\OgMetaData;
use LBHurtado\OgMeta\Resolvers\ModelOgResolver;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Models\Voucher;

class VoucherOgResolver extends ModelOgResolver
{
    protected string $model = Voucher::class;

    protected string $findBy = 'code';

    protected string $queryParam = 'code';

    protected bool $uppercase = true;

    protected function mapToOgData(Model $model): OgMetaData
    {
        /** @var Voucher $model */
        $status = $this->resolveStatus($model);
        $amount = $model->instructions->cash->amount ?? 0;
        $formattedAmount = '₱'.number_format((float) $amount, 2);
        $type = ucfirst($model->voucher_type?->value ?? 'redeemable');

        $rider = $model->instructions->rider;

        return new OgMetaData(
            title: "{$type}: {$this->dateDiff($model, $status)}",
            description: $this->buildDescription($model, $status, $formattedAmount),
            status: $status,
            headline: $model->code,
            subtitle: $formattedAmount,
            tagline: $this->buildTagline($status),
            url: url("/disburse?code={$model->code}"),
            cacheKey: $model->code,
            httpMaxAge: $this->cacheTtl($status),
            message: $rider->message,
            overlayImage: $this->resolveOverlayImage($rider->splash),
        );
    }

    private function resolveStatus(Voucher $voucher): string
    {
        if ($voucher->isRedeemed()) {
            return 'redeemed';
        }
        if ($voucher->isExpired()) {
            return 'expired';
        }
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return 'pending';
        }

        return 'active';
    }

    private function dateDiff(Voucher $voucher, string $status): string
    {
        return match ($status) {
            'redeemed' => 'Redeemed '.($voucher->redeemed_at?->diffForHumans() ?? 'already'),
            'expired' => 'Expired '.($voucher->expires_at?->diffForHumans() ?? 'already'),
            'pending' => 'Starts '.($voucher->starts_at?->diffForHumans() ?? 'soon'),
            default => $voucher->expires_at
                ? 'Expires '.$voucher->expires_at->diffForHumans()
                : 'Ready to redeem',
        };
    }

    private function buildDescription(Voucher $voucher, string $status, string $formattedAmount): string
    {
        $default = "Voucher {$voucher->code} — {$formattedAmount}";

        return match ($status) {
            'redeemed' => $this->disbursementSummary($voucher, $formattedAmount) ?? "{$default} redeemed",
            'expired' => "This voucher has expired — {$formattedAmount}",
            default => $voucher->instructions->rider->message ?? $default,
        };
    }

    private function buildTagline(string $status): string
    {
        return match ($status) {
            'redeemed' => 'This voucher has been redeemed',
            'expired' => 'This voucher is no longer valid',
            'pending' => 'This voucher is not yet active',
            default => 'Tap to redeem this voucher',
        };
    }

    private function cacheTtl(string $status): int
    {
        return match ($status) {
            'redeemed', 'expired' => 604_800, // 7 days
            default => 600, // 10 minutes
        };
    }

    /**
     * Convert splash content (HTML with <img> or raw base64) into base64 image data.
     */
    private function resolveOverlayImage(?string $splash): ?string
    {
        if (! $splash) {
            return null;
        }

        // HTML splash — extract image URL and download
        if (preg_match('/src=["\']([^"\']+)["\']/', $splash, $matches)) {
            $imageData = @file_get_contents($matches[1]);

            return $imageData ? base64_encode($imageData) : null;
        }

        // Assume raw base64 — validate it decodes to an image
        $decoded = base64_decode($splash, true);
        if ($decoded && @imagecreatefromstring($decoded)) {
            return $splash;
        }

        return null;
    }

    private function disbursementSummary(Voucher $voucher, string $formattedAmount): ?string
    {
        $attempt = DisbursementAttempt::where('voucher_code', $voucher->code)
            ->success()
            ->latest('completed_at')
            ->first();

        if (! $attempt) {
            return null;
        }

        $parts = ["{$formattedAmount} disbursed"];

        if ($attempt->settlement_rail) {
            $parts[] = "via {$attempt->settlement_rail}";
        }

        if ($attempt->bank_code) {
            $parts[] = "to {$attempt->bank_code}";
        }

        return implode(' ', $parts);
    }
}
