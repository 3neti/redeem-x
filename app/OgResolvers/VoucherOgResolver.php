<?php

declare(strict_types=1);

namespace App\OgResolvers;

use App\OgResolvers\Concerns\GeneratesQrDataUri;
use App\OgResolvers\Concerns\ResolvesOgImage;
use App\OgResolvers\Concerns\ResolvesOgTitle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LBHurtado\OgMeta\Data\OgMetaData;
use LBHurtado\OgMeta\Resolvers\ModelOgResolver;
use LBHurtado\Voucher\Models\Voucher;

class VoucherOgResolver extends ModelOgResolver
{
    use GeneratesQrDataUri;
    use ResolvesOgImage;
    use ResolvesOgTitle;

    protected string $model = Voucher::class;

    protected string $findBy = 'code';

    protected string $queryParam = 'code';

    protected bool $uppercase = true;

    public function resolve(Request $request): ?OgMetaData
    {
        if (! $request->query($this->queryParam)) {
            return $this->landingOgData('/disburse', 'Redeem Pay Code here', 'Scan to redeem');
        }

        return parent::resolve($request);
    }

    public function resolveForImage(string $identifier): ?OgMetaData
    {
        if ($identifier === 'landing-disburse') {
            return $this->landingOgData('/disburse', 'Redeem Pay Code here', 'Scan to redeem');
        }

        return parent::resolveForImage($identifier);
    }

    protected function mapToOgData(Model $model): OgMetaData
    {
        /** @var Voucher $model */
        $status = $this->resolveStatus($model);
        $type = $model->voucher_type?->value ?? 'redeemable';
        $subtitle = $this->resolveSubtitle($model, $type);

        $rider = $model->instructions->rider;
        $validation = $model->instructions->cash->validation;
        $payee = $validation->payable ?? $validation->mobile ?? 'CASH';

        $imageFields = $this->resolveImageFields($rider, $status, $model);

        return new OgMetaData(
            title: $this->resolveOgTitle($rider, $status, $model),
            description: ucfirst($type).' voucher — '.$subtitle,
            status: $status,
            headline: $model->code,
            subtitle: $subtitle,
            url: url("/disburse?code={$model->code}"),
            cacheKey: $model->code,
            httpMaxAge: $this->cacheTtl($status),
            splashHtml: $imageFields['splashHtml'],
            typeBadge: $type,
            payeeBadge: $payee,
            overlayImage: $imageFields['overlayImage'],
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

    private function landingOgData(string $path, string $title, string $subtitle): OgMetaData
    {
        $landingUrl = url($path);

        return new OgMetaData(
            title: $title,
            description: (config('og-meta.app_name') ?? config('app.name', 'App')).' — '.$subtitle,
            status: 'active',
            headline: config('og-meta.app_name') ?? config('app.name', 'App'),
            subtitle: $subtitle,
            url: $landingUrl,
            cacheKey: 'landing-'.ltrim($path, '/'),
            qrDataUri: $this->generateQrDataUri($landingUrl),
        );
    }

    private function resolveSubtitle(Voucher $voucher, string $type): string
    {
        $fmt = fn (float $v) => '₱'.number_format($v, 2);

        $amount = $voucher->instructions->cash->amount ?? 0;
        $target = $voucher->instructions->target_amount;

        return match ($type) {
            'payable' => $fmt((float) ($target ?? $amount)),
            'settlement' => $fmt((float) $amount).' → '.$fmt((float) ($target ?? $amount)),
            default => $fmt((float) $amount),
        };
    }

}
