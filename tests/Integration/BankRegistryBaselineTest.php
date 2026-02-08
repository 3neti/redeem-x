<?php

use LBHurtado\MoneyIssuer\Support\BankRegistry;

/**
 * BASELINE TESTS - DO NOT MODIFY
 * These tests capture the known-good state BEFORE banks.json rationalization.
 * All these tests MUST pass before proceeding with refactoring.
 */
it('baseline: payment-gateway can load banks', function () {
    $registry = app(BankRegistry::class);
    expect($registry->all())->toBeArray()->toHaveCount(146);
});

it('baseline: EMI restrictions work', function () {
    $registry = app(BankRegistry::class);
    $rails = $registry->getAllowedRails('GXCHPHM2XXX');
    expect($rails)->toBe(['INSTAPAY']);
});

it('baseline: traditional banks have no rail restrictions', function () {
    $registry = app(BankRegistry::class);
    $rails = $registry->getAllowedRails('BOPIPHMM'); // BPI
    // Empty array means no restrictions (supports all rails)
    expect($rails)->toBeEmpty();
});

it('baseline: can retrieve EMI list', function () {
    $registry = app(BankRegistry::class);
    $emis = $registry->getEMIs();
    expect($emis)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->not->toBeEmpty();
    expect($emis->has('GXCHPHM2XXX'))->toBeTrue(); // GCash
});

it('baseline: can find bank by SWIFT code', function () {
    $registry = app(BankRegistry::class);
    $bank = $registry->find('GXCHPHM2XXX');
    expect($bank)->toBeArray()->toHaveKey('full_name');
    expect($bank['full_name'])->toContain('GCash');
});
