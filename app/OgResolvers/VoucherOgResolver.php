<?php

declare(strict_types=1);

namespace App\OgResolvers;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\OgMeta\Data\OgMetaData;
use LBHurtado\OgMeta\Resolvers\ModelOgResolver;
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

        return new OgMetaData(
            title: "{$type}: {$this->dateDiff($model, $status)}",
            description: "Voucher {$model->code} — {$formattedAmount}",
            status: $status,
            headline: $model->code,
            subtitle: $formattedAmount,
            tagline: 'Tap to redeem this voucher',
            url: url("/disburse?code={$model->code}"),
            cacheKey: $model->code,
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
            default => 'Created '.$voucher->created_at->diffForHumans(),
        };
    }
}
