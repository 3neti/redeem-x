<?php

namespace LBHurtado\Merchant\Actions;

use LBHurtado\Merchant\Models\VendorAlias;
use LBHurtado\Merchant\Services\VendorAliasService;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignVendorAlias
{
    use AsAction;

    /**
     * Assign a vendor alias to a user.
     *
     * @param  int  $ownerUserId  The user to assign the alias to
     * @param  string  $alias  The alias to assign (will be normalized)
     * @param  int|null  $assignedByUserId  The admin user assigning the alias
     * @param  string|null  $notes  Optional notes
     *
     * @throws \RuntimeException
     */
    public function handle(
        int $ownerUserId,
        string $alias,
        ?int $assignedByUserId = null,
        ?string $notes = null
    ): VendorAlias {
        $service = new VendorAliasService;

        // Step 1: Normalize
        $normalized = $service->normalize($alias);

        // Step 2: Validate format
        if (! $service->validate($normalized)) {
            throw new \RuntimeException('Invalid alias format. Must be 3-8 uppercase letters/digits, starting with a letter.');
        }

        // Step 3: Check reserved
        if ($service->isReserved($normalized)) {
            throw new \RuntimeException("Alias '{$normalized}' is reserved and cannot be assigned.");
        }

        // Step 4: Check uniqueness
        if (VendorAlias::where('alias', $normalized)->exists()) {
            throw new \RuntimeException('Alias already exists.');
        }

        // Step 5: Create
        return VendorAlias::create([
            'alias' => $normalized,
            'owner_user_id' => $ownerUserId,
            'assigned_by_user_id' => $assignedByUserId,
            'assigned_at' => now(),
            'status' => 'active',
            'notes' => $notes,
        ]);
    }
}
