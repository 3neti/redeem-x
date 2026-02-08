<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TopUp>
 */
class TopUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'gateway' => 'netbank',
            'reference_no' => 'TOPUP-'.strtoupper($this->faker->bothify('??########')),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => 'PHP',
            'payment_status' => 'PENDING',
            'institution_code' => $this->faker->randomElement(['GCASH', 'MAYA', 'BDO', 'BPI', null]),
            'redirect_url' => $this->faker->url(),
        ];
    }
}
