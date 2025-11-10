<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionItemPriceHistory extends Model
{
    protected $table = 'instruction_item_price_history';

    protected $fillable = [
        'instruction_item_id',
        'old_price',
        'new_price',
        'currency',
        'changed_by',
        'reason',
        'effective_at',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

    public function instructionItem(): BelongsTo
    {
        return $this->belongsTo(InstructionItem::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
    
    public function priceDifference(): int
    {
        return $this->new_price - $this->old_price;
    }
    
    public function percentageChange(): float
    {
        if ($this->old_price === 0) {
            return 100.0;
        }
        return (($this->new_price - $this->old_price) / $this->old_price) * 100;
    }
}
