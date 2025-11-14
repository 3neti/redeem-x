<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceAlert extends Model
{
    protected $fillable = [
        'account_number',
        'gateway',
        'threshold',
        'alert_type',
        'recipients',
        'enabled',
        'last_triggered_at',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'recipients' => 'array',
        'enabled' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Get formatted threshold as currency string.
     */
    public function getFormattedThresholdAttribute(): string
    {
        // Use PHP as default currency if not specified
        $currency = $this->accountBalance?->currency ?? 'PHP';

        return Money::ofMinor($this->threshold, $currency)
            ->formatTo('en_PH');
    }

    /**
     * Get the account balance this alert belongs to.
     */
    public function accountBalance(): BelongsTo
    {
        return $this->belongsTo(AccountBalance::class, 'account_number', 'account_number')
            ->where('gateway', $this->gateway);
    }

    /**
     * Check if alert was triggered today.
     */
    public function wasTriggeredToday(): bool
    {
        return $this->last_triggered_at && $this->last_triggered_at->isToday();
    }

    /**
     * Scope to get enabled alerts.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to get alerts by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }
}
