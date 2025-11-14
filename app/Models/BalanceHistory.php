<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceHistory extends Model
{
    protected $table = 'balance_history';

    protected $fillable = [
        'account_number',
        'gateway',
        'balance',
        'available_balance',
        'currency',
        'recorded_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'available_balance' => 'integer',
        'recorded_at' => 'datetime',
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
     * Get the account balance this history entry belongs to.
     */
    public function accountBalance(): BelongsTo
    {
        return $this->belongsTo(AccountBalance::class, 'account_number', 'account_number')
            ->where('gateway', $this->gateway);
    }
}
