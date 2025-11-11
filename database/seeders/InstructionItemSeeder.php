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
            InstructionItem::updateOrCreate(
                ['index' => $index],
                InstructionItem::attributesFromIndex($index, [
                    'price' => $data['price'],
                    'currency' => 'PHP',
                    'meta' => [
                        'description' => $data['description'] ?? null,
                        'label' => $data['label'] ?? null,
                        'category' => $data['category'] ?? 'other',
                    ],
                ])
            );
        }
    }
}
