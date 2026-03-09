<?php

namespace LBHurtado\LocationPreset\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\LocationPreset\Models\LocationPreset;

class LocationPresetFactory extends Factory
{
    protected $model = LocationPreset::class;

    public function definition(): array
    {
        $centerLat = $this->faker->latitude(14.40, 14.70);
        $centerLng = $this->faker->longitude(120.90, 121.10);
        $offset = 0.005;

        return [
            'name' => $this->faker->city().' Area',
            'coordinates' => [
                ['lat' => $centerLat + $offset, 'lng' => $centerLng - $offset],
                ['lat' => $centerLat + $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng - $offset],
            ],
            'radius' => $this->faker->randomElement([100, 250, 500, 1000]),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
            'model_type' => null,
            'model_id' => null,
        ]);
    }
}
