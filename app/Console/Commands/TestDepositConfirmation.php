<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDepositConfirmation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:deposit-confirmation
                            {--amount=150 : Amount in centavos (150 = ₱1.50)}
                            {--mobile=09173011987 : Recipient mobile number}
                            {--sender-name=RUTH APPLE HURTADO : Sender name}
                            {--sender-mobile=09175180722 : Sender mobile number}
                            {--operation-id= : Operation ID (auto-generated if not provided)}
                            {--show-json : Display the JSON payload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test deposit confirmation webhook by simulating a POST to /api/confirm-deposit';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $amount = (int) $this->option('amount');
        $recipientMobile = $this->option('mobile');
        $senderName = $this->option('sender-name');
        $senderMobile = $this->option('sender-mobile');
        $operationId = $this->option('operation-id') ?: rand(100000000, 999999999);

        $this->info('🧪 Testing Deposit Confirmation Webhook');
        $this->newLine();

        // Build the payload matching real NetBank format
        // recipientAccountNumber = alias (91500) + national mobile (09173011987)
        // Example: 91500 + 09173011987 = 9150009173011987
        $recipientAccountNumber = '91500' . $recipientMobile;
        
        // referenceCode = strip alias + replace leading 0 with 1
        // Example: 09173011987 → 9173011987 → 19173011987
        $referenceCode = '1' . substr($recipientMobile, 1);
        
        $payload = [
            'merchant_details' => [
                'merchant_code' => '1',
                'merchant_account' => $recipientMobile,
            ],
            'recipientAccountNumber' => $recipientAccountNumber,
            'commandId' => rand(100000000, 999999999),
            'operationId' => $operationId,
            'referenceNumber' => '20250613GXCHPHM2XXXB' . str_pad(rand(1, 999999999), 12, '0', STR_PAD_LEFT),
            'sender' => [
                'name' => strtoupper($senderName),
                'accountNumber' => $senderMobile,
                'institutionCode' => 'GXCHPHM2XXX', // GCash
            ],
            'alias' => '91500',
            'referenceCode' => $referenceCode,
            'externalTransferStatus' => 'SETTLED',
            'remarks' => 'InstaPay transfer (Test)',
            'amount' => $amount,
            'registrationTime' => now()->toIso8601String(),
            'transferType' => 'QR_P2M',
            'recipientAccountNumberBankFormat' => '113-001-00001-9',
            'channel' => 'INSTAPAY',
            'productBranchCode' => '000',
        ];

        $this->table(
            ['Field', 'Value'],
            [
                ['Amount', '₱' . number_format($amount / 100, 2)],
                ['Recipient Mobile', $recipientMobile],
                ['Recipient Account Number', $payload['recipientAccountNumber']],
                ['Reference Code', $payload['referenceCode']],
                ['Sender', $senderName],
                ['Sender Mobile', $senderMobile],
                ['Operation ID', $operationId],
                ['Status', $payload['externalTransferStatus']],
                ['Channel', $payload['channel']],
            ]
        );

        // Show JSON if requested
        if ($this->option('show-json')) {
            $this->newLine();
            $this->info('📄 JSON Payload:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
        $this->info('📤 Sending POST request to /api/confirm-deposit...');
        $this->newLine();

        try {
            // Make POST request to the webhook endpoint
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(url('/api/confirm-deposit'), $payload);

            $statusCode = $response->status();

            if ($statusCode === 204) {
                $this->components->success('✅ Webhook processed successfully (204 No Content)');
            } else {
                $this->components->error("❌ Unexpected status code: {$statusCode}");
                
                if ($response->body()) {
                    $this->warn('Response body:');
                    $this->line($response->body());
                }

                return self::FAILURE;
            }

            // Show logs hint
            $this->newLine();
            $this->info('💡 Check logs for processing details:');
            $this->comment('   tail -f storage/logs/laravel.log | grep "ConfirmDeposit"');

        } catch (\Exception $e) {
            $this->components->error('❌ Request failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
