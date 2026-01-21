<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use LBHurtado\Voucher\Models\Voucher as VoucherModel;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\{Data, DataCollection};
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Cash\Data\CashData;
use LBHurtado\Cash\Models\Cash;
use Illuminate\Support\Carbon;

class VoucherData extends Data
{
    public function __construct(
        public string                       $code,
        public ?ModelData                   $owner,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $starts_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $expires_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $redeemed_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $processed_on,
        public bool                         $processed,
        public ?VoucherInstructionsData     $instructions,
        /** @var InputData[] */
        public DataCollection               $inputs,
        public ?CashData                    $cash = null,
        public ?ContactData                 $contact = null,
        public ?DisbursementData            $disbursement = null,
//        public ?ModelData                   $redeemer,
        // Computed fields
        public ?string                      $status = null,
        public ?float                       $amount = null,
        public ?string                      $currency = null,
        public ?bool                        $is_expired = null,
        public ?bool                        $is_redeemed = null,
        public ?bool                        $can_redeem = null,
        public ?array                       $external_metadata = null,
        public ?float                       $target_amount = null,
        public ?string                      $voucher_type = null,
    ) {}

    public static function fromModel(VoucherModel $model): static
    {
        $instructions = null;
        try {
            $instructions = $model->instructions instanceof VoucherInstructionsData
                ? $model->instructions
                : ($model->instructions
                    ? VoucherInstructionsData::from($model->instructions)
                    : null
                );
        } catch (\Exception $e) {
            // Instructions might not exist or be invalid
        }

        return new static(
            code: $model->code,
            owner: $model->owner
                ? ModelData::fromModel($model->owner)
                : null,
            created_at: $model->created_at,
            starts_at: $model->starts_at,
            expires_at: $model->expires_at,
            redeemed_at: $model->redeemed_at,
            processed_on: $model->processed_on,
            processed: $model->processed,
            instructions: $instructions,
            inputs: new DataCollection(InputData::class, $model->inputs),
            cash: $model->cash instanceof Cash ? CashData::fromModel($model->cash) : null,
            contact: $model->contact instanceof Contact ? ContactData::fromModel($model->contact) : null,
            disbursement: DisbursementData::fromMetadata($model->metadata),
//            redeemer: $model->redeemer
//                ? ModelData::fromModel($model->redeemer)
//                : null,
            // Computed fields
            status: static::computeStatus($model),
            amount: $instructions?->cash?->amount,
            currency: $instructions?->cash?->currency ?? 'PHP',
            is_expired: $model->isExpired(),
            is_redeemed: $model->isRedeemed(),
            can_redeem: static::computeCanRedeem($model),
            external_metadata: $model->external_metadata,
            target_amount: $model->target_amount,
            voucher_type: $model->voucher_type?->value,
        );
    }

    /**
     * Compute voucher status.
     */
    protected static function computeStatus(VoucherModel $model): string
    {
        if ($model->isRedeemed()) {
            return 'redeemed';
        }

        if ($model->isExpired()) {
            return 'expired';
        }

        if ($model->starts_at && $model->starts_at->isFuture()) {
            return 'pending';
        }

        return 'active';
    }

    /**
     * Check if voucher can be redeemed.
     */
    protected static function computeCanRedeem(VoucherModel $model): bool
    {
        return ! $model->isRedeemed()
            && ! $model->isExpired()
            && (! $model->starts_at || $model->starts_at->isPast());
    }
}

class ModelData extends Data
{
    public function __construct(
        public int         $id,
        public string      $name,
        public string      $email,
        public ?string     $mobile
    ){}

    public static function fromModel($model): static
    {
        return new static(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            mobile: $model->mobile ?? null,
        );
    }
}
