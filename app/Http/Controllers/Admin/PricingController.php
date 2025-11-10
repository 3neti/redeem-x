<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(): Response
    {
        $items = InstructionItem::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'index' => $item->index,
                'type' => $item->type,
                'price' => $item->price,
                'price_formatted' => '₱'.number_format($item->price / 100, 2),
                'currency' => $item->currency,
                'meta' => $item->meta,
                'updated_at' => $item->updated_at->toIso8601String(),
            ]);

        return Inertia::render('admin/pricing/Index', [
            'items' => $items,
        ]);
    }

    public function edit(InstructionItem $item): Response
    {
        return Inertia::render('admin/pricing/Edit', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'index' => $item->index,
                'type' => $item->type,
                'price' => $item->price,
                'price_formatted' => number_format($item->price / 100, 2),
                'currency' => $item->currency,
                'meta' => $item->meta,
                'label' => $item->meta['label'] ?? '',
                'description' => $item->meta['description'] ?? '',
            ],
            'history' => $item->priceHistory()
                ->with('changer:id,name,email')
                ->latest('effective_at')
                ->limit(10)
                ->get()
                ->map(fn ($history) => [
                    'id' => $history->id,
                    'old_price' => '₱'.number_format($history->old_price / 100, 2),
                    'new_price' => '₱'.number_format($history->new_price / 100, 2),
                    'changed_by' => $history->changer?->name ?? 'System',
                    'reason' => $history->reason,
                    'effective_at' => $history->effective_at->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request, InstructionItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'reason' => 'required|string|min:3|max:255',
            'label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $newPriceCentavos = (int) ($validated['price'] * 100);
        $oldPrice = $item->price; // Capture old price before updating

        // Update meta
        $meta = $item->meta ?? [];
        if (isset($validated['label'])) {
            $meta['label'] = $validated['label'];
        }
        if (isset($validated['description'])) {
            $meta['description'] = $validated['description'];
        }
        $item->meta = $meta;

        // Update price
        $item->price = $newPriceCentavos;
        $item->save();

        // Create price history with reason and user if price changed
        if ($oldPrice !== $newPriceCentavos) {
            $item->priceHistory()->create([
                'old_price' => $oldPrice,
                'new_price' => $newPriceCentavos,
                'currency' => $item->currency,
                'changed_by' => auth()->id(),
                'reason' => $validated['reason'],
                'effective_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.pricing.index')
            ->with('success', "Pricing updated for {$item->name}");
    }
}
