<?php

declare(strict_types=1);

namespace App\OgResolvers\Concerns;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

trait GeneratesQrDataUri
{
    protected function generateQrDataUri(string $url, int $size = 280): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new PngWriter)->write($qrCode)->getDataUri();
    }
}
