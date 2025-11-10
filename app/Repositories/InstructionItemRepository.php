<?php

namespace App\Repositories;

use App\Models\InstructionItem;
use Illuminate\Support\Collection;

class InstructionItemRepository
{
    public function all(): Collection
    {
        return InstructionItem::all();
    }

    public function findByIndex(string $index): ?InstructionItem
    {
        return InstructionItem::where('index', $index)->first();
    }

    public function findByIndices(array $indices): Collection
    {
        return InstructionItem::whereIn('index', $indices)->get();
    }

    public function allByType(string $type): Collection
    {
        return InstructionItem::where('type', $type)->get();
    }

    public function totalCharge(array $indices): int
    {
        return $this->findByIndices($indices)->sum('price');
    }

    public function descriptionsFor(array $indices): array
    {
        return $this->findByIndices($indices)->mapWithKeys(function ($item) {
            return [$item->index => $item->meta['description'] ?? ''];
        })->toArray();
    }
}
