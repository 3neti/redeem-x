<?php

namespace App\Console\Commands;

use LBHurtado\Voucher\Models\Voucher;
use App\Services\DisbursementStatusService;
use Illuminate\Console\Command;

class UpdateDisbursementStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'disbursement:update-status 
                            {--voucher= : Update specific voucher by code}
                            {--limit=100 : Maximum number of vouchers to check in batch mode}
                            {--show-response : Show detailed API response}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update pending disbursement statuses from payment gateway';
    
    /**
     * Create a new command instance.
     */
    public function __construct(
        protected DisbursementStatusService $service
    ) {
        parent::__construct();
    }
    
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $voucherCode = $this->option('voucher');
        
        if ($voucherCode) {
            return $this->updateSingle($voucherCode);
        }
        
        return $this->updateBatch();
    }
    
    /**
     * Update status for a single voucher
     */
    protected function updateSingle(string $code): int
    {
        $this->info("🔍 Checking status for voucher: {$code}");
        $this->newLine();
        
        $voucher = Voucher::where('code', $code)->first();
        
        if (!$voucher) {
            $this->error("❌ Voucher not found: {$code}");
            return 1;
        }
        
        // Check if voucher has disbursement
        $disbursement = $voucher->metadata['disbursement'] ?? null;
        if (!$disbursement) {
            $this->warn("⚠️  Voucher has no disbursement data");
            return 1;
        }
        
        // Display current status
        $this->table(
            ['Field', 'Value'],
            [
                ['Voucher Code', $voucher->code],
                ['Transaction ID', $disbursement['transaction_id'] ?? 'N/A'],
                ['Current Status', $disbursement['status'] ?? 'Unknown'],
                ['Gateway', $disbursement['gateway'] ?? 'Unknown'],
                ['Amount', ($disbursement['currency'] ?? 'PHP') . ' ' . ($disbursement['amount'] ?? '0')],
                ['Recipient', $disbursement['recipient_name'] ?? 'N/A'],
            ]
        );
        
        $this->newLine();
        $this->info("📡 Querying payment gateway...");
        
        try {
            // Show verbose API response if requested
            if ($this->option('show-response')) {
                $gateway = app(\LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface::class);
                $apiResult = $gateway->checkDisbursementStatus($disbursement['transaction_id']);
                
                $this->newLine();
                $this->line("<fg=cyan>🔍 API Response:</>");
                $this->line("   Status: <fg=yellow>" . ($apiResult['status'] ?? 'N/A') . "</>");
                
                if (!empty($apiResult['raw'])) {
                    $this->line("   Raw Data:");
                    $this->line("   " . str_replace("\n", "\n   ", json_encode($apiResult['raw'], JSON_PRETTY_PRINT)));
                }
                
                $this->newLine();
            }
            
            $updated = $this->service->updateVoucherStatus($voucher);
            
            if ($updated) {
                // Refresh voucher to get new status
                $voucher->refresh();
                $newDisbursement = $voucher->metadata['disbursement'] ?? [];
                $newStatus = $newDisbursement['status'] ?? 'Unknown';
                
                $this->newLine();
                $this->info("✅ Status updated successfully!");
                $this->line("   Old Status: <fg=yellow>{$disbursement['status']}</>");
                $this->line("   New Status: <fg=green>{$newStatus}</>");
                
                // Show enriched data if available
                if (isset($newDisbursement['settled_at'])) {
                    $this->newLine();
                    $this->line("<fg=cyan>📊 Enriched Data:</>");
                    
                    if (isset($newDisbursement['settled_at'])) {
                        $settledAt = \Carbon\Carbon::parse($newDisbursement['settled_at'])->format('Y-m-d H:i:s');
                        $this->line("   Settled At: <fg=green>{$settledAt}</>");
                    }
                    
                    if (isset($newDisbursement['reference_number'])) {
                        $this->line("   Reference #: {$newDisbursement['reference_number']}");
                    }
                    
                    if (isset($newDisbursement['fees'])) {
                        $feeAmount = $newDisbursement['fees']['amount'] ?? 0;
                        $feeCurrency = $newDisbursement['fees']['currency'] ?? 'PHP';
                        $feeFormatted = number_format($feeAmount / 100, 2);
                        $this->line("   Fees: {$feeCurrency} {$feeFormatted}");
                    }
                    
                    if (isset($newDisbursement['status_history']) && count($newDisbursement['status_history']) > 1) {
                        $this->line("   Status Changes: " . count($newDisbursement['status_history']));
                    }
                }
            } else {
                $this->newLine();
                $this->comment("ℹ️  No update needed (status unchanged or already final)");
            }
            
            return 0;
            
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
    
    /**
     * Update status for multiple pending vouchers
     */
    protected function updateBatch(): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("🔍 Checking up to {$limit} pending disbursements...");
        $this->newLine();
        
        // Get count first
        $pendingCount = Voucher::query()
            ->whereNotNull('redeemed_at')
            ->whereNotNull('metadata->disbursement')
            ->whereIn('metadata->disbursement->status', ['pending', 'processing'])
            ->count();
        
        if ($pendingCount === 0) {
            $this->comment("ℹ️  No pending disbursements found");
            return 0;
        }
        
        $this->line("   Found <fg=cyan>{$pendingCount}</> pending disbursement(s)");
        $this->line("   Will check up to <fg=cyan>{$limit}</> voucher(s)");
        $this->newLine();
        
        // Show progress bar
        $bar = $this->output->createProgressBar(min($limit, $pendingCount));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Starting...');
        $bar->start();
        
        try {
            // Hook into the service to update progress bar
            $updated = 0;
            $checked = 0;
            
            $vouchers = Voucher::query()
                ->whereNotNull('redeemed_at')
                ->whereNotNull('metadata->disbursement')
                ->whereIn('metadata->disbursement->status', ['pending', 'processing'])
                ->limit($limit)
                ->get();
            
            foreach ($vouchers as $voucher) {
                $checked++;
                $bar->setMessage("Checking {$voucher->code}...");
                
                try {
                    if ($this->service->updateVoucherStatus($voucher)) {
                        $updated++;
                        $bar->setMessage("Updated {$voucher->code}");
                    } else {
                        $bar->setMessage("No change for {$voucher->code}");
                    }
                } catch (\Throwable $e) {
                    $bar->setMessage("Error: {$voucher->code}");
                    // Continue with next voucher
                }
                
                $bar->advance();
            }
            
            $bar->setMessage('Complete!');
            $bar->finish();
            
            $this->newLine(2);
            
            // Summary
            $this->info("✅ Batch update complete!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Pending', $pendingCount],
                    ['Checked', $checked],
                    ['Updated', $updated],
                    ['Unchanged', $checked - $updated],
                ]
            );
            
            if ($updated > 0) {
                $this->newLine();
                $this->line("   <fg=green>→ {$updated} voucher(s) had status changes</>");
            }
            
            if ($pendingCount > $limit) {
                $this->newLine();
                $this->comment("   ℹ️  {$pendingCount} total pending. Run again to check more.");
            }
            
            return 0;
            
        } catch (\Throwable $e) {
            $this->newLine(2);
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
}
