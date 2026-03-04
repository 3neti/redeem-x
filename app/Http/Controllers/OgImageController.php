<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Voucher\GenerateVoucherOgImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;

class OgImageController extends Controller
{
    /**
     * Serve a dynamically generated OG image for a voucher.
     *
     * Used as the og:image URL in link previews (WhatsApp, iMessage, Viber).
     */
    public function voucher(string $code): Response
    {
        $voucher = Voucher::where('code', strtoupper($code))->first();

        if (! $voucher) {
            return $this->fallbackImage();
        }

        GenerateVoucherOgImage::run($voucher);

        $status = $this->resolveStatus($voucher);
        $filename = "og/{$voucher->code}-{$status}.png";

        if (! Storage::disk('public')->exists($filename)) {
            return $this->fallbackImage();
        }

        return response(Storage::disk('public')->get($filename))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function fallbackImage(): Response
    {
        // Return a 1x1 transparent PNG as fallback
        $img = imagecreatetruecolor(1200, 630);
        $bg = imagecolorallocate($img, 245, 245, 245);
        imagefilledrectangle($img, 0, 0, 1200, 630, $bg);

        $fontBold = storage_path('fonts/Inter-Bold.ttf');
        if (file_exists($fontBold)) {
            $dark = imagecolorallocate($img, 60, 60, 60);
            imagettftext($img, 36, 0, 420, 330, $dark, $fontBold, config('app.name', 'RedeemX'));
        }

        ob_start();
        imagepng($img, null, 6);
        $data = ob_get_clean();
        imagedestroy($img);

        return response($data)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function resolveStatus(Voucher $voucher): string
    {
        if ($voucher->isRedeemed()) {
            return 'redeemed';
        }
        if ($voucher->isExpired()) {
            return 'expired';
        }
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return 'pending';
        }

        return 'active';
    }
}
