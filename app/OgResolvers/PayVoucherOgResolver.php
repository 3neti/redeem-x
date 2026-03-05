<?php

declare(strict_types=1);

namespace App\OgResolvers;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\OgMeta\Data\OgMetaData;
use LBHurtado\OgMeta\Resolvers\ModelOgResolver;
use LBHurtado\Voucher\Models\Voucher;

class PayVoucherOgResolver extends ModelOgResolver
{
    protected string $model = Voucher::class;

    protected string $findBy = 'code';

    protected string $queryParam = 'code';

    protected bool $uppercase = true;

    protected function mapToOgData(Model $model): OgMetaData
    {
        /** @var Voucher $model */
        $status = $this->resolveStatus($model);
        $type = $model->voucher_type?->value ?? 'payable';
        $target = $model->instructions->target_amount ?? $model->instructions->cash->amount ?? 0;
        $formattedTarget = '₱'.number_format((float) $target, 2);

        $rider = $model->instructions->rider;
        $validation = $model->instructions->cash->validation;
        $payee = $validation->payable ?? $validation->mobile ?? 'CASH';

        return new OgMetaData(
            title: $rider->message ?? $this->defaultTitle($status),
            description: ucfirst($type).' voucher — '.$formattedTarget,
            status: $status,
            headline: $model->code,
            subtitle: $formattedTarget,
            url: url("/pay?code={$model->code}"),
            cacheKey: $model->code,
            httpMaxAge: $this->cacheTtl($status),
            typeBadge: $type,
            payeeBadge: $payee,
        );
    }

    private function resolveStatus(Voucher $voucher): string
    {
        if ($voucher->isClosed()) {
            return 'redeemed'; // fully paid — reuse gray styling
        }
        if ($voucher->isExpired()) {
            return 'expired';
        }
        if (! $voucher->canAcceptPayment()) {
            return 'pending';
        }

        return 'active';
    }

    private function defaultTitle(string $status): string
    {
        return match ($status) {
            'redeemed' => 'This voucher has been fully paid',
            'expired' => 'This voucher has expired',
            'pending' => 'This voucher cannot accept payments',
            default => 'Pay this voucher',
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
