<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Voucher authorization policy.
 */
class VoucherPolicy
{
    /**
     * Determine if the user can view the voucher.
     *
     * @param  User  $user
     * @param  Voucher  $voucher
     * @return bool
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return $voucher->owner_id === $user->id;
    }

    /**
     * Determine if the user can update the voucher.
     *
     * @param  User  $user
     * @param  Voucher  $voucher
     * @return bool
     */
    public function update(User $user, Voucher $voucher): bool
    {
        return $voucher->owner_id === $user->id;
    }

    /**
     * Determine if the user can delete the voucher.
     *
     * @param  User  $user
     * @param  Voucher  $voucher
     * @return bool
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        return $voucher->owner_id === $user->id && ! $voucher->isRedeemed();
    }
}
