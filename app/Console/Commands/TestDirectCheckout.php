<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDirectCheckout extends Command
{
    protected $signature = 'test:direct-checkout 
                            {amount=100 : Payment amount in PHP}
                            {voucher=ABCD-EFGI : Voucher code or reference}
                            {institution=GCASH : Financial institution code}';

    protected $description = 'Test NetBank Direct Checkout API';

    public function handle()
    {
        $amount = $this->argument('amount');
        $voucherCode = $this->argument('voucher');
        $institution = $this->argument('institution');

        $this->info('Testing Direct Checkout with:');
        $this->line("  Amount: ₱{$amount}");
        $this->line("  Voucher: {$voucherCode}");
        $this->line("  Institution: {$institution}");
        $this->newLine();

        // Check if using fake mode
        $useFake = config('payment-gateway.netbank.direct_checkout.use_fake');
        if ($useFake) {
            $this->warn('⚠️  FAKE MODE ENABLED - Using mock endpoint');
            $this->line('Set NETBANK_DIRECT_CHECKOUT_USE_FAKE=false to test real API');
            $this->newLine();
        }

        // Get credentials - try OAuth first, then fall back to access key
        $clientId = env('NETBANK_CLIENT_ID');
        $clientSecret = env('NETBANK_CLIENT_SECRET');
        $accessKey = config('payment-gateway.netbank.direct_checkout.access_key');
        $secretKey = config('payment-gateway.netbank.direct_checkout.secret_key');
        $endpoint = config('payment-gateway.netbank.direct_checkout.endpoint');

        if (! $clientId && ! $accessKey) {
            $this->error('❌ NetBank credentials not configured!');
            $this->line('Set NETBANK_CLIENT_ID/SECRET or ACCESS_KEY/SECRET_KEY in .env');

            return 1;
        }

        $useOAuth = $clientId && $clientSecret;
        if ($useOAuth) {
            $this->info('🔑 Using OAuth2 authentication');
        } else {
            $this->info('🔑 Using Access Key authentication');
        }

        // Get OAuth token if using OAuth
        $accessToken = null;
        if ($useOAuth) {
            $this->info('🔑 Acquiring OAuth access token...');
            try {
                $tokenEndpoint = str_replace('/collect/checkout', '/oauth/token', $endpoint);
                $tokenResponse = Http::asForm()->post($tokenEndpoint, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

                if ($tokenResponse->successful()) {
                    $accessToken = $tokenResponse->json()['access_token'] ?? null;
                    if ($accessToken) {
                        $this->line('✓ Access token acquired');
                    }
                } else {
                    $this->warn("Token acquisition failed: {$tokenResponse->body()}");
                    $this->line('Trying direct Basic Auth...');
                }
            } catch (\Exception $e) {
                $this->warn("Token error: {$e->getMessage()}");
                $this->line('Trying direct Basic Auth...');
            }
            $this->newLine();
        }

        $this->info('🔄 Calling NetBank API...');
        $this->line("Endpoint: {$endpoint}");
        $this->newLine();

        // Prepare payload
        $referenceNo = "PAYMENT-{$voucherCode}-".now()->timestamp;
        $appUrl = config('app.url');
        $payload = [
            'reference_no' => $referenceNo,
            'amount' => [
                'value' => (float) $amount,
                'currency' => 'PHP',
            ],
            'recipient_account' => config('payment-gateway.system_account', '09173011987'),
            'redirect_url' => "{$appUrl}/pay?test=true",
            'webhook_url' => "{$appUrl}/webhooks/netbank/payment",
        ];

        // Add institution preference if specified
        if ($institution) {
            $payload['preferred_institution'] = strtoupper($institution);
        }

        try {
            // Build request with appropriate auth
            $request = Http::withHeaders(['Content-Type' => 'application/json']);

            if ($useOAuth && $accessToken) {
                // Use Bearer token
                $request = $request->withToken($accessToken);
            } elseif ($useOAuth) {
                // Fallback to Basic Auth
                $request = $request->withBasicAuth($clientId, $clientSecret);
            } else {
                // Access Key authentication
                $request = $request->withHeaders([
                    'X-Access-Key' => $accessKey,
                    'X-Secret-Key' => $secretKey,
                ]);
            }

            $response = $request->timeout(30)->post($endpoint, $payload);

            $this->newLine();

            if ($response->successful()) {
                $data = $response->json();

                $this->info('✅ SUCCESS!');
                $this->newLine();

                $this->line('📋 Response Data:');
                $this->line('─────────────────────────────────────');

                if (isset($data['checkout_url'])) {
                    $this->line('🔗 Checkout URL:');
                    $this->line("   {$data['checkout_url']}");
                    $this->newLine();
                }

                if (isset($data['reference_no'])) {
                    $this->line("📝 Reference Number: {$data['reference_no']}");
                }

                if (isset($data['qr_code'])) {
                    $qrLength = strlen($data['qr_code']);
                    $this->line("📱 QR Code: <included> ({$qrLength} chars)");
                }

                $this->newLine();
                $this->line('─────────────────────────────────────');
                $this->newLine();

                $this->info('🎯 Next Steps:');
                if (isset($data['checkout_url'])) {
                    $this->line('1. Copy the checkout URL above');
                    $this->line('2. Open it in your browser or mobile');
                    $this->line('3. It should redirect to GCash app');
                    $this->line('4. Complete the payment');
                    $this->newLine();

                    // Offer to open URL
                    if ($this->confirm('Open checkout URL in browser?', false)) {
                        $this->line("Opening {$data['checkout_url']}");
                        if (PHP_OS_FAMILY === 'Darwin') {
                            exec("open '{$data['checkout_url']}'");
                        } elseif (PHP_OS_FAMILY === 'Linux') {
                            exec("xdg-open '{$data['checkout_url']}'");
                        } elseif (PHP_OS_FAMILY === 'Windows') {
                            exec("start '{$data['checkout_url']}'");
                        }
                    }
                }

                $this->newLine();
                $this->line('📦 Full Response:');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));

            } else {
                $this->error('❌ API Error!');
                $this->line("Status: {$response->status()}");
                $this->line("Response: {$response->body()}");

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }
}
