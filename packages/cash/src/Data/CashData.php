<?php

namespace LBHurtado\Cash\Data;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Carbon;
use LBHurtado\Cash\Data\Casts\MoneyCast;
use LBHurtado\Cash\Data\Transformers\MoneyToStringTransformer;
use LBHurtado\Cash\Models\Cash as CashModel;
use LBHurtado\Wallet\Data\TransactionData;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class CashData extends Data
{
    public function __construct(
        #[WithTransformer(MoneyToStringTransformer::class)]
        #[WithCast(MoneyCast::class)]
        public Money $amount,
        public string $currency,
        public ArrayObject $meta,
        public bool $expired,
        public string $status,
        public array $tags,
        public ?TransactionData $withdrawTransaction,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $expires_on = null,
        public ?string $secret = null
    ) {}

    public static function fromModel(CashModel $cash): CashData
    {
        return new static(
            amount: $cash->amount,
            currency: $cash->currency,
            meta: $cash->meta,
            expires_on: $cash->expires_on,
            expired: $cash->expired,
            status: $cash->status,
            tags: $cash->tags->toArray(),
            secret: $cash->secret,
            withdrawTransaction: $cash->withdrawTransaction ? TransactionData::fromModel($cash->withdrawTransaction) : null
        );
    }
}
