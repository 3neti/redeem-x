<?php

namespace LBHurtado\Merchant\Contracts;

use LBHurtado\Merchant\Models\Merchant;

interface MerchantInterface
{
    /**
     * Get the associated merchant for the model.
     */
    public function getMerchantAttribute();

    /**
     * Set the merchant for the model.
     */
    public function setMerchant(Merchant $merchant): static;
}
