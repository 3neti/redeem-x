<?php

namespace App\Console\Commands;

use App\Models\InstructionItem;
use App\Models\User;
use App\Services\RevenueCollectionService;
use Illuminate\Console\Command;
use Brick\Money\Money;

class RevenueCollectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'revenue:collect
                            {--preview : Preview without executing}
                            {--item= : Collect from specific InstructionItem ID}
                            {--min= : Minimum balance to collect (PHP)}
                            {--destination= : Destination email (overrides default/configured)}
                            {--stats : Show revenue statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect revenue from InstructionItem wallets to configured destinations';

    /**
     * Execute the console command.
     */
    public function handle(RevenueCollectionService $service): int
    {
        // Show statistics
        if ($this->option('stats')) {
            return $this->showStatistics($service);
        }
        
        $preview = $this->option('preview');
        $itemId = $this->option('item');
        $minAmount = $this->option('min') ? (float) $this->option('min') : null;
        $destinationEmail = $this->option('destination');
        
        // Resolve destination override
        $destinationOverride = null;
        if ($destinationEmail) {
            $destinationOverride = User::where('email', $destinationEmail)->first();
            if (!$destinationOverride) {
                $this->error("User not found: {$destinationEmail}");
                return 1;
            }
            $this->info("Destination override: {$destinationOverride->name} <{$destinationOverride->email}>");
        }
        
        // Preview mode
        if ($preview) {
            return $this->showPreview($service, $minAmount);
        }
        
        // Collect from specific item
        if ($itemId) {
            return $this->collectFromItem($service, (int) $itemId, $destinationOverride);
        }
        
        // Collect from all
        return $this->collectFromAll($service, $minAmount, $destinationOverride);
    }
    
    /**
     * Show revenue statistics.
     */
    protected function showStatistics(RevenueCollectionService $service): int
    {
        $stats = $service->getStatistics();
        
        $this->info('\n📊 Revenue Statistics\n');
        
        // Pending
        $this->components->twoColumnDetail(
            '<fg=yellow>Pending Revenue</>',
            $stats['pending']['formatted_total']
        );
        $this->components->twoColumnDetail(
            '  Items with balance',
            (string) $stats['pending']['count']
        );
        
        $this->newLine();
        
        // All-time
        $this->components->twoColumnDetail(
            '<fg=green>All-Time Collected</>',
            $stats['all_time']['formatted_total']
        );
        $this->components->twoColumnDetail(
            '  Total collections',
            (string) $stats['all_time']['collections_count']
        );
        
        // Last collection
        if ($stats['last_collection']) {
            $this->newLine();
            $this->components->twoColumnDetail(
                '<fg=blue>Last Collection</>',
                $stats['last_collection']['amount']
            );
            $this->components->twoColumnDetail(
                '  From',
                $stats['last_collection']['item_name']
            );
            $this->components->twoColumnDetail(
                '  To',
                $stats['last_collection']['destination']
            );
            $this->components->twoColumnDetail(
                '  When',
                $stats['last_collection']['collected_at']
            );
        }
        
        return 0;
    }
    
    /**
     * Show preview of pending revenue.
     */
    protected function showPreview(RevenueCollectionService $service, ?float $minAmount): int
    {
        $pending = $service->getPendingRevenue($minAmount);
        
        if ($pending->isEmpty()) {
            $this->info('No revenue to collect.');
            return 0;
        }
        
        $this->table(
            ['ID', 'Name', 'Index', 'Balance', 'Destination', 'Type'],
            $pending->map(fn($p) => [
                $p['id'],
                $p['name'],
                $p['index'],
                $p['formatted_balance'],
                $p['destination']['name'],
                $p['destination']['is_default'] ? 'Default' : 'Configured',
            ])
        );
        
        $total = $pending->sum('balance');
        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green>Total pending</>',
            Money::of($total, 'PHP')->formatTo('en_PH')
        );
        $this->newLine();
        $this->info('Run without --preview to collect.');
        
        return 0;
    }
    
    /**
     * Collect from specific InstructionItem.
     */
    protected function collectFromItem(
        RevenueCollectionService $service,
        int $itemId,
        $destinationOverride
    ): int {
        $item = InstructionItem::find($itemId);
        if (!$item) {
            $this->error("InstructionItem #{$itemId} not found.");
            return 1;
        }
        
        if ($item->balanceFloat <= 0) {
            $this->warn("InstructionItem '{$item->name}' has no balance to collect.");
            return 0;
        }
        
        $destination = $destinationOverride ?? $service->getPendingRevenue()->firstWhere('id', $itemId);
        $destName = $destination['destination']['name'] ?? 'Unknown';
        
        if (!$this->confirm("Collect {$item->balanceFloat} PHP from '{$item->name}' to {$destName}?", true)) {
            $this->info('Cancelled.');
            return 0;
        }
        
        try {
            $collection = $service->collect($item, $destinationOverride);
            $this->components->info(
                "✓ Collected {$collection->formatted_amount} from {$item->name}"
            );
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            return 1;
        }
    }
    
    /**
     * Collect from all InstructionItems.
     */
    protected function collectFromAll(
        RevenueCollectionService $service,
        ?float $minAmount,
        $destinationOverride
    ): int {
        $pending = $service->getPendingRevenue($minAmount);
        
        if ($pending->isEmpty()) {
            $this->info('No revenue to collect.');
            return 0;
        }
        
        $total = $pending->sum('balance');
        $count = $pending->count();
        
        $this->table(
            ['Name', 'Balance', 'Destination'],
            $pending->map(fn($p) => [
                $p['name'],
                $p['formatted_balance'],
                $p['destination']['name'],
            ])
        );
        
        $this->newLine();
        $formattedTotal = Money::of($total, 'PHP')->formatTo('en_PH');
        
        if (!$this->confirm("Collect {$formattedTotal} from {$count} items?", false)) {
            $this->info('Cancelled.');
            return 0;
        }
        
        $this->info('Collecting revenue...');
        $collections = $service->collectAll($minAmount, $destinationOverride);
        
        if ($collections->isEmpty()) {
            $this->warn('No revenue collected.');
            return 0;
        }
        
        $totalCollected = $collections->sum('amount') / 100;
        $this->newLine();
        $this->components->info(
            "✓ Collected " . Money::of($totalCollected, 'PHP')->formatTo('en_PH') . 
            " from {$collections->count()} items"
        );
        
        return 0;
    }
}
