<?php

use LBHurtado\Contact\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Propaganistas\LaravelPhone\PhoneNumber;

uses(RefreshDatabase::class);

test('contact package is loaded and autoloaded', function () {
    expect(class_exists(Contact::class))->toBeTrue();
});

test('contact model can be instantiated', function () {
    $contact = new Contact();
    
    expect($contact)->toBeInstanceOf(Contact::class);
});

test('contact can be created with mobile number', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);
    
    expect($contact->exists)->toBeTrue()
        ->and($contact->mobile)->toBeString()
        ->and($contact->country)->toBe('PH');
});

test('contact uses default country when not specified', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
    ]);
    
    expect($contact->country)->toBe('PH'); // Default from config
});

test('contact auto-generates bank account on creation', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);
    
    expect($contact->bank_account)->not->toBeNull()
        ->and($contact->bank_account)->toContain(':')
        ->and($contact->bank_code)->toBeString()
        ->and($contact->account_number)->toBeString();
});

test('contact can have custom bank account', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'bank_account' => 'BPI:1234567890',
    ]);
    
    expect($contact->bank_account)->toBe('BPI:1234567890')
        ->and($contact->bank_code)->toBe('BPI')
        ->and($contact->account_number)->toBe('1234567890');
});

test('contact can have name stored in meta', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'name' => 'Juan Dela Cruz',
    ]);
    
    expect($contact->name)->toBe('Juan Dela Cruz');
});

test('contact can have email stored in meta', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'email' => 'juan@example.com',
    ]);
    
    expect($contact->email)->toBe('juan@example.com');
});

test('contact can have address stored in meta', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'address' => '123 Main St, Manila',
    ]);
    
    expect($contact->address)->toBe('123 Main St, Manila');
});

test('contact can have birth date stored in meta', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'birth_date' => '1990-01-01',
    ]);
    
    expect($contact->birth_date)->toBe('1990-01-01');
});

test('contact can have gross monthly income stored in meta', function () {
    $contact = Contact::create([
        'mobile' => '09171234567',
        'gross_monthly_income' => '50000',
    ]);
    
    expect($contact->gross_monthly_income)->toBe('50000');
});

test('contact can be created from phone number object', function () {
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    
    $contact = Contact::fromPhoneNumber($phoneNumber);
    
    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->exists)->toBeTrue()
        ->and($contact->country)->toBe('PH');
});

test('contact table exists in database', function () {
    expect(\Schema::hasTable('contacts'))->toBeTrue();
});

test('contact has required columns', function () {
    $columns = \Schema::getColumnListing('contacts');
    
    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('mobile', $columns))->toBeTrue()
        ->and(in_array('country', $columns))->toBeTrue()
        ->and(in_array('bank_account', $columns))->toBeTrue()
        ->and(in_array('meta', $columns))->toBeTrue();
});
