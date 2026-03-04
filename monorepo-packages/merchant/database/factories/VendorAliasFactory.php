<?php

namespace LBHurtado\Merchant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Merchant\Models\VendorAlias;

class VendorAliasFactory extends Factory
{
    protected $model = VendorAlias::class;

    public function definition(): array
    {
        return [
            'alias' => strtoupper($this->faker->lexify('????')), // 4 random letters
            'owner_user_id' => 1, // Will be overridden in tests
            'status' => 'active',
            'assigned_at' => now(),
        ];
    }

    /**
     * Indicate that the alias is reserved.
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
            'reservation_reason' => 'Protected name',
        ]);
    }

    /**
     * Indicate that the alias is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }
}
