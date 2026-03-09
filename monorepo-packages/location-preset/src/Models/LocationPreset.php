<?php

namespace LBHurtado\LocationPreset\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\LocationPreset\Database\Factories\LocationPresetFactory;
use Location\Coordinate;
use Location\Distance\Haversine;
use Location\Polygon;

class LocationPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'coordinates',
        'radius',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'is_default' => 'boolean',
            'radius' => 'integer',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convert stored coordinates to a phpgeo Polygon.
     */
    public function toPolygon(): Polygon
    {
        $polygon = new Polygon;

        foreach ($this->coordinates as $point) {
            $polygon->addPoint(new Coordinate($point['lat'], $point['lng']));
        }

        return $polygon;
    }

    /**
     * Check if a coordinate falls inside the polygon.
     */
    public function contains(float $lat, float $lng): bool
    {
        return $this->toPolygon()->contains(new Coordinate($lat, $lng));
    }

    /**
     * Check if a coordinate is inside the polygon or within the buffer radius of its perimeter.
     */
    public function containsWithBuffer(float $lat, float $lng): bool
    {
        if ($this->contains($lat, $lng)) {
            return true;
        }

        if ($this->radius <= 0) {
            return false;
        }

        $point = new Coordinate($lat, $lng);
        $coords = $this->coordinates;
        $calculator = new Haversine;

        for ($i = 0, $count = count($coords); $i < $count; $i++) {
            $a = new Coordinate($coords[$i]['lat'], $coords[$i]['lng']);
            $b = new Coordinate($coords[($i + 1) % $count]['lat'], $coords[($i + 1) % $count]['lng']);

            $distance = $this->distanceToSegment($point, $a, $b, $calculator);

            if ($distance <= $this->radius) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the centroid of the polygon.
     */
    public function centroid(): array
    {
        $latSum = 0;
        $lngSum = 0;
        $count = count($this->coordinates);

        foreach ($this->coordinates as $point) {
            $latSum += $point['lat'];
            $lngSum += $point['lng'];
        }

        return [
            'lat' => $latSum / $count,
            'lng' => $lngSum / $count,
        ];
    }

    /**
     * Scope to system-wide default presets.
     */
    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to presets owned by a specific model.
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey());
    }

    public static function newFactory(): LocationPresetFactory
    {
        return LocationPresetFactory::new();
    }

    /**
     * Calculate minimum distance from a point to a line segment.
     */
    protected function distanceToSegment(Coordinate $point, Coordinate $a, Coordinate $b, Haversine $calculator): float
    {
        $distToA = $calculator->getDistance($point, $a);
        $distToB = $calculator->getDistance($point, $b);

        return min($distToA, $distToB);
    }
}
