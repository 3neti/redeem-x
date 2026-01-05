<?php

namespace LBHurtado\Voucher\Models;

use FrittenKeeZ\Vouchers\Models\Redeemer;
use FrittenKeeZ\Vouchers\Models\Voucher as BaseVoucher;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Observers\VoucherObserver;
use LBHurtado\Voucher\Scopes\RedeemedScope;
use LBHurtado\Voucher\Data\VoucherData;
use Spatie\LaravelData\WithData;
use Illuminate\Support\Carbon;
use LBHurtado\ModelInput\Contracts\InputInterface;
use LBHurtado\ModelInput\Traits\HasInputs;
use LBHurtado\Voucher\Traits\HasExternalMetadata;
use LBHurtado\Voucher\Traits\HasVoucherTiming;
use LBHurtado\Voucher\Traits\HasValidationResults;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Enums\VoucherState;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Class Voucher.
 *
 * @property int                                        $id
 * @property string                                     $code
 * @property \Illuminate\Database\Eloquent\Model        $owner
 * @property array                                      $metadata
 * @property Carbon                                     $starts_at
 * @property Carbon                                     $expires_at
 * @property Carbon                                     $redeemed_at
 * @property Carbon                                     $processed_on
 * @property bool                                       $processed
 * @property VoucherInstructionsData                    $instructions
 * @property \FrittenKeeZ\Vouchers\Models\Redeemer      $redeemer
 * @property \Illuminate\Database\Eloquent\Collection   $voucherEntities
 * @property \Illuminate\Database\Eloquent\Collection   $redeemers
 * @property Cash                                       $cash
 * @property Contact                                    $contact
 * @property \LBHurtado\Voucher\Data\ExternalMetadataData $external_metadata
 * @property \LBHurtado\Voucher\Data\VoucherTimingData    $timing
 * @property \LBHurtado\Voucher\Data\ValidationResultsData $validation_results
 *
 * @method int getKey()
 */
#[ObservedBy([VoucherObserver::class])]
class Voucher extends BaseVoucher implements InputInterface
{
    use WithData;
    use HasInputs;
    use HasExternalMetadata;
    use HasVoucherTiming;
    use HasValidationResults;

    protected string $dataClass = VoucherData::class;

    public ?Redeemer $redeemer = null;

    protected function casts(): array
    {
        // Include parent's casts and add/override
        return array_merge(parent::casts(), [
            'processed_on' => 'datetime:Y-m-d H:i:s',
            'voucher_type' => VoucherType::class,
            'state' => VoucherState::class,
            'rules' => 'array',
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
        ]);
    }

    public function getRouteKeyName() {
        return "code";
    }

    /**
     * Override the default to trim your incoming code.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $column = $field ?? $this->getRouteKeyName();

        return $this
            ->where($column, strtoupper(trim($value)))
            ->firstOrFail();
    }

    public function setProcessedAttribute(bool $value): self
    {
        $this->setAttribute('processed_on', $value ? now() : null);

        return $this;
    }

    public function getProcessedAttribute(): bool
    {
        return $this->getAttribute('processed_on')
            && $this->getAttribute('processed_on') <= now();
    }

    public function getInstructionsAttribute(): VoucherInstructionsData
    {
        return VoucherInstructionsData::from($this->metadata['instructions']);
    }

    public function getCashAttribute(): ?Cash
    {
        return $this->getEntities(Cash::class)->first();
    }

    public function getRedeemerAttribute(): ?Redeemer
    {
        return $this->redeemers->first();
    }

    public function getContactAttribute(): ?Contact
    {
        return $this->redeemers?->first()?->redeemer;
    }

    /**
     * Target amount accessor - converts between minor units (storage) and major units (display)
     */
    protected function targetAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? $value / 100 : null, // Convert centavos to pesos
            set: fn ($value) => $value ? $value * 100 : null  // Convert pesos to centavos
        );
    }

    // Domain Guards
    
    public function canAcceptPayment(): bool
    {
        return in_array($this->voucher_type, [VoucherType::PAYABLE, VoucherType::SETTLEMENT])
            && $this->state === VoucherState::ACTIVE
            && !$this->isExpired()
            && !$this->isClosed();
    }

    public function canRedeem(): bool
    {
        return in_array($this->voucher_type, [VoucherType::REDEEMABLE, VoucherType::SETTLEMENT])
            && $this->state === VoucherState::ACTIVE
            && !$this->isExpired()
            && $this->redeemed_at === null;
    }

    public function isLocked(): bool
    {
        return $this->state === VoucherState::LOCKED;
    }

    public function isClosed(): bool
    {
        return $this->state === VoucherState::CLOSED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Computed Amount Methods (derived from wallet ledger)

    public function getPaidTotal(): float
    {
        if (!$this->cash || !$this->cash->wallet) {
            return 0.0;
        }

        return $this->cash->wallet->transactions()
            ->where('type', 'deposit')
            ->whereJsonContains('meta->flow', 'pay')
            ->sum('amount') / 100; // Convert minor units to major
    }

    public function getRedeemedTotal(): float
    {
        if (!$this->cash || !$this->cash->wallet) {
            return 0.0;
        }

        return $this->cash->wallet->transactions()
            ->where('type', 'withdraw')
            ->whereJsonContains('meta->flow', 'redeem')
            ->sum('amount') / 100; // Convert minor units to major
    }

    public function getRemaining(): float
    {
        if (!$this->target_amount) {
            return 0.0;
        }

        return $this->target_amount - $this->getPaidTotal();
    }
}
