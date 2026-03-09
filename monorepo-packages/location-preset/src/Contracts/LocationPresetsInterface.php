<?php

namespace LBHurtado\LocationPreset\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LBHurtado\LocationPreset\Models\LocationPreset;

interface LocationPresetsInterface
{
    public function locationPresets(): MorphMany;

    public function addLocationPreset(string $name, array $coordinates, int $radius = 0): LocationPreset;
}
