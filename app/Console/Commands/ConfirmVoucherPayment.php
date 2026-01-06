<?php

namespace App\Console\Commands;

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfirmVoucherPayment extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'voucher:confirm-payment 
                            {voucher_code : The voucher code to receive payment}
                            {amount : Payment amount in PHP}
                            {--payment-id= : Optional payment ID/reference}
                            {--payer= : Optional payer mobile/email}';

    /**
     * The console command description.
     */
    protected $description = 'Manually confirm a payment to a settlement/payable voucher';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $voucherCode = $this->argument('voucher_code');
        $amount = (float) $this->argument('amount');
        $paymentId = $this->option('payment-id') ?: 'MANUAL-' . now()->timestamp;
        $payer = $this->option('payer');

        // Find voucher
        $voucher = Voucher::with('owner')->where('code', $voucherCode)->first();

        if (!$voucher) {
            $this->error("Voucher not found: {$voucherCode}");
            return 1;
        }

        if (!$voucher->owner) {
            $this->error("Voucher has no owner");
            return 1;
        }

        // Validate voucher can accept payment
        if (!$voucher->canAcceptPayment()) {
            $this->error("This voucher cannot accept payments (state: {$voucher->state->value})");
            return 1;
        }

        // Validate amount
        $remaining = $voucher->getRemaining();
        if ($amount > $remaining) {
            $this->error("Amount exceeds remaining balance. Maximum: ₱{$remaining}");
            return 1;
        }

        // Show confirmation
        $this->info("Payment Confirmation");
        $this->line("==================");
        $this->line("Voucher: {$voucher->code}");
        $this->line("Owner: {$voucher->owner->email}");
        $this->line("Target: ₱" . number_format($voucher->target_amount, 2));
        $this->line("Paid: ₱" . number_format($voucher->getPaidTotal(), 2));
        $this->line("Remaining: ₱" . number_format($remaining, 2));
        $this->newLine();
        $this->line("Payment Amount: ₱" . number_format($amount, 2));
        $this->line("Payment ID: {$paymentId}");
        if ($payer) {
            $this->line("Payer: {$payer}");
        }
        $this->newLine();

        if (!$this->confirm('Confirm this payment?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        try {
            DB::beginTransaction();

            // Get cash entity (voucher's wallet)
            $cash = $voucher->cash;
            if (!$cash || !$cash->wallet) {
                $this->error("Voucher has no cash entity or wallet");
                return 1;
            }

            // Credit cash entity's wallet (not owner's personal wallet)
            $oldBalance = $cash->wallet->balanceFloat;
            
            $cash->wallet->deposit($amount * 100, [ // Convert to minor units
                'flow' => 'pay',
                'voucher_code' => $voucher->code,
                'payment_id' => $paymentId,
                'payer' => $payer,
                'confirmed_by' => 'console',
            ]);

            $newBalance = $cash->wallet->fresh()->balanceFloat;

            // Check if voucher is now fully paid and should close
            $newPaidTotal = $voucher->getPaidTotal();
            if ($newPaidTotal >= $voucher->target_amount && $voucher->state->value === 'active') {
                // Note: You may need to add a close() method to the voucher model
                // For now, just log it
                $this->warn("⚠️  Voucher has reached target amount. Consider closing it.");
            }

            DB::commit();

            $this->info("✅ Payment confirmed successfully");
            $this->line("   Voucher: {$voucher->code}");
            $this->line("   Amount: ₱" . number_format($amount, 2));
            $this->line("   Voucher Wallet: ₱" . number_format($oldBalance, 2) . " → ₱" . number_format($newBalance, 2));
            $this->line("   New Paid Total: ₱" . number_format($newPaidTotal, 2) . " / ₱" . number_format($voucher->target_amount, 2));
            $this->line("   Remaining: ₱" . number_format($voucher->fresh()->getRemaining(), 2));

            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed to confirm payment: " . $e->getMessage());
            return 1;
        }
    }
}
