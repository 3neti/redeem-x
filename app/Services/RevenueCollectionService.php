<?php

namespace App\Services;

use App\Models\InstructionItem;
use App\Models\RevenueCollection;
use App\Models\User;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Brick\Money\Money;

/**
 * Revenue Collection Service
 * 
 * Handles collection of fees from InstructionItem wallets with flexible destination routing.
 * Each InstructionItem can specify its own revenue destination (polymorphic), or fall back
 * to the default revenue/system wallet.
 */
class RevenueCollectionService
{
    public function __construct(
        protected SystemUserResolverService $systemUserResolver
    ) {}

    /**
     * Get all InstructionItems with non-zero balances and their destinations.
     * 
     * @param float|null $minAmount Minimum balance in PHP (optional filter)
     * @return Collection
     */
    public function getPendingRevenue(?float $minAmount = null): Collection
    {
        $minCentavos = $minAmount ? (int)($minAmount * 100) : 0;
        
        return InstructionItem::query()
            ->whereHas('wallet', function($q) use ($minCentavos) {
                $q->where('balance', '>', $minCentavos);
            })
            ->with(['wallet', 'revenueDestination'])
            ->get()
            ->filter(function($item) {
                $balance = (float) $item->balanceFloat;
                return $balance > 0;
            })
            ->map(function($item) {
                $destination = $this->resolveDestination($item);
                $balance = (float) $item->balanceFloat;
                
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'index' => $item->index,
                    'balance' => $balance,
                    'formatted_balance' => Money::of($balance, 'PHP')->formatTo('en_PH'),
                    'transaction_count' => $item->wallet->transactions()->count(),
                    'destination' => [
                        'type' => class_basename($destination),
                        'id' => $destination->getKey(),
                        'name' => $this->getDestinationName($destination),
                        'is_default' => !$item->revenueDestination,
                    ],
                ];
            });
    }

    /**
     * Collect revenue from a specific InstructionItem.
     * 
     * @param InstructionItem $item Source of revenue
     * @param Wallet|null $destinationOverride Optional destination override
     * @param string|null $notes Optional notes
     * @return RevenueCollection
     * @throws \InvalidArgumentException
     */
    public function collect(
        InstructionItem $item,
        ?Wallet $destinationOverride = null,
        ?string $notes = null
    ): RevenueCollection {
        $balance = (float) $item->balanceFloat;
        
        if ($balance <= 0) {
            throw new \InvalidArgumentException("InstructionItem '{$item->name}' has no balance to collect");
        }
        
        // Resolve destination (override > configured > default)
        $destination = $destinationOverride ?? $this->resolveDestination($item);
        
        DB::beginTransaction();
        try {
            // Transfer from InstructionItem wallet to destination wallet
            // Use transferFloat since $balance is in PHP (major units), not centavos
            $transfer = $item->transferFloat($destination, $balance);
            
            // Record collection
            $collection = RevenueCollection::create([
                'instruction_item_id' => $item->id,
                'collected_by_user_id' => auth()->id() ?? $destination->getKey(),
                'destination_type' => get_class($destination),
                'destination_id' => $destination->getKey(),
                'amount' => abs($transfer->deposit->amount), // Use deposit (positive amount)
                'transfer_uuid' => $transfer->uuid,
                'notes' => $notes,
            ]);
            
            DB::commit();
            
            Log::info('[RevenueCollection] Collected', [
                'collection_id' => $collection->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'amount_php' => $balance,
                'destination_type' => class_basename($destination),
                'destination_id' => $destination->getKey(),
                'destination_name' => $this->getDestinationName($destination),
                'is_override' => $destinationOverride !== null,
                'is_configured' => $item->revenueDestination !== null,
            ]);
            
            return $collection;
            
        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('[RevenueCollection] Failed', [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Collect from all InstructionItems with balance.
     * 
     * @param float|null $minAmount Minimum balance in PHP to collect
     * @param Wallet|null $destinationOverride Override destination for all items
     * @return Collection Collection of RevenueCollection records
     */
    public function collectAll(
        ?float $minAmount = null,
        ?Wallet $destinationOverride = null
    ): Collection {
        $minCentavos = $minAmount ? (int)($minAmount * 100) : 0;
        
        $items = InstructionItem::query()
            ->whereHas('wallet', function($q) use ($minCentavos) {
                $q->where('balance', '>', $minCentavos);
            })
            ->get();
        
        $collections = collect();
        $errors = collect();
        
        foreach ($items as $item) {
            try {
                $collections->push($this->collect($item, $destinationOverride));
            } catch (\Throwable $e) {
                $errors->push([
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        if ($errors->isNotEmpty()) {
            Log::warning('[RevenueCollection] Some collections failed', [
                'errors' => $errors->toArray(),
            ]);
        }
        
        return $collections;
    }

    /**
     * Get total pending revenue across all InstructionItems.
     * 
     * @return float Total in PHP
     */
    public function getTotalPendingRevenue(): float
    {
        $total = InstructionItem::query()
            ->whereHas('wallet', function($q) {
                $q->where('balance', '>', 0);
            })
            ->with('wallet')
            ->get()
            ->sum(fn($item) => (float) $item->balanceFloat);
        
        return $total;
    }

    /**
     * Get revenue statistics for reporting.
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $pending = $this->getPendingRevenue();
        $totalPending = $pending->sum('balance');
        
        $allTimeCollected = RevenueCollection::sum('amount') / 100;
        $collectionsCount = RevenueCollection::count();
        
        $lastCollection = RevenueCollection::with(['instructionItem', 'destination'])
            ->latest()
            ->first();
        
        return [
            'pending' => [
                'count' => $pending->count(),
                'total' => $totalPending,
                'formatted_total' => Money::of($totalPending, 'PHP')->formatTo('en_PH'),
                'by_item' => $pending->toArray(),
            ],
            'all_time' => [
                'total_collected' => $allTimeCollected,
                'formatted_total' => Money::of($allTimeCollected, 'PHP')->formatTo('en_PH'),
                'collections_count' => $collectionsCount,
            ],
            'last_collection' => $lastCollection ? [
                'id' => $lastCollection->id,
                'item_name' => $lastCollection->instructionItem->name,
                'amount' => $lastCollection->formatted_amount,
                'destination' => $lastCollection->destination_name,
                'collected_at' => $lastCollection->created_at->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Resolve destination wallet for an InstructionItem.
     * 
     * Priority:
     * 1. InstructionItem's configured revenueDestination
     * 2. Default revenue user (from config)
     * 3. System user (fallback)
     * 
     * @param InstructionItem $item
     * @return Wallet
     */
    protected function resolveDestination(InstructionItem $item): Wallet
    {
        // 1. Check if item has configured destination
        if ($item->revenueDestination && $item->revenueDestination instanceof Wallet) {
            Log::debug('[RevenueCollection] Using configured destination', [
                'item_id' => $item->id,
                'destination_type' => class_basename($item->revenueDestination),
                'destination_id' => $item->revenueDestination->getKey(),
            ]);
            return $item->revenueDestination;
        }
        
        // 2. Try default revenue user from config
        $revenueUser = $this->getRevenueUser();
        if ($revenueUser) {
            Log::debug('[RevenueCollection] Using default revenue user', [
                'item_id' => $item->id,
                'revenue_user_email' => $revenueUser->email,
            ]);
            return $revenueUser;
        }
        
        // 3. Fallback to system user
        $systemUser = $this->systemUserResolver->resolve();
        Log::debug('[RevenueCollection] Using system user as fallback', [
            'item_id' => $item->id,
        ]);
        
        return $systemUser;
    }

    /**
     * Get the configured revenue user from config.
     * 
     * @return User|null
     */
    protected function getRevenueUser(): ?User
    {
        $modelClass = config('account.revenue_user.model');
        $identifier = config('account.revenue_user.identifier');
        $column = config('account.revenue_user.identifier_column', 'email');
        
        if (!$modelClass || !$identifier) {
            return null;
        }
        
        return $modelClass::where($column, $identifier)->first();
    }

    /**
     * Get display name for a destination wallet.
     * 
     * @param Wallet $destination
     * @return string
     */
    protected function getDestinationName(Wallet $destination): string
    {
        return match (true) {
            $destination instanceof User => $destination->name ?? $destination->email,
            method_exists($destination, 'getName') => $destination->getName(),
            default => class_basename($destination) . ' #' . $destination->getKey(),
        };
    }
}
