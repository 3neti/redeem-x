<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSmsRedemptionCommand extends Command
{
    protected $signature = 'test:sms-redeem 
                            {code : Voucher code to redeem}
                            {mobile : Mobile number (09XX or 639XX)}
                            {--bank= : Bank specification (MAYA, GCASH:09181111111, etc.)}';

    protected $description = 'Test SMS voucher redemption flow';

    public function handle(): int
    {
        $code = strtoupper($this->argument('code'));
        $mobile = $this->argument('mobile');
        $bank = $this->option('bank');

        $this->info("Testing SMS redemption...\n");
        $this->line("Voucher Code: {$code}");
        $this->line("Mobile: {$mobile}");
        $this->line('Bank Spec: '.($bank ?: '(default)'));
        $this->newLine();

        // Prepare request data
        $payload = [
            'voucher_code' => $code,
            'mobile' => $mobile,
            'bank_spec' => $bank,
        ];

        try {
            // Call local API endpoint
            $url = config('app.url').'/api/v1/redeem/sms';

            $this->comment("Calling: POST {$url}");
            $this->comment('Payload: '.json_encode($payload, JSON_PRETTY_PRINT));
            $this->newLine();

            $response = Http::post($url, $payload);

            $data = $response->json();
            $status = $response->status();

            // Display response
            $this->line("HTTP Status: {$status}");
            $this->newLine();

            if ($response->successful()) {
                $this->info('✅ SUCCESS');
                $this->newLine();

                if (isset($data['message'])) {
                    $this->line("Message: {$data['message']}");
                }

                if (isset($data['data'])) {
                    $this->line("\nVoucher Details:");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Code', $data['data']['voucher']['code'] ?? 'N/A'],
                            ['Amount', '₱'.number_format($data['data']['voucher']['amount'] ?? 0, 2)],
                            ['Currency', $data['data']['voucher']['currency'] ?? 'PHP'],
                            ['Mobile', $data['data']['mobile'] ?? 'N/A'],
                            ['Bank Account', $data['data']['bank_account'] ?? 'N/A'],
                        ]
                    );
                }

                return self::SUCCESS;
            }

            // Handle errors
            $this->error('❌ FAILED');
            $this->newLine();

            if (isset($data['error'])) {
                $this->line("Error Type: {$data['error']}");
            }

            if (isset($data['message'])) {
                $this->line("Message: {$data['message']}");
            }

            if (isset($data['redemption_url'])) {
                $this->line("Redemption URL: {$data['redemption_url']}");
            }

            $this->newLine();
            $this->comment('Full Response:');
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ EXCEPTION');
            $this->newLine();
            $this->error($e->getMessage());
            $this->newLine();
            $this->comment('Stack Trace:');
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
