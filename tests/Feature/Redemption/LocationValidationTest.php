<?php

declare(strict_types=1);

/**
 * Location Validation Feature Tests - SKIPPED
 *
 * These tests were designed to verify location validation through ProcessRedemption::run(),
 * but ProcessRedemption no longer validates location directly. Location validation has moved
 * to the Unified Validation Gateway (VoucherRedemptionService → RedemptionGuard → LocationSpecification).
 *
 * Unit-level location spec tests: tests/Unit/Specifications/LocationSpecificationTest.php
 */

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('allows redemption when user is within location radius')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('allows redemption in warn mode even when outside radius')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('blocks redemption when user is outside location radius in block mode')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('allows redemption in block mode when within radius')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('allows redemption when no location validation configured')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('blocks redemption when location validation required but no location data provided')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('blocks redemption when location data has invalid format')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');

test('calculates correct distance and stores in validation results')
    ->skip('Location validation moved from ProcessRedemption to Unified Validation Gateway');
