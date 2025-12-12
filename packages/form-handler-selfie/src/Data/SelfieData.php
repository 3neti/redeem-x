<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSelfie\Data;

use Spatie\LaravelData\Data;

/**
 * Selfie Data
 * 
 * Represents captured selfie image data from browser camera.
 */
class SelfieData extends Data
{
    public function __construct(
        public string $image,        // base64 encoded image
        public string $timestamp,
        public int $width,
        public int $height,
        public string $format,
    ) {}
}
