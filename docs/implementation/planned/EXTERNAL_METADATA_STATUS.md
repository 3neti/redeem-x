# External Metadata Implementation - Sanity Check & Status

**Date:** 2025-11-18  
**Package:** lbhurtado/voucher  
**Status:** ✅ **COMPLETE** and **TESTED**

## Overview

The `lbhurtado/voucher` package now has **generic external metadata support** for integrating with external systems (games, loyalty programs, etc.) without requiring QuestPay-specific modifications.

## Implementation Summary

### ✅ What's Implemented

#### 1. **External Metadata Storage** (`metadata->external`)
- **Trait:** `HasExternalMetadata`
- **DTO:** `ExternalMetadataData`
- **Fields:**
  - `external_id` - External system's unique identifier
  - `external_type` - Type/category of external entity
  - `reference_id` - Reference to external record
  - `user_id` - External user/player/member ID
  - `custom` - Additional custom fields (flexible array)

**Usage:**
```php
$voucher->external_metadata = [
    'external_id' => 'MMS-001',
    'external_type' => 'game',
    'reference_id' => 'CH-005',
    'user_id' => 'CONT-042',
    'custom' => [
        'sequence' => 3,
        'challenge_type' => 'treasure_hunt',
    ],
];
$voucher->save();

// Read
$gameId = $voucher->external_metadata->external_id;
$sequence = $voucher->external_metadata->getCustom('sequence');
```

**Query Scopes:**
```php
// Find vouchers by external field
Voucher::whereExternal('external_id', 'MMS-001')->get();
Voucher::whereExternal('external_type', 'game')->get();

// Find vouchers by multiple values
Voucher::whereExternalIn('external_id', ['MMS-001', 'MMS-002'])->get();
```

#### 2. **Timing Tracking** (`metadata->timing`)
- **Trait:** `HasVoucherTiming`
- **DTO:** `VoucherTimingData`
- **Fields:**
  - `clicked_at` - ISO-8601 timestamp when voucher link was clicked
  - `started_at` - ISO-8601 timestamp when redemption wizard opened
  - `submitted_at` - ISO-8601 timestamp when redemption was submitted
  - `duration_seconds` - Time taken from start to submit (calculated)

**Usage:**
```php
// Track events
$voucher->trackClick();              // Idempotent - won't overwrite
$voucher->trackRedemptionStart();
$voucher->trackRedemptionSubmit();   // Auto-calculates duration

// Read
$duration = $voucher->getRedemptionDuration(); // seconds
$clickedAt = $voucher->timing->getClickedAt(); // Carbon instance

// Check status
if ($voucher->hasBeenClicked()) { /* ... */ }
if ($voucher->hasRedemptionStarted()) { /* ... */ }
```

#### 3. **Validation Results** (`metadata->validation_results`)
- **Trait:** `HasValidationResults`
- **DTO:** `ValidationResultsData`
- **Sub-DTOs:**
  - `LocationValidationResultData` (validated, distance_meters, should_block)
  - `TimeValidationResultData` (within_window, within_duration, duration_seconds, should_block)

**Usage:**
```php
// Store validation results
use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;

$voucher->storeValidationResults(
    location: LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 500.0,
        'should_block' => false,
    ]),
    time: TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 45,
        'should_block' => false,
    ])
);
$voucher->save();

// Read
$passed = $voucher->passedValidation();
$blocked = $voucher->wasBlockedByValidation();
$locationResult = $voucher->getLocationValidationResult();
$failedChecks = $voucher->getFailedValidationTypes();
$summary = $voucher->getValidationSummary();

// Query scopes
Voucher::passedValidation()->get();
Voucher::failedValidation()->get();
Voucher::blockedByValidation()->get();
```

## Metadata Structure

All data is stored in the existing `metadata` JSON column:

