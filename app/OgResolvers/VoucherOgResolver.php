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
        $type = $model->voucher_type?->value ?? 'redeemable';

        $rider = $model->instructions->rider;
        $validation = $model->instructions->cash->validation;
        $payee = $validation->payable ?? $validation->mobile ?? 'CASH';

        return new OgMetaData(
            title: $rider->message ?? $this->defaultTitle($status),
            description: ucfirst($type).' voucher — '.$formattedAmount,
            status: $status,
            headline: $model->code,
            subtitle: $formattedAmount,
            url: url("/disburse?code={$model->code}"),
            cacheKey: $model->code,
            httpMaxAge: $this->cacheTtl($status),
            typeBadge: $type,
            payeeBadge: $payee,
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

    private function defaultTitle(string $status): string
    {
        return match ($status) {
            'redeemed' => 'This voucher has been redeemed',
            'expired' => 'This voucher has expired',
            'pending' => 'This voucher is not yet active',
            default => 'Click to redeem',
        };
    }

    private function cacheTtl(string $status): int
    {
        return match ($status) {
            'redeemed', 'expired' => 604_800, // 7 days
            default => 600, // 10 minutes
        };
    }

}
