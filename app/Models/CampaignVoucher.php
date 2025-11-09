<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use LBHurtado\Voucher\Models\Voucher;

class CampaignVoucher extends Pivot
{
    /**
     * The table associated with the model.
     */
    protected $table = 'campaign_voucher';

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'instructions_snapshot' => 'array',
    ];

    /**
     * Get the campaign that owns the pivot record.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the voucher that owns the pivot record.
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
