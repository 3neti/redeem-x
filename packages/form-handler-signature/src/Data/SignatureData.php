<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSignature\Data;

use Spatie\LaravelData\Data;

/**
 * Signature Data
 * 
 * Represents captured signature image data from canvas drawing.
 */
class SignatureData extends Data
{
    public function __construct(
        public string $image,        // base64 encoded image
        public string $timestamp,
        public int $width,
        public int $height,
        public string $format,
    ) {}
}
