<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountBalance extends Model
{
    protected $fillable = [
        'account_number',
        'gateway',
        'balance',
        'available_balance',
        'currency',
        'checked_at',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'integer',
        'available_balance' => 'integer',
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get formatted balance as currency string.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return Money::ofMinor($this->balance, $this->currency)
            ->formatTo('en_PH');
    }

    /**
     * Get formatted available balance as currency string.
     */
    public function getFormattedAvailableBalanceAttribute(): string
    {
        return Money::ofMinor($this->available_balance, $this->currency)
            ->formatTo('en_PH');
    }

    /**
     * Get balance history for this account.
     */
    public function history(): HasMany
    {
        return $this->hasMany(BalanceHistory::class, 'account_number', 'account_number')
            ->where('gateway', $this->gateway)
            ->orderByDesc('recorded_at');
    }

    /**
     * Get alerts for this account.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(BalanceAlert::class, 'account_number', 'account_number')
            ->where('gateway', $this->gateway);
    }

    /**
     * Check if balance is below any alert threshold.
     */
    public function isLow(): bool
    {
        $alerts = $this->alerts()->where('enabled', true)->get();

        foreach ($alerts as $alert) {
            if ($this->balance < $alert->threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the lowest alert threshold if balance is low.
     */
    public function getLowestTriggeredThreshold(): ?int
    {
        $alerts = $this->alerts()
            ->where('enabled', true)
            ->where('threshold', '>', $this->balance)
            ->orderBy('threshold')
            ->get();

        return $alerts->first()?->threshold;
    }
}
