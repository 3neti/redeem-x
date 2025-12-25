<?php

namespace App\Data\Api\Wallet;

use Bavix\Wallet\Models\Transaction as TransactionModel;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class TransactionData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $amount,
        public bool $confirmed,
        public array $meta,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public Carbon $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public Carbon $updated_at,
    ) {}

    public static function fromModel(TransactionModel $transaction): self
    {
        return new self(
            id: $transaction->id,
            type: $transaction->type,
            amount: number_format($transaction->amountFloat, 2, '.', ''),
            confirmed: $transaction->confirmed,
            meta: $transaction->meta ?? [],
            created_at: $transaction->created_at,
            updated_at: $transaction->updated_at,
        );
    }
}
