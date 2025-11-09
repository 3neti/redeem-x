<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug,
            'description' => fake()->sentence,
            'status' => 'active',
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => fake()->numberBetween(100, 1000),
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                ],
                'inputs' => [
                    'fields' => ['selfie', 'signature'],
                ],
                'feedback' => [
                    'email' => fake()->email,
                    'mobile' => null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => fake()->sentence,
                    'url' => null,
                ],
                'count' => 1,
                'prefix' => '',
                'mask' => '****',
                'ttl' => null,
            ]),
        ];
    }

    /**
     * Indicate that the campaign is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the campaign is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Create a blank campaign with minimal instructions.
     */
    public function blank(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Blank Template',
            'description' => 'Start from scratch',
            'instructions' => VoucherInstructionsData::generateFromScratch(),
        ]);
    }
}
