<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopUp extends Model
{
    protected $fillable = [
        'user_id',
        'gateway',
        'reference_no',
        'amount',
        'currency',
        'payment_status',
        'payment_id',
        'institution_code',
        'redirect_url',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'PAID';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'PENDING';
    }

    public function isFailed(): bool
    {
        return in_array($this->payment_status, ['FAILED', 'EXPIRED']);
    }

    public function markAsPaid(string $paymentId): void
    {
        $this->update([
            'payment_status' => 'PAID',
            'payment_id' => $paymentId,
            'paid_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'PENDING');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'PAID');
    }
}
