<?php

namespace LBHurtado\Merchant\Traits;

use LBHurtado\Merchant\Models\Merchant;

trait HasMerchant
{
    /**
     * Define the belongsToMany relationship to the Merchant model.
     */
    public function merchant()
    {
        return $this->belongsToMany(Merchant::class, 'merchant_user', 'user_id', 'merchant_id')->withTimestamps();
    }

    /**
     * Associate a merchant with the user.
     */
    public function setMerchant(Merchant $merchant): static
    {
        // Enforce single merchant association
        $this->merchant()->sync($merchant);

        return $this;
    }

    /**
     * Set the merchant attribute via the accessor.
     */
    public function setMerchantAttribute(Merchant|string $merchant): void
    {
        if ($merchant instanceof Merchant) {
            $this->setMerchant($merchant);
        } elseif (is_numeric($merchant)) {
            $this->setMerchant(Merchant::find($merchant));
        }
    }

    /**
     * Get the currently associated merchant via the accessor.
     */
    public function getMerchantAttribute()
    {
        return $this->merchant()->first();
    }

    /**
     * Get the user's merchant or create a default one.
     */
    public function getOrCreateMerchant(): Merchant
    {
        $merchant = $this->getMerchantAttribute();

        if (!$merchant) {
            $merchant = Merchant::create([
                'code' => 'USR-' . $this->getKey(),
                'name' => $this->name ?? 'User ' . $this->getKey(),
                'city' => 'Manila',
                'merchant_category_code' => '0000', // General/Personal
                'description' => 'Personal wallet',
                'is_active' => true,
            ]);

            $this->setMerchant($merchant);
        }

        return $merchant;
    }

    /**
     * Check if user has a merchant profile.
     */
    public function hasMerchant(): bool
    {
        return $this->merchant()->exists();
    }
}
