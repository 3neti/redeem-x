<?php

namespace App\Observers;

use App\Models\InstructionItem;
use App\Models\InstructionItemPriceHistory;
use Illuminate\Support\Facades\Auth;

class InstructionItemObserver
{
    /**
     * Handle the InstructionItem "updating" event.
     * 
     * Log price changes before the model is saved.
     */
    public function updating(InstructionItem $item): void
    {
        // Check if price is being changed
        if ($item->isDirty('price')) {
            $oldPrice = $item->getOriginal('price');
            $newPrice = $item->price;
            
            // Only log if there's an actual change
            if ($oldPrice !== $newPrice) {
                InstructionItemPriceHistory::create([
                    'instruction_item_id' => $item->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'currency' => $item->currency ?? 'PHP',
                    'changed_by' => Auth::id(),
                    'effective_at' => now(),
                ]);
            }
        }
    }
}
