<?php

use App\Models\InstructionItem;
use App\Models\User;
use App\Repositories\InstructionItemRepository;
use App\Services\InstructionCostEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

uses(RefreshDatabase::class);

function makeInstructions(array $overrides = []): VoucherInstructionsData
{
    return VoucherInstructionsData::from(array_merge([
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create test pricing items
    InstructionItem::create([
        'name' => 'Cash Amount',
        'index' => 'cash.amount',
        'type' => 'cash',
        'price' => 2000,
    ]);
    
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
    
    $this->evaluator = new InstructionCostEvaluator(
        new InstructionItemRepository()
    );
});

test('evaluator charges for non-empty string values', function () {
    $instructions = makeInstructions([
        'feedback' => ['email' => 'test@example.com'],
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    expect($charges)->toHaveCount(2) // cash.amount + feedback.email
        ->and($charges->pluck('item.index')->toArray())
        ->toContain('cash.amount', 'feedback.email');
});

test('evaluator does not charge for empty strings', function () {
    $instructions = makeInstructions([
        'feedback' => ['email' => ''],
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    expect($charges->pluck('item.index')->toArray())
        ->not->toContain('feedback.email');
});

test('evaluator excludes count field', function () {
    InstructionItem::create([
        'name' => 'Count',
        'index' => 'count',
        'type' => 'generation',
        'price' => 100,
    ]);
    
    $instructions = makeInstructions([
        'count' => 10,
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    expect($charges->pluck('item.index')->toArray())
        ->not->toContain('count');
});

test('evaluator excludes mask field', function () {
    InstructionItem::create([
        'name' => 'Mask',
        'index' => 'mask',
        'type' => 'generation',
        'price' => 100,
    ]);
    
    $instructions = makeInstructions([
        'mask' => '****',
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    expect($charges->pluck('item.index')->toArray())
        ->not->toContain('mask');
});

test('evaluator returns correct price for each item', function () {
    $instructions = makeInstructions([
        'feedback' => ['email' => 'test@example.com'],
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    $emailCharge = $charges->firstWhere('item.index', 'feedback.email');
    
    expect($emailCharge['price'])->toBe(100);
});

test('evaluator includes item metadata', function () {
    InstructionItem::where('index', 'feedback.email')
        ->update(['meta' => ['label' => 'Email Address']]);
    
    $instructions = makeInstructions([
        'feedback' => ['email' => 'test@example.com'],
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    $emailCharge = $charges->firstWhere('item.index', 'feedback.email');
    
    expect($emailCharge['label'])->toBe('Email Address');
});

test('evaluator handles multiple chargeable items', function () {
    $instructions = makeInstructions([
        'feedback' => [
            'email' => 'test@example.com',
            'mobile' => '09171234567',
        ],
    ]);
    
    $charges = $this->evaluator->evaluate($this->user, $instructions);
    
    expect($charges)->toHaveCount(3) // cash.amount + email + mobile
        ->and($charges->sum('price'))->toBe(2280); // 2000 + 100 + 180
});
