<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $presets = config('location-preset.default_presets', []);

        foreach ($presets as $preset) {
            DB::table('location_presets')->insertOrIgnore([
                'name' => $preset['name'],
                'coordinates' => json_encode($preset['coordinates']),
                'radius' => $preset['radius'],
                'is_default' => true,
                'model_type' => null,
                'model_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('location_presets')
            ->where('is_default', true)
            ->delete();
    }
};
