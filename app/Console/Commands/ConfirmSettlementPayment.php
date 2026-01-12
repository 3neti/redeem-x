<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentRequest;
use App\Services\DisbursementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Wallet\Actions\TopupWalletAction;

/**
 * Confirm Settlement Payment Command
 * 
 * Confirm a pending payment for a settlement voucher, with optional auto-disbursement.
 * 
 * Usage:
 *   php artisan voucher:confirm C5YG
 *   php artisan voucher:confirm C5YG --disburse
 *   php artisan voucher:confirm C5YG --disburse --bank-account=uuid
 *   php artisan voucher:confirm C5YG --transfer (just confirm, keep in wallet)
 */
class ConfirmSettlementPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voucher:confirm 
                            {code : The voucher code}
                            {--disburse : Auto-disburse to bank account after confirmation}
                            {--transfer : Transfer to wallet only (default behavior)}
                            {--bank-account= : Bank account ID (default: user\'s default account)}
                            {--rail= : Settlement rail for disbursement (INSTAPAY/PESONET, default: auto)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confirm pending settlement payment with optional auto-disbursement';

    /**
     * Execute the console command.
     */
    public function handle(DisbursementService $disbursementService): int
    {
        $code = strtoupper($this->argument('code'));
        
        // Find latest awaiting payment request for this voucher
        $paymentRequest = PaymentRequest::with('voucher.owner')
            ->whereHas('voucher', fn($q) => $q->where('code', $code))
            ->where('status', 'awaiting_confirmation')
            ->latest()
            ->first();
        
        if (!$paymentRequest) {
            $this->error("No pending payment found for voucher '{$code}'.");
            $this->info("Payment must be in 'awaiting_confirmation' status.");
            return self::FAILURE;
        }
        
        $voucher = $paymentRequest->voucher;
        $owner = $voucher->owner;
        $amount = $paymentRequest->getAmountInMajorUnits();
        
        $this->info("Payment Request: #{$paymentRequest->id}");
        $this->info("Reference: {$paymentRequest->reference_id}");
        $this->info("Voucher: {$voucher->code}");
        $this->info("Owner: {$owner->name} ({$owner->email})");
        $this->info("Amount: ₱" . number_format($amount, 2));
        $this->info("Current Paid Total: ₱" . number_format($voucher->getPaidTotal(), 2));
        $this->newLine();
        
        // Check if user wants to disburse
        $shouldDisburse = $this->option('disburse');
        $bankAccount = null;
        
        if ($shouldDisburse) {
            // Get bank account
            $bankAccountId = $this->option('bank-account');
            $bankAccount = $bankAccountId 
                ? $owner->getBankAccountById($bankAccountId)
                : $owner->getDefaultBankAccount();
            
            if (!$bankAccount) {
                $this->error("No bank account found.");
                $this->info("Available bank accounts:");
                foreach ($owner->getBankAccounts() as $acc) {
                    $this->line("  - {$acc['id']}: {$acc['bank_code']} {$acc['account_number']} " . 
                               ($acc['is_default'] ? '(default)' : ''));
                }
                return self::FAILURE;
            }
            
            $rail = $this->option('rail');
            $autoRail = $disbursementService->determineSettlementRail($amount, $rail);
            
            $this->info("Disbursement Plan:");
            $this->info("  Bank: {$bankAccount['bank_code']}");
            $this->info("  Account: {$bankAccount['account_number']}");
            $this->info("  Rail: {$autoRail}");
            $this->newLine();
        } else {
            $this->info("Mode: Transfer to wallet only (no disbursement)");
            $this->newLine();
        }
        
        // Confirm
        $action = $shouldDisburse ? 'confirm payment and disburse' : 'confirm payment';
        if (!$this->confirm("Proceed to {$action}?", true)) {
            $this->info("Operation cancelled.");
            return self::SUCCESS;
        }
        
        try {
            DB::beginTransaction();
            
            // Create cash entity if it doesn't exist (first payment)
            if (!$voucher->cash) {
                $this->info("Creating cash entity for first payment...");
                $cash = Cash::create([
                    'amount' => 0,
                    'currency' => 'PHP',
                ]);
                $voucher->cashable()->associate($cash);
                $voucher->save();
            } else {
                $cash = $voucher->cash;
            }
            
            $balanceBefore = $cash->balanceFloat;
            
            // Transfer to voucher's cash wallet
            $this->info("Transferring ₱{$amount} to voucher wallet...");
            $transfer = TopupWalletAction::run($cash, $amount);
            
            // Add metadata
            $transfer->withdraw->update([
                'meta' => array_merge($transfer->withdraw->meta ?? [], [
                    'flow' => 'pay',
                    'voucher_code' => $voucher->code,
                    'payment_id' => $paymentRequest->reference_id,
                    'confirmed_by' => 'console',
                ]),
            ]);
            
            $transfer->deposit->update([
                'meta' => array_merge($transfer->deposit->meta ?? [], [
                    'flow' => 'pay',
                    'voucher_code' => $voucher->code,
                    'payment_id' => $paymentRequest->reference_id,
                    'confirmed_by' => 'console',
                ]),
            ]);
            
            $balanceAfter = $cash->fresh()->balanceFloat;
            
            // Mark payment request as confirmed
            $paymentRequest->markAsConfirmed();
            
            DB::commit();
            
            $this->info("✓ Payment confirmed successfully!");
            $this->info("  Transfer UUID: {$transfer->uuid}");
            $this->info("  Balance: ₱{$balanceBefore} → ₱{$balanceAfter}");
            $this->info("  New Paid Total: ₱" . number_format($voucher->fresh()->getPaidTotal(), 2));
            
            // Auto-disbursement if requested
            if ($shouldDisburse && $bankAccount) {
                $this->newLine();
                $this->info("Initiating disbursement...");
                
                $result = $disbursementService->disburse(
                    $owner,
                    $amount,
                    [
                        'bank_code' => $bankAccount['bank_code'],
                        'account_number' => $bankAccount['account_number'],
                    ],
                    $this->option('rail'),
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_code' => $voucher->code,
                        'payment_type' => 'settlement',
                        'initiated_via' => 'console',
                    ]
                );
                
                if ($result['success']) {
                    $this->info("✓ Disbursement successful!");
                    $this->info("  Transaction ID: {$result['transaction_id']}");
                    if (isset($result['reference_id'])) {
                        $this->info("  Reference ID: {$result['reference_id']}");
                    }
                } else {
                    $this->error("✗ Disbursement failed: {$result['message']}");
                    $this->warn("Payment was confirmed, but funds remain in voucher wallet.");
                    if (isset($result['error'])) {
                        $this->error("  Error: {$result['error']}");
                    }
                }
            }
            
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            DB::rollBack();
            
            $this->error("Failed to confirm payment: {$e->getMessage()}");
            Log::error('[ConfirmPaymentCommand] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::FAILURE;
        }
    }
}
