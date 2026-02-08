<?php

namespace App\Models;

use Bavix\Wallet\Models\Transfer;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Revenue collection record.
 *
 * Tracks revenue collected from InstructionItem wallets to destination wallets.
 * Supports polymorphic destinations (User, Organization, etc.).
 */
class RevenueCollection extends Model
{
    protected $fillable = [
        'instruction_item_id',
        'collected_by_user_id',
        'destination_type',
        'destination_id',
        'amount',
        'transfer_uuid',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    /**
     * InstructionItem that revenue was collected from.
     */
    public function instructionItem(): BelongsTo
    {
        return $this->belongsTo(InstructionItem::class);
    }

    /**
     * User who initiated the collection (admin/system).
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    /**
     * Polymorphic destination (User, Organization, etc.).
     */
    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Transfer record from bavix/wallet.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_uuid', 'uuid');
    }

    /**
     * Formatted amount attribute.
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Money::ofMinor($this->amount, 'PHP')->formatTo('en_PH')
        );
    }

    /**
     * Float amount for convenience.
     */
    protected function amountFloat(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->amount / 100
        );
    }

    /**
     * Destination name for display.
     */
    protected function destinationName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $dest = $this->destination;
                if (! $dest) {
                    return 'Unknown';
                }

                return match (true) {
                    $dest instanceof User => $dest->name ?? $dest->email,
                    method_exists($dest, 'getName') => $dest->getName(),
                    default => class_basename($dest).' #'.$dest->id,
                };
            }
        );
    }
}
