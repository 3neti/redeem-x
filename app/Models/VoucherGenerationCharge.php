<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class VoucherGenerationCharge extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'voucher_codes',
        'voucher_count',
        'instructions_snapshot',
        'charge_breakdown',
        'total_charge',
        'charge_per_voucher',
        'generated_at',
    ];

    protected $casts = [
        'voucher_codes' => 'array',
        'instructions_snapshot' => 'array',
        'charge_breakdown' => 'array',
        'total_charge' => 'decimal:2',
        'charge_per_voucher' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function vouchers()
    {
        return Voucher::whereIn('code', $this->voucher_codes)->get();
    }
    
    public function grossProfit(): float
    {
        // Future: subtract actual expenses
        return $this->total_charge;
    }
}
