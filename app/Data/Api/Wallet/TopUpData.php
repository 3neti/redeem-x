<?php

namespace App\Data\Api\Wallet;

use App\Models\TopUp;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class TopUpData extends Data
{
    public function __construct(
        public int $id,
        public string $reference_no,
        public string $gateway,
        public string $amount,
        public string $currency,
        public string $payment_status,
        public ?string $payment_id,
        public ?string $institution_code,
        public ?string $redirect_url,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $paid_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public Carbon $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public Carbon $updated_at,
    ) {}

    public static function fromModel(TopUp $topUp): self
    {
        return new self(
            id: $topUp->id,
            reference_no: $topUp->reference_no,
            gateway: $topUp->gateway,
            amount: number_format($topUp->amount, 2, '.', ''),
            currency: $topUp->currency,
            payment_status: $topUp->payment_status,
            payment_id: $topUp->payment_id,
            institution_code: $topUp->institution_code,
            redirect_url: $topUp->redirect_url,
            paid_at: $topUp->paid_at,
            created_at: $topUp->created_at,
            updated_at: $topUp->updated_at,
        );
    }
}
