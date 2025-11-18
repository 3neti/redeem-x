<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

class SimulateDepositCommand extends Command
{
    protected $signature = 'simulate:deposit 
                            {user? : Mobile/email of recipient, or leave empty to use first user}
                            {amount=100 : Amount in PHP (default: 100)}
                            {--sender-name=TEST SENDER : Sender name}
                            {--sender-institution=GXCHPHM2XXX : Sender institution code}';

    protected $description = 'Simulate a deposit confirmation webhook for testing';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $amount = (float) $this->argument('amount');
        $senderName = $this->option('sender-name');
        $senderInstitution = $this->option('sender-institution');

        // Find user by mobile or email, or use first user
        if ($userIdentifier) {
            $user = \App\Models\User::where('mobile', $userIdentifier)
                ->orWhere('email', $userIdentifier)
                ->first();
        } else {
            $user = \App\Models\User::first();
        }
        
        if (!$user) {
            $this->error($userIdentifier 
                ? "No user found with mobile/email: {$userIdentifier}"
                : "No users in database. Please create a user first."
            );
            return self::FAILURE;
        }
        
        // Ensure user has mobile for webhook
        $mobile = $user->mobile ?? '09173011987';
        if (!$user->mobile) {
            $this->warn("User has no mobile number, using default: {$mobile}");
        }

        $this->info("Simulating deposit for user: {$user->name} ({$user->email})");
        $this->info("Amount: ₱{$amount}");
        $this->info("Sender: {$senderName} ({$senderInstitution})");

        // Generate fake webhook payload matching NetBank structure
        $operationId = random_int(100000000, 999999999);
        $commandId = random_int(100000000, 999999999);
        $refNumber = now()->format('Ymd') . $senderInstitution . 'B' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        
        $payload = [
            'alias' => '91500',
            'amount' => (int) ($amount * 100), // Convert to centavos
            'channel' => 'INSTAPAY',
            'commandId' => $commandId,
            'externalTransferStatus' => 'SETTLED',
            'operationId' => $operationId,
            'productBranchCode' => '000',
            'recipientAccountNumber' => '91500' . $mobile,
            'recipientAccountNumberBankFormat' => '113-001-00001-9',
            'referenceCode' => substr($mobile, 1), // Remove leading 0
            'referenceNumber' => $refNumber,
            'registrationTime' => now()->toIso8601String(),
            'remarks' => "InstaPay transfer #{$refNumber}",
            'sender' => [
                'accountNumber' => '09171234567',
                'name' => $senderName,
                'institutionCode' => $senderInstitution,
            ],
            'transferType' => 'QR_P2M',
            'merchant_details' => [
                'merchant_code' => '1',
                'merchant_account' => $mobile,
            ],
        ];

        $this->newLine();
        $this->line('Webhook payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        $this->newLine();

        if (!$this->confirm('Send this webhook to confirm deposit?', true)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        // Get payment gateway and confirm deposit
        $gateway = app(PaymentGatewayInterface::class);
        
        $this->info('Processing deposit...');
        $result = $gateway->confirmDeposit($payload);

        if ($result) {
            $this->info('✅ Deposit confirmed successfully!');
            $this->info('Check:');
            $this->line('  1. Laravel logs for BalanceUpdated event dispatch');
            $this->line('  2. Pusher debug console for balance.updated event');
            $this->line('  3. Browser console for Echo events');
            $this->line('  4. Toast notification in UI');
            
            // Show updated balance
            $user->refresh();
            $balance = $user->wallet ? $user->wallet->balanceFloat : 0;
            $this->newLine();
            $this->info("New wallet balance: ₱{$balance}");
            
            return self::SUCCESS;
        }

        $this->error('❌ Deposit confirmation failed. Check logs for details.');
        return self::FAILURE;
    }
}
