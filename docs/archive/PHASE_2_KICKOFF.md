# Phase 2: API Endpoints & Validation Implementation - Kickoff

**Status:** Ready to Start  
**Date:** 2025-01-17  
**Prerequisites:** Phase 1 Complete ✅

## Phase 1 Recap

✅ **Completed:**
- 12 DTOs for external metadata, timing, and validation
- 3 Traits (HasExternalMetadata, HasVoucherTiming, HasValidationResults)
- Enhanced webhook payload with all new data structures
- 127 tests passing
- Documentation complete

## Phase 2 Overview

Phase 2 integrates Phase 1 DTOs into the redemption flow and creates API endpoints for external systems.

### Goals
1. **Validation Integration** - Use validation instructions during redemption
2. **API Endpoints** - External systems can manage vouchers programmatically
3. **Backward Compatibility** - Existing flows work unchanged

### Tasks

#### Task 2.1: Location Validation Flow (1.5 days)
**Integrate geo-fence validation into redemption controller**

**Files to Modify:**
- Controller for voucher redemption
- Request validation

**Implementation:**
```php
// In redemption submit handler
if ($locationValidation = $voucher->instructions->validation?->location) {
    $userLocation = $request->input('location');
    
    $locationResult = $locationValidation->validateLocation(
        $userLocation['latitude'],
        $userLocation['longitude']
    );
    
    // Store result
    $voucher->storeValidationResults($locationResult);
    $voucher->save();
    
    // Block if failed
    if ($locationResult->should_block) {
        return back()->withErrors(['location' => '...']);
    }
}
```

**Tests Needed:**
- Location validation passes
- Location validation fails (within radius)
- Location validation fails (outside radius, block mode)
- Location validation fails (outside radius, warn mode)
- No location validation configured

#### Task 2.2: Time Validation Flow (1.5 days)
**Integrate time window and duration validation**

**Implementation:**
```php
// Check time window
if ($timeWindow = $voucher->instructions->validation?->time?->window) {
    if (!$timeWindow->isWithinWindow()) {
        return back()->withErrors(['time' => '...']);
    }
}

// Check duration limit
if ($timeValidation?->limit_minutes) {
    $duration = $voucher->getRedemptionDuration();
    if ($timeValidation->exceedsDurationLimit($duration)) {
        return back()->withErrors(['time' => '...']);
    }
}
```

**Tests Needed:**
- Time window validation passes
- Time window validation fails
- Duration limit validation passes
- Duration limit validation fails
- Cross-midnight time windows
- No time validation configured

#### Task 2.3: API Endpoints (2 days)
**Create RESTful API endpoints for external systems**

**Endpoints to Create:**

1. **GET /api/vouchers/{code}** - Get voucher details
   ```json
   {
     "code": "ABC-1234",
     "status": "active",
     "external_metadata": {...},
     "timing": {...},
     "validation_results": {...}
   }
   ```

2. **POST /api/vouchers/{code}/external** - Set external metadata
   ```json
   {
     "external_id": "quest-123",
     "external_type": "questpay",
     "user_id": "player-789",
     "custom": {"level": 10}
   }
   ```

3. **POST /api/vouchers/{code}/timing/click** - Track click event
4. **POST /api/vouchers/{code}/timing/start** - Track redemption start
5. **POST /api/vouchers/{code}/timing/submit** - Track submission

6. **GET /api/vouchers** - Query vouchers with filters
   ```
   ?external_type=questpay
   &user_id=player-789
   &status=active
   ```

7. **POST /api/vouchers/bulk-create** - Generate multiple vouchers
   ```json
   {
     "campaign_id": 1,
     "vouchers": [
       {
         "mobile": "09171234567",
         "external_metadata": {...}
       }
     ]
   }
   ```

**Files to Create:**
- `app/Http/Controllers/Api/VoucherApiController.php`
- `app/Http/Requests/Api/SetExternalMetadataRequest.php`
- `app/Http/Requests/Api/BulkCreateVouchersRequest.php`
- `app/Http/Resources/VoucherResource.php`
- `routes/api/vouchers.php` (or add to existing)
- `tests/Feature/Api/VoucherApiTest.php`

**Authentication:**
- Use Sanctum API tokens
- Middleware: `auth:sanctum`

**Rate Limiting:**
- Apply `throttle:api` middleware
- Consider separate limits for bulk operations

### Implementation Order

1. **Start with Task 2.3 (API Endpoints)** - Most impactful for external integrations
   - Create controller structure
   - Implement GET /api/vouchers/{code}
   - Implement POST /api/vouchers/{code}/external
   - Implement timing endpoints
   - Implement query endpoint
   - Implement bulk create

2. **Then Task 2.1 (Location Validation)**
   - Find redemption controller
   - Add location validation logic
   - Write tests

3. **Finally Task 2.2 (Time Validation)**
   - Add time validation logic to redemption
   - Write tests

### Success Criteria

✅ All API endpoints working and tested  
✅ Location validation blocks/warns appropriately  
✅ Time validation enforces windows and limits  
✅ Backward compatible - existing redemptions work  
✅ All tests passing (target: 50+ new tests)  
✅ API documentation created  

### Next Steps

Ready to start implementation. Shall we begin with Task 2.3 (API Endpoints)?

---

**Phase 1:** ✅ Complete  
**Phase 2:** 🚀 Ready to Start
