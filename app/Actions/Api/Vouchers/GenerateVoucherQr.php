<?php

namespace App\Actions\Api\Vouchers;

use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

class GenerateVoucherQr
{
    use AsAction;

    /**
     * Generate QR code data for voucher redemption.
     * Returns the redemption URL and QR code image data.
     */
    public function handle(Voucher $voucher): array
    {
        // Generate redemption URL
        $redemptionUrl = url("/redeem?code={$voucher->code}");

        // Generate QR code as base64 data URL
        $qrCode = $this->generateQrCode($redemptionUrl);

        // Safely get cash details (voucher might not have instructions)
        $amount = null;
        $currency = null;
        
        try {
            if ($voucher->cash) {
                $amount = $voucher->cash->getAmount();
                $currency = $voucher->cash->getCurrency();
            }
        } catch (\Throwable $e) {
            // Voucher has no instructions - that's okay for QR generation
        }

        return [
            'qr_code' => $qrCode,
            'redemption_url' => $redemptionUrl,
            'voucher_code' => $voucher->code,
            'amount' => $amount,
            'currency' => $currency,
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'is_redeemed' => $voucher->redeemed_at !== null,
            'is_expired' => $voucher->expires_at && $voucher->expires_at->isPast(),
        ];
    }

    /**
     * Handle as controller action.
     */
    public function asController(string $code): array
    {
        $user = auth()->user();

        // Find voucher by code
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            throw new NotFoundHttpException('Voucher not found');
        }

        // Check if user owns this voucher
        if (!Gate::allows('view', $voucher)) {
            throw new AccessDeniedHttpException('You do not have permission to view this voucher');
        }

        $qrData = $this->handle($voucher);

        return [
            'data' => $qrData,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ];
    }

    /**
     * Generate QR code as data URL using PHP GD.
     * This provides server-side QR generation as an alternative to client-side.
     */
    private function generateQrCode(string $url): string
    {
        // Use a simple QR code library or service
        // For now, return a placeholder - in production, use a library like endroid/qr-code
        // Or defer to client-side generation for better performance
        
        try {
            // Check if endroid/qr-code is available
            if (class_exists(\Endroid\QrCode\QrCode::class)) {
                $qrCode = \Endroid\QrCode\QrCode::create($url)
                    ->setSize(300)
                    ->setMargin(10);

                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);

                return $result->getDataUri();
            }
        } catch (\Throwable $e) {
            // Fall through to placeholder
        }

        // Fallback: Return a data URL indicating client-side generation is recommended
        return 'data:text/plain;base64,' . base64_encode(
            'QR_CODE_PLACEHOLDER: Use client-side generation via /resources/js/composables/useVoucherQr.ts for better performance'
        );
    }
}
