<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerLocation\Data;

use Spatie\LaravelData\Data;

/**
 * Location Data
 * 
 * Represents captured location data from browser geolocation.
 */
class LocationData extends Data
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $formatted_address = null,
        public ?array $address_components = null,
        public ?string $snapshot = null, // base64 map image
        public ?string $timestamp = null,
        public ?float $accuracy = null,
    ) {}
}
