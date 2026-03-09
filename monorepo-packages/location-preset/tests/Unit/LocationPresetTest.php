<?php

use LBHurtado\LocationPreset\Models\LocationPreset;
use LBHurtado\LocationPreset\Tests\Models\User;

it('can create a location preset for a user', function () {
    $user = User::factory()->create();

    $preset = $user->addLocationPreset('BGC Office', [
        ['lat' => 14.5547, 'lng' => 121.0444],
        ['lat' => 14.5547, 'lng' => 121.0574],
        ['lat' => 14.5467, 'lng' => 121.0574],
        ['lat' => 14.5467, 'lng' => 121.0444],
    ], 500);

    expect($preset)->toBeInstanceOf(LocationPreset::class)
        ->and($preset->name)->toBe('BGC Office')
        ->and($preset->radius)->toBe(500)
        ->and($preset->is_default)->toBeFalse()
        ->and($preset->coordinates)->toHaveCount(4);
});

it('can retrieve location presets for a user', function () {
    $user = User::factory()->create();

    $user->addLocationPreset('Place A', [
        ['lat' => 14.55, 'lng' => 121.04],
        ['lat' => 14.55, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.04],
    ]);

    $user->addLocationPreset('Place B', [
        ['lat' => 14.56, 'lng' => 121.06],
        ['lat' => 14.56, 'lng' => 121.07],
        ['lat' => 14.55, 'lng' => 121.07],
        ['lat' => 14.55, 'lng' => 121.06],
    ]);

    expect($user->locationPresets)->toHaveCount(2);
});

it('can delete own preset but not default presets', function () {
    $user = User::factory()->create();

    $own = $user->addLocationPreset('My Place', [
        ['lat' => 14.55, 'lng' => 121.04],
        ['lat' => 14.55, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.04],
    ]);

    $default = LocationPreset::factory()->default()->create();

    expect($user->deleteLocationPreset($own->id))->toBeTrue();
    expect($user->locationPresets)->toHaveCount(0);

    // Cannot delete default presets via trait (not owned by user)
    expect($user->deleteLocationPreset($default->id))->toBeFalse();
    expect(LocationPreset::find($default->id))->not->toBeNull();
});

it('merges user presets with defaults', function () {
    $user = User::factory()->create();

    LocationPreset::factory()->default()->count(2)->create();

    $user->addLocationPreset('My Place', [
        ['lat' => 14.55, 'lng' => 121.04],
        ['lat' => 14.55, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.04],
    ]);

    $all = $user->getLocationPresetsWithDefaults();

    expect($all)->toHaveCount(3);
});

it('converts coordinates to a phpgeo polygon', function () {
    $preset = LocationPreset::factory()->create([
        'coordinates' => [
            ['lat' => 14.55, 'lng' => 121.04],
            ['lat' => 14.55, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.04],
        ],
    ]);

    $polygon = $preset->toPolygon();

    expect($polygon->getNumberOfPoints())->toBe(4);
});

it('detects a point inside the polygon', function () {
    $preset = LocationPreset::factory()->create([
        'coordinates' => [
            ['lat' => 14.55, 'lng' => 121.04],
            ['lat' => 14.55, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.04],
        ],
    ]);

    // Center of the rectangle
    expect($preset->contains(14.54, 121.05))->toBeTrue();

    // Well outside the rectangle
    expect($preset->contains(14.60, 121.10))->toBeFalse();
});

it('detects a point within buffer radius', function () {
    $preset = LocationPreset::factory()->create([
        'coordinates' => [
            ['lat' => 14.55, 'lng' => 121.04],
            ['lat' => 14.55, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.04],
        ],
        'radius' => 1000,
    ]);

    // Point just outside the polygon but within 1km of a vertex
    expect($preset->containsWithBuffer(14.554, 121.04))->toBeTrue();

    // Point far away
    expect($preset->containsWithBuffer(15.00, 122.00))->toBeFalse();
});

it('calculates centroid correctly', function () {
    $preset = LocationPreset::factory()->create([
        'coordinates' => [
            ['lat' => 14.56, 'lng' => 121.04],
            ['lat' => 14.56, 'lng' => 121.06],
            ['lat' => 14.54, 'lng' => 121.06],
            ['lat' => 14.54, 'lng' => 121.04],
        ],
    ]);

    $centroid = $preset->centroid();

    expect(round($centroid['lat'], 6))->toBe(14.55)
        ->and(round($centroid['lng'], 6))->toBe(121.05);
});

it('scopes to defaults', function () {
    LocationPreset::factory()->count(2)->create(['is_default' => false, 'model_type' => User::class, 'model_id' => 1]);
    LocationPreset::factory()->default()->count(3)->create();

    expect(LocationPreset::defaults()->count())->toBe(3);
});

it('scopes to a specific model', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->addLocationPreset('Mine', [
        ['lat' => 14.55, 'lng' => 121.04],
        ['lat' => 14.55, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.05],
        ['lat' => 14.54, 'lng' => 121.04],
    ]);

    $other->addLocationPreset('Theirs', [
        ['lat' => 14.56, 'lng' => 121.06],
        ['lat' => 14.56, 'lng' => 121.07],
        ['lat' => 14.55, 'lng' => 121.07],
        ['lat' => 14.55, 'lng' => 121.06],
    ]);

    expect(LocationPreset::forModel($user)->count())->toBe(1);
    expect(LocationPreset::forModel($other)->count())->toBe(1);
});

it('returns false for buffer check when radius is zero', function () {
    $preset = LocationPreset::factory()->create([
        'coordinates' => [
            ['lat' => 14.55, 'lng' => 121.04],
            ['lat' => 14.55, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.06],
            ['lat' => 14.53, 'lng' => 121.04],
        ],
        'radius' => 0,
    ]);

    // Outside polygon, radius is 0 — should be false
    expect($preset->containsWithBuffer(14.56, 121.05))->toBeFalse();

    // Inside polygon — should still be true
    expect($preset->containsWithBuffer(14.54, 121.05))->toBeTrue();
});
