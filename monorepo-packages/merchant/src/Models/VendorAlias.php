<?php

namespace LBHurtado\Merchant\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorAlias extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'alias',
        'owner_user_id',
        'status',
        'assigned_by_user_id',
        'assigned_at',
        'reserved_until',
        'reservation_reason',
        'notes',
        'meta',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'reserved_until' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Get the user who owns this alias.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('merchant.user_model', 'App\\Models\\User'), 'owner_user_id');
    }

    /**
     * Get the admin who assigned this alias.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(config('merchant.user_model', 'App\\Models\\User'), 'assigned_by_user_id');
    }

    /**
     * Check if alias is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if alias is reserved.
     */
    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \LBHurtado\Merchant\Database\Factories\VendorAliasFactory::new();
    }
}
