<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InstructionItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'index',
        'type',
        'price',
        'currency',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function priceHistory()
    {
        return $this->hasMany(InstructionItemPriceHistory::class);
    }

    public function getAmountProduct(User $customer): int
    {
        // Future: VIP discounts, volume pricing
        return $this->price;
    }

    public function getMetaProduct(): ?array
    {
        return [
            'type' => $this->type,
            'title' => $this->meta['title'] ?? ucfirst($this->type),
            'description' => $this->meta['description'] ?? "Charge for {$this->type} instruction",
        ];
    }

    /**
     * Get the category from meta, defaulting to 'other'
     */
    public function getCategoryAttribute(): string
    {
        return $this->meta['category'] ?? 'other';
    }

    public static function attributesFromIndex(string $index, array $overrides = []): array
    {
        return array_merge([
            'index'    => $index,
            'name'     => Str::of($index)->afterLast('.')->headline()->toString(),
            'type'     => Str::of($index)->explode('.')[1] ?? 'general',
            'price'    => 0,
            'currency' => 'PHP',
            'meta'     => [],
        ], $overrides);
    }
}
