<?php

use App\Models\InstructionItem;
use App\Repositories\InstructionItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new InstructionItemRepository;

    // Create test items
    InstructionItem::create([
        'name' => 'Email',
        'index' => 'feedback.email',
        'type' => 'feedback',
        'price' => 100,
    ]);

    InstructionItem::create([
        'name' => 'Mobile',
        'index' => 'feedback.mobile',
        'type' => 'feedback',
        'price' => 180,
    ]);

    InstructionItem::create([
        'name' => 'Signature',
        'index' => 'inputs.fields.signature',
        'type' => 'inputs',
        'price' => 280,
    ]);
});

test('repository can get all instruction items', function () {
    $items = $this->repository->all();

    expect($items)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($items->count())->toBeGreaterThanOrEqual(3);
});

test('repository can find item by index', function () {
    $item = $this->repository->findByIndex('feedback.email');

    expect($item)->toBeInstanceOf(InstructionItem::class)
        ->and($item->index)->toBe('feedback.email')
        ->and($item->price)->toBe(100);
});

test('repository returns null for non-existent index', function () {
    $item = $this->repository->findByIndex('non.existent');

    expect($item)->toBeNull();
});

test('repository can find items by multiple indices', function () {
    $items = $this->repository->findByIndices([
        'feedback.email',
        'feedback.mobile',
    ]);

    expect($items)->toHaveCount(2)
        ->and($items->pluck('index')->toArray())
        ->toContain('feedback.email', 'feedback.mobile');
});

test('repository can get items by type', function () {
    $feedbackItems = $this->repository->allByType('feedback');

    expect($feedbackItems)->toHaveCount(2)
        ->and($feedbackItems->every(fn ($item) => $item->type === 'feedback'))->toBeTrue();
});

test('repository can calculate total charge', function () {
    $total = $this->repository->totalCharge([
        'feedback.email',
        'feedback.mobile',
    ]);

    expect($total)->toBe(280); // 100 + 180
});

test('repository can get descriptions for indices', function () {
    InstructionItem::where('index', 'feedback.email')
        ->update(['meta' => ['description' => 'Email notification']]);

    $descriptions = $this->repository->descriptionsFor(['feedback.email']);

    expect($descriptions)->toBeArray()
        ->and($descriptions['feedback.email'])->toBe('Email notification');
});
