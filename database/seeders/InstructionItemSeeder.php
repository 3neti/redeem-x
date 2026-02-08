<?php

namespace Database\Seeders;

use App\Models\InstructionItem;
use Illuminate\Database\Seeder;

class InstructionItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = config('redeem.pricelist', []);

        foreach ($items as $index => $data) {
            $meta = [
                'description' => $data['description'] ?? null,
                'label' => $data['label'] ?? null,
                'category' => $data['category'] ?? 'other',
            ];

            // Add deprecated flag if present
            if (isset($data['deprecated']) && $data['deprecated']) {
                $meta['deprecated'] = true;
                $meta['deprecated_reason'] = $data['deprecated_reason'] ?? 'No longer in use';
            }

            InstructionItem::updateOrCreate(
                ['index' => $index],
                InstructionItem::attributesFromIndex($index, [
                    'price' => $data['price'],
                    'currency' => 'PHP',
                    'meta' => $meta,
                ])
            );
        }
    }
}
