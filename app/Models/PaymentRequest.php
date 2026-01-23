<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use LBHurtado\Voucher\Models\Voucher;

class PaymentRequest extends Model
{
    use Notifiable;
    protected $fillable = [
        'reference_id',
        'voucher_id',
        'amount',
        'currency',
        'payer_info',
        'meta',
        'status',
        'confirmed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payer_info' => 'array',
        'meta' => 'array',
        'confirmed_at' => 'datetime',
    ];

    /**
     * Get the route key name for route model binding.
     * Use reference_id instead of id for cleaner URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'reference_id';
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAwaitingConfirmation($query)
    {
        return $query->where('status', 'awaiting_confirmation');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // Helpers
    public function markAsAwaitingConfirmation(): void
    {
        $this->update(['status' => 'awaiting_confirmation']);
    }

    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function getAmountInMajorUnits(): float
    {
        return $this->amount / 100;
    }
}
