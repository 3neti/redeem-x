<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\ModelInput\Enums\InputType;
use LBHurtado\ModelInput\Models\Input;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

// Helper function
function createVoucherWithInputs(): Voucher
{
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });

    return $voucher;
}

test('model input package is loaded and autoloaded', function () {
    expect(class_exists(Input::class))->toBeTrue()
        ->and(enum_exists(InputType::class))->toBeTrue()
        ->and(trait_exists(\LBHurtado\ModelInput\Traits\HasInputs::class))->toBeTrue();
});

test('voucher has inputs trait', function () {
    $voucher = createVoucherWithInputs();

    expect(method_exists($voucher, 'inputs'))->toBeTrue()
        ->and(method_exists($voucher, 'setInput'))->toBeTrue()
        ->and(method_exists($voucher, 'isValidInput'))->toBeTrue();
});

test('voucher can have inputs relationship', function () {
    $voucher = createVoucherWithInputs();

    $voucher->inputs()->create(['name' => 'name', 'value' => 'Juan Dela Cruz']);
    $voucher->inputs()->create(['name' => 'email', 'value' => 'juan@example.com']);

    expect($voucher->inputs()->count())->toBe(2)
        ->and($voucher->inputs->pluck('name')->toArray())->toContain('name', 'email');
});

test('voucher can set input', function () {
    $voucher = createVoucherWithInputs();

    $voucher->setInput('name', 'Juan Dela Cruz');

    expect($voucher->inputs()->where('name', 'name')->exists())->toBeTrue();

    $input = $voucher->inputs()->where('name', 'name')->first();
    expect($input->value)->toBe('Juan Dela Cruz');
});

test('voucher can set mobile input with normalization', function () {
    $voucher = createVoucherWithInputs();

    $voucher->setInput('mobile', '09171234567');

    $input = $voucher->inputs()->where('name', 'mobile')->first();
    expect($input->value)->toBe('639171234567'); // E.164 without +
});

test('voucher can set input using enum', function () {
    $voucher = createVoucherWithInputs();

    $voucher->setInput(InputType::NAME, 'Juan Dela Cruz');

    expect($voucher->inputs()->where('name', InputType::NAME->value)->exists())->toBeTrue();
});

test('voucher can access input via magic property', function () {
    $voucher = createVoucherWithInputs();

    $voucher->inputs()->create(['name' => 'name', 'value' => 'Juan Dela Cruz']);
    $voucher->inputs()->create(['name' => 'email', 'value' => 'juan@example.com']);

    expect($voucher->name)->toBe('Juan Dela Cruz')
        ->and($voucher->email)->toBe('juan@example.com');
});

test('voucher can set input via magic property', function () {
    $voucher = createVoucherWithInputs();

    $voucher->name = 'Juan Dela Cruz';
    $voucher->email = 'juan@example.com';

    $nameInput = $voucher->inputs()->where('name', 'name')->first();
    $emailInput = $voucher->inputs()->where('name', 'email')->first();

    expect($nameInput->value)->toBe('Juan Dela Cruz')
        ->and($emailInput->value)->toBe('juan@example.com');
});

test('validates input names against enum', function () {
    $voucher = createVoucherWithInputs();

    expect($voucher->isValidInput('name', 'Juan Dela Cruz'))->toBeTrue()
        ->and($voucher->isValidInput('email', 'juan@example.com'))->toBeTrue()
        ->and($voucher->isValidInput('invalid_input', 'value'))->toBeFalse();
});

test('validates input values using rules', function () {
    $voucher = createVoucherWithInputs();

    expect($voucher->isValidInput('email', 'juan@example.com'))->toBeTrue()
        ->and($voucher->isValidInput('email', 'not-an-email'))->toBeFalse()
        ->and($voucher->isValidInput('mobile', '09171234567'))->toBeTrue()
        ->and($voucher->isValidInput('mobile', 'invalid'))->toBeFalse();
});

test('throws exception for invalid input', function () {
    $voucher = createVoucherWithInputs();

    expect(fn () => $voucher->setInput('invalid_input', 'value'))
        ->toThrow(Exception::class, 'Input name is not valid');
});

test('voucher can be found by input', function () {
    $voucher = createVoucherWithInputs();
    $voucher->setInput('name', 'Juan Dela Cruz');

    $found = Voucher::findByInput('name', 'Juan Dela Cruz');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($voucher->id);
});

test('voucher can be found using dynamic finder', function () {
    $voucher = createVoucherWithInputs();
    $voucher->setInput('email', 'juan@example.com');

    $found = Voucher::findByEmail('juan@example.com');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($voucher->id);
});

test('voucher can get input using input method', function () {
    $voucher = createVoucherWithInputs();
    $voucher->setInput('name', 'Juan Dela Cruz');

    expect($voucher->input('name'))->toBe('Juan Dela Cruz');
});

test('inputs table exists in database', function () {
    expect(\Schema::hasTable('inputs'))->toBeTrue();
});

test('inputs table has required columns', function () {
    $columns = \Schema::getColumnListing('inputs');

    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('name', $columns))->toBeTrue()
        ->and(in_array('value', $columns))->toBeTrue()
        ->and(in_array('model_type', $columns))->toBeTrue()
        ->and(in_array('model_id', $columns))->toBeTrue();
});
