<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Location Preset Model
    |--------------------------------------------------------------------------
    |
    | Override the default LocationPreset model if needed.
    |
    */

    'location_preset_model' => LBHurtado\LocationPreset\Models\LocationPreset::class,

    /*
    |--------------------------------------------------------------------------
    | Model Primary Key Attribute
    |--------------------------------------------------------------------------
    |
    | The column name used for the polymorphic model key.
    |
    */

    'model_primary_key_attribute' => 'model_id',

    /*
    |--------------------------------------------------------------------------
    | Default Presets
    |--------------------------------------------------------------------------
    |
    | System-wide location presets available to all users.
    | Each preset defines a polygon as an array of {lat, lng} coordinate pairs.
    |
    */

    'default_presets' => [
        [
            'name' => 'BGC, Taguig',
            'coordinates' => [
                ['lat' => 14.5547, 'lng' => 121.0444],
                ['lat' => 14.5547, 'lng' => 121.0574],
                ['lat' => 14.5467, 'lng' => 121.0574],
                ['lat' => 14.5407, 'lng' => 121.0534],
                ['lat' => 14.5397, 'lng' => 121.0464],
                ['lat' => 14.5437, 'lng' => 121.0424],
            ],
            'radius' => 500,
        ],
        [
            'name' => 'Makati CBD',
            'coordinates' => [
                ['lat' => 14.5640, 'lng' => 121.0150],
                ['lat' => 14.5640, 'lng' => 121.0340],
                ['lat' => 14.5530, 'lng' => 121.0340],
                ['lat' => 14.5480, 'lng' => 121.0270],
                ['lat' => 14.5510, 'lng' => 121.0150],
            ],
            'radius' => 500,
        ],
        [
            'name' => 'Ortigas Center',
            'coordinates' => [
                ['lat' => 14.5900, 'lng' => 121.0550],
                ['lat' => 14.5900, 'lng' => 121.0700],
                ['lat' => 14.5810, 'lng' => 121.0700],
                ['lat' => 14.5780, 'lng' => 121.0620],
                ['lat' => 14.5820, 'lng' => 121.0550],
            ],
            'radius' => 500,
        ],
    ],

];
