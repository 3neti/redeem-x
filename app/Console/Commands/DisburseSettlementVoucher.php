<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DisbursementService;
use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Disburse Settlement Voucher Command
 *
 * Manually trigger disbursement for a settlement voucher to the owner's default bank account.
 *
 * Usage:
 *   php artisan voucher:disburse VRUK
 *   php artisan voucher:disburse VRUK --amount=50
 *   php artisan voucher:disburse VRUK --bank-account=uuid-123
 */
class DisburseSettlementVoucher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voucher:disburse 
                            {code : The voucher code to disburse}
                            {--amount= : Amount to disburse (default: full paid amount)}
                            {--bank-account= : Bank account ID (default: user\'s default account)}
                            {--rail= : Settlement rail (INSTAPAY/PESONET, default: auto)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disburse settlement voucher funds to owner\'s bank account';

    /**
     * Execute the console command.
     */
    public function handle(DisbursementService $disbursementService): int
    {
        $code = strtoupper($this->argument('code'));

        // Find voucher
        $voucher = Voucher::with('owner')->where('code', $code)->first();

        if (! $voucher) {
            $this->error("Voucher '{$code}' not found.");

            return self::FAILURE;
        }

        if (! $voucher->owner) {
            $this->error("Voucher '{$code}' has no owner.");

            return self::FAILURE;
        }

        $owner = $voucher->owner;

        $this->info("Voucher: {$voucher->code}");
        $this->info("Owner: {$owner->name} ({$owner->email})");
        $this->info('Paid Total: ₱'.number_format($voucher->getPaidTotal(), 2));
        $this->info('Remaining: ₱'.number_format($voucher->getRemaining(), 2));

        // Check if voucher has received payments yet
        if ($voucher->getPaidTotal() == 0) {
            $this->error('Voucher has no confirmed payments yet.');
            $this->info("\nTo disburse, you must first:");
            $this->info('  1. Generate a payment QR code');
            $this->info('  2. Mark payment as done');
            $this->info('  3. Confirm the payment');

            return self::FAILURE;
        }

        // Access cash entity dynamically
        $cash = $voucher->cash;
        if (! $cash || ! $cash->wallet) {
            $this->error("Voucher has no wallet. This shouldn't happen after payment confirmation.");

            return self::FAILURE;
        }

        $cashBalance = (float) $cash->balanceFloat;
        $this->info('Cash Balance: ₱'.number_format($cashBalance, 2));

        // Determine amount to disburse
        $amount = $this->option('amount')
            ? (float) $this->option('amount')
            : $cashBalance;

        if ($amount <= 0) {
            $this->error('Amount must be greater than 0.');

            return self::FAILURE;
        }

        if ($amount > $cashBalance) {
            $this->error("Amount ₱{$amount} exceeds cash balance ₱{$cashBalance}.");

            return self::FAILURE;
        }

        // Get bank account
        $bankAccountId = $this->option('bank-account');
        $bankAccount = $bankAccountId
            ? $owner->getBankAccountById($bankAccountId)
            : $owner->getDefaultBankAccount();

        if (! $bankAccount) {
            $this->error('No bank account found. User must have at least one saved bank account.');
            $this->info('Available bank accounts:');
            foreach ($owner->getBankAccounts() as $acc) {
                $this->line("  - {$acc['id']}: {$acc['bank_code']} {$acc['account_number']} ".
                           ($acc['is_default'] ? '(default)' : ''));
            }

            return self::FAILURE;
        }

        $this->info("Bank Account: {$bankAccount['bank_code']} {$bankAccount['account_number']}");

        // Get settlement rail
        $rail = $this->option('rail') ? strtoupper($this->option('rail')) : null;
        if ($rail && ! in_array($rail, ['INSTAPAY', 'PESONET'])) {
            $this->error('Invalid rail. Must be INSTAPAY or PESONET.');

            return self::FAILURE;
        }

        $autoRail = $disbursementService->determineSettlementRail($amount, $rail);
        $this->info("Settlement Rail: {$autoRail}");

        // Confirm
        if (! $this->confirm("Disburse ₱{$amount} to {$bankAccount['bank_code']} {$bankAccount['account_number']}?", true)) {
            $this->info('Disbursement cancelled.');

            return self::SUCCESS;
        }

        // Perform disbursement
        $this->info('Initiating disbursement...');

        $result = $disbursementService->disburse(
            $owner,
            $amount,
            [
                'bank_code' => $bankAccount['bank_code'],
                'account_number' => $bankAccount['account_number'],
            ],
            $rail,
            [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'payment_type' => 'settlement',
                'initiated_via' => 'console',
            ]
        );

        if ($result['success']) {
            $this->info('✓ Disbursement successful!');
            $this->info("Transaction ID: {$result['transaction_id']}");
            if (isset($result['reference_id'])) {
                $this->info("Reference ID: {$result['reference_id']}");
            }
        } else {
            $this->error("✗ Disbursement failed: {$result['message']}");
            if (isset($result['error'])) {
                $this->error("Error: {$result['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
