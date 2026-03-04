<?php

namespace LBHurtado\Merchant\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LBHurtado\Merchant\Models\VendorAlias;

trait HasVendorAlias
{
    /**
     * Get all vendor aliases for this user.
     */
    public function vendorAliases(): HasMany
    {
        return $this->hasMany(VendorAlias::class, 'owner_user_id');
    }

    /**
     * Get the primary (most recent active) vendor alias.
     */
    public function primaryVendorAlias(): HasOne
    {
        return $this->hasOne(VendorAlias::class, 'owner_user_id')
            ->where('status', 'active')
            ->latest('assigned_at');
    }
}
