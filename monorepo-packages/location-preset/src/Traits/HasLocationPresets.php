<?php

namespace LBHurtado\LocationPreset\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use LBHurtado\LocationPreset\Models\LocationPreset;

trait HasLocationPresets
{
    public function locationPresets(): MorphMany
    {
        return $this->morphMany(
            $this->getLocationPresetModelClassName(),
            'model',
            'model_type',
            $this->getLocationPresetModelKeyColumnName()
        )->latest('id');
    }

    public function addLocationPreset(string $name, array $coordinates, int $radius = 0): LocationPreset
    {
        return $this->locationPresets()->create([
            'name' => $name,
            'coordinates' => $coordinates,
            'radius' => $radius,
            'is_default' => false,
        ]);
    }

    public function deleteLocationPreset(int $id): bool
    {
        return (bool) $this->locationPresets()
            ->where('id', $id)
            ->where('is_default', false)
            ->delete();
    }

    public function getLocationPresetsWithDefaults(): Collection
    {
        $modelClass = $this->getLocationPresetModelClassName();

        $defaults = $modelClass::defaults()->get();
        $own = $this->locationPresets()->get();

        return $defaults->merge($own);
    }

    protected function getLocationPresetModelClassName(): string
    {
        return config('location-preset.location_preset_model')
            ?? LocationPreset::class;
    }

    protected function getLocationPresetModelKeyColumnName(): string
    {
        return config('location-preset.model_primary_key_attribute') ?? 'model_id';
    }
}
