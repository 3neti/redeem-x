<?php

namespace App\Console\Commands;

use App\Models\TopUp;
use Illuminate\Console\Command;

class ConfirmTopUp extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'topup:confirm {reference? : The top-up reference number}
                            {--all : Confirm all pending top-ups}
                            {--payment-id= : Optional payment ID to set}';

    /**
     * The console command description.
     */
    protected $description = 'Confirm pending top-up(s) and credit user wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            return $this->confirmAll();
        }

        $reference = $this->argument('reference');

        if (! $reference) {
            // Show interactive list of pending top-ups
            $pending = TopUp::where('payment_status', 'PENDING')
                ->with('user')
                ->latest()
                ->get();

            if ($pending->isEmpty()) {
                $this->info('No pending top-ups found.');

                return 0;
            }

            $this->table(
                ['Reference', 'User', 'Amount', 'Created'],
                $pending->map(fn ($t) => [
                    $t->reference_no,
                    $t->user->email,
                    '₱'.number_format($t->amount, 2),
                    $t->created_at->diffForHumans(),
                ])
            );

            $reference = $this->ask('Enter reference number to confirm');
        }

        $topUp = TopUp::where('reference_no', $reference)->first();

        if (! $topUp) {
            $this->error("Top-up not found: {$reference}");

            return 1;
        }

        if ($topUp->isPaid()) {
            $this->warn("Top-up already confirmed: {$reference}");

            return 0;
        }

        $paymentId = $this->option('payment-id') ?: 'MANUAL-'.now()->timestamp;

        $topUp->markAsPaid($paymentId);
        // Console command: no initiatedBy (system operation)
        $topUp->user->creditWalletFromTopUp($topUp, null);

        $this->info("✅ Top-up confirmed: {$topUp->reference_no}");
        $this->line("   User: {$topUp->user->email}");
        $this->line('   Amount: ₱'.number_format($topUp->amount, 2));
        $this->line('   New Balance: ₱'.number_format($topUp->user->fresh()->balanceFloat, 2));

        return 0;
    }

    /**
     * Confirm all pending top-ups.
     */
    protected function confirmAll()
    {
        $pending = TopUp::where('payment_status', 'PENDING')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending top-ups found.');

            return 0;
        }

        $this->warn("Found {$pending->count()} pending top-up(s)");

        if (! $this->confirm('Confirm all?', true)) {
            $this->info('Cancelled.');

            return 0;
        }

        $confirmed = 0;

        foreach ($pending as $topUp) {
            $paymentId = 'MANUAL-'.now()->timestamp.'-'.$topUp->id;
            $topUp->markAsPaid($paymentId);
            // Console command: no initiatedBy (system operation)
            $topUp->user->creditWalletFromTopUp($topUp, null);
            $confirmed++;

            $this->line("✅ {$topUp->reference_no} - {$topUp->user->email} - ₱".number_format($topUp->amount, 2));
        }

        $this->info("Confirmed {$confirmed} top-up(s)");

        return 0;
    }
}
