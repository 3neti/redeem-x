<?php

use App\Models\InstructionItem;
use App\Models\InstructionItemPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('instruction item can be created', function () {
    $item = InstructionItem::create([
        'name' => 'Email Address',
        'index' => 'feedback.email',
        'type' => 'feedback',
        'price' => 100,
        'currency' => 'PHP',
        'meta' => ['description' => 'Email notification'],
    ]);

    expect($item)->toBeInstanceOf(InstructionItem::class)
        ->and($item->name)->toBe('Email Address')
        ->and($item->index)->toBe('feedback.email')
        ->and($item->price)->toBe(100);
});

test('instruction item attributes can be generated from index', function () {
    $attributes = InstructionItem::attributesFromIndex('feedback.email', [
        'price' => 100,
        'meta' => ['description' => 'Test'],
    ]);

    expect($attributes)
        ->toHaveKey('index')
        ->and($attributes['index'])->toBe('feedback.email')
        ->and($attributes['name'])->toBe('Email')
        ->and($attributes['type'])->toBe('email') // Second part of dot notation
        ->and($attributes['price'])->toBe(100)
        ->and($attributes['currency'])->toBe('PHP');
});

test('price changes are automatically logged to history', function () {
    $item = InstructionItem::create([
        'name' => 'Test Item',
        'index' => 'test.item',
        'type' => 'test',
        'price' => 100,
    ]);

    expect($item->priceHistory()->count())->toBe(0);

    // Update price
    $item->update(['price' => 200]);

    expect($item->priceHistory()->count())->toBe(1);

    $history = $item->priceHistory()->first();
    expect($history->old_price)->toBe(100)
        ->and($history->new_price)->toBe(200)
        ->and($history->changed_by)->toBe($this->user->id);
});

test('meta is cast to array', function () {
    $item = InstructionItem::create([
        'name' => 'Test',
        'index' => 'test.index',
        'type' => 'test',
        'price' => 100,
        'meta' => ['key' => 'value'],
    ]);

    expect($item->meta)->toBeArray()
        ->and($item->meta['key'])->toBe('value');
});

test('seeded instruction items exist', function () {
    $this->seed(\Database\Seeders\InstructionItemSeeder::class);

    $count = InstructionItem::count();

    expect($count)->toBeGreaterThan(0)
        ->and(InstructionItem::where('index', 'cash.amount')->exists())->toBeTrue()
        ->and(InstructionItem::where('index', 'feedback.email')->exists())->toBeTrue();
});

test('instruction item has price history relationship', function () {
    $item = InstructionItem::factory()->create(['price' => 100]);

    $item->update(['price' => 200]);

    expect($item->priceHistory)->toHaveCount(1)
        ->and($item->priceHistory->first())->toBeInstanceOf(InstructionItemPriceHistory::class);
});
