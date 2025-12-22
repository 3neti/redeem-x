<?php

namespace LBHurtado\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class DisbursementAttempt extends Model
{
    protected $fillable = [
        'voucher_id',
        'user_id',
        'voucher_code',
        'amount',
        'currency',
        'mobile',
        'bank_code',
        'account_number',
        'settlement_rail',
        'gateway',
        'reference_id',
        'gateway_transaction_id',
        'status',
        'error_type',
        'error_message',
        'error_details',
        'request_payload',
        'response_payload',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'error_details' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes for reporting
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('attempted_at', '>=', now()->subDays($days));
    }

    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeByErrorType($query, string $errorType)
    {
        return $query->where('error_type', $errorType);
    }
}
