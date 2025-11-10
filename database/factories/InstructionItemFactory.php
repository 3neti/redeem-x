<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstructionItem>
 */
class InstructionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['feedback', 'inputs', 'validation', 'rider'];
        $type = fake()->randomElement($types);
        $field = fake()->word();
        
        return [
            'name' => fake()->words(2, true),
            'index' => "{$type}.{$field}",
            'type' => $type,
            'price' => fake()->numberBetween(100, 500),
            'currency' => 'PHP',
            'meta' => [
                'description' => fake()->sentence(),
            ],
        ];
    }
}