```json
{
  "instructions": { /* ... existing voucher instructions ... */ },
  "disbursement": { /* ... disbursement data ... */ },
  "external": {
    "external_id": "MMS-001",
    "external_type": "game",
    "reference_id": "CH-005",
    "user_id": "CONT-042",
    "custom": {
      "sequence": 3,
      "challenge_type": "treasure_hunt"
    }
  },
  "timing": {
    "clicked_at": "2025-11-18T10:15:58+08:00",
    "started_at": "2025-11-18T10:29:14+08:00",
    "submitted_at": "2025-11-18T10:29:15+08:00",
    "duration_seconds": 1
  },
  "validation_results": {
    "location": {
      "validated": true,
      "distance_meters": 500,
      "should_block": false
    },
    "time": {
      "within_window": true,
      "within_duration": true,
      "duration_seconds": 45,
      "should_block": false
    },
    "passed": true,
    "blocked": false
  }
}
```

## Test Results

✅ **All tests passing** - Run: `php artisan test:voucher-traits`

- External metadata storage/retrieval
- Timing tracking (click → start → submit → duration calculation)
- Validation results storage
- Query scopes (whereExternal, passedValidation, etc.)
- Proper DTO hydration (Spatie LaravelData)

## Design Decisions

### ✅ Generic Structure (Not QuestPay-Specific)
The implementation uses a **flexible, reusable structure**:
- Standard fields: `external_id`, `external_type`, `reference_id`, `user_id`
- Custom fields: Any additional data goes in `custom` array
- **No QuestPay coupling** - can be used for loyalty programs, event ticketing, membership systems, etc.

### ✅ No Database Changes Required
Uses existing `metadata` JSON column - no migrations needed. This keeps the voucher package pristine and backwards-compatible.

### ✅ Type-Safe DTOs
Leverages Spatie LaravelData for:
- Validation
- Type safety
- IDE autocomplete
- Array/DTO conversion

### ✅ Query-Friendly
JSON path queries work on all databases (SQLite, MySQL, PostgreSQL):
```php
Voucher::whereExternal('external_id', 'MMS-001')
```

## What's Still Missing (API Integration)

From the original critical path, here's what's **NOT YET DONE**:

### 🔴 Still Missing (Critical)

1. **API: Accept external_metadata on voucher creation**
   - [ ] Update `VoucherGenerationRequest` to validate `external_metadata` field
   - [ ] Modify `VoucherGenerationController` to set metadata on generated vouchers
   - [ ] Update API response to include metadata

2. **API: Enhanced webhook payload**
   - [ ] Include timing data in webhook
   - [ ] Include validation results in webhook
   - [ ] Include external metadata in webhook
   - [ ] Include collected input data (GPS, photos, signatures, text)

3. **API: Bulk voucher generation with unique metadata**
   - [ ] POST `/api/vouchers/bulk-create` endpoint
   - [ ] Accept array of voucher configs with unique external_metadata per voucher

4. **API: Voucher status endpoint**
   - [ ] GET `/api/vouchers/{code}/status` endpoint
   - [ ] Include external_metadata, timing, validation results

5. **UI: Track timing events**
   - [ ] Call `trackClick()` when voucher link is clicked
   - [ ] Call `trackRedemptionStart()` when redemption wizard opens
   - [ ] Call `trackRedemptionSubmit()` on redemption submission

## Recommendation

The **package implementation is solid and ready**. Next steps:

1. **Commit the package changes** (lbhurtado/voucher monorepo)
2. **Move to API integration** in the host app (redeem-x):
   - Phase 2: API accepts external_metadata on voucher generation
   - Phase 3: Track timing events in UI
   - Phase 4: Store validation results in redemption flow
   - Phase 5: Enhance webhook payload with all data
   - Phase 6: Add status endpoint

## Files Modified (Package)

**lbhurtado/voucher package:**
- `src/Models/Voucher.php` - Added traits
- `src/Traits/HasExternalMetadata.php` - ✅ Complete
- `src/Traits/HasVoucherTiming.php` - ✅ Complete
- `src/Traits/HasValidationResults.php` - ✅ Complete
- `src/Data/ExternalMetadataData.php` - ✅ Complete
- `src/Data/VoucherTimingData.php` - ✅ Complete
- `src/Data/LocationValidationResultData.php` - ✅ Complete
- `src/Data/TimeValidationResultData.php` - ✅ Complete
- `src/Data/ValidationResultsData.php` - ✅ Complete

**Host app (redeem-x):**
- `app/Console/Commands/TestVoucherTraits.php` - Test command (can be deleted after commit)

## Next Action

Should we **commit the package changes** now and proceed with API integration?
