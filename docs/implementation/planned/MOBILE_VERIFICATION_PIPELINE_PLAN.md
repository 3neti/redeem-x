# Mobile Verification as a Policy-Driven Pipeline Step

## Problem
LTFRB requires that during voucher redemption, the submitted mobile number be validated against a beneficiary database. This is not a simple format check — it's a business rule that varies per voucher. Current mobile validation (`MobileSpecification`) only does 1:1 matching ("is this the pre-configured recipient?"). We need a driver-based system where each voucher declares its own verification policy.

## Current Architecture
**Pre-redemption validation** flows through a single chokepoint:
* `VoucherRedemptionService::validateRedemption()` → `RedemptionGuard::check()`
* The guard runs `RedemptionSpecificationInterface` implementations (Secret, Mobile, Payable, Inputs, KYC, Location, TimeWindow, TimeLimit)
* ALL three redemption channels use this: Web (`DisburseController::redeem()`), API (`ConfirmRedemption`), SMS (`RedeemViaSms` → `ConfirmRedemption`)
* Specifications return `bool` — pass or fail. Guard collects failure keys into `ValidationResult`.

**Existing mobile handling:**
* `MobileSpecification` (`voucher/src/Specifications/`) — checks `cash.validation.mobile` for 1:1 recipient match. Untouched.
* `CashValidationRulesData` — stores `secret`, `mobile`, `payable`, `country`, `location`, `radius`
* `RedemptionContext` — carries `mobile`, `secret`, `vendorAlias`, `inputs`, `bankAccount`

**Post-redemption pipeline** (`config/voucher-pipeline.php` → `post-redemption`):
* `ValidateRedeemerAndCash` → `PersistInputs` → `ClearOgMetaCache` → `SyncEnvelopeData` → `DisburseCash` → `SendFeedbacks`
* Runs AFTER `redeemed_at` is set. Mobile verification must happen BEFORE this.

## Proposed Changes
The instruction's core pattern: "voucher declares the rule, driver performs the check, pipeline enforces the outcome." We integrate this into the existing `RedemptionGuard` specification pattern since that's the shared pre-redemption chokepoint across all channels.

### Phase 1: Core Driver System (voucher package)
New files in `monorepo-packages/voucher/src/MobileVerification/`:

**1.1 — Contract and Result**
* `MobileVerificationDriver` interface: `verify(string $mobile, array $context): MobileVerificationResult`
* `MobileVerificationResult` value object: `bool $valid`, `?string $normalizedMobile`, `?string $reason`, `array $meta`

**1.2 — Manager**
* `MobileVerificationManager`: resolves driver by name from registered drivers, calls `verify()`
* Registered as singleton in `VoucherServiceProvider`, drivers configured via `config/voucher.php`

**1.3 — Built-in Drivers (5 drivers)**
* `BasicDriver` — validates any mobile number format, normalizes to E.164. No country restriction.
* `CountriesDriver` — validates mobile belongs to one of the allowed ISO country codes. Options: `countries: ["PH", "US", "BE"]`. Uses `libphonenumber` to detect country from number.
* `WhiteListDriver` — checks mobile against a list of allowed numbers. Two source modes:
    * **Inline**: `mobiles: ["+639171234567", "+639181234567"]` — list embedded in voucher instructions. Good for small lists (<50).
    * **CSV file**: `file: "mobile-lists/beneficiaries-2026.csv"`, `column: "mobile"` (optional, defaults to first column) — reads from `Storage::disk('local')`. Good for medium lists (50–10k) uploaded by admin or seeded. File is parsed once per verification call; for very hot paths, results can be cached.
    * Normalizes both sides to E.164 before comparison. This is the self-contained option — no external system needed.
* `ExternalApiDriver` — calls an external HTTP endpoint to verify. Sends mobile as `mobile_param` (POST body or GET query). Config includes: `url`, `method`, `headers` (with auth token from `.env`), `extra_params` (additional key-value pairs sent alongside mobile), `timeout`, and `response_field` (JSON key in response that holds the boolean result, defaults to `valid`).
* `ExternalDbDriver` — queries an external (or local) database directly via a configured connection. Options: `connection` (Laravel DB connection name), `table`, `column` (mobile column), `where` (additional conditions as key-value pairs). No new migration — conforms to whatever schema exists.

**Note on `white_list` vs existing `MobileSpecification`:**
The current `MobileSpecification` checks `cash.validation.mobile` — a single hardcoded recipient. A `white_list` driver with one entry is functionally equivalent. In the future, the existing `cash.validation.mobile` field could be migrated to use the `white_list` driver, unifying all mobile restrictions under the driver system. For now, both coexist independently.

* Drivers are configured in `config/voucher.php`, parameters sourced from `.env` (same pattern as `config/database.php` connections):
```php
// config/voucher.php
'mobile_verification' => [
    'default' => env('REDEMPTION_MOBILE_VERIFICATION_DRIVER', 'basic'), // basic = format-only, no restriction
    'enforcement' => env('REDEMPTION_MOBILE_VERIFICATION_ENFORCEMENT', 'strict'),
    'drivers' => [
        'basic' => [
            'class' => BasicDriver::class,
        ],
        'countries' => [
            'class' => CountriesDriver::class,
            'countries' => array_filter(explode(',', env('REDEMPTION_MOBILE_VERIFICATION_COUNTRIES', 'PH'))),
        ],
        'white_list' => [
            'class' => WhiteListDriver::class,
            'mobiles' => array_filter(explode(',', env('REDEMPTION_MOBILE_VERIFICATION_MOBILES', ''))),
            'file' => env('REDEMPTION_MOBILE_VERIFICATION_FILE'),
            'column' => env('REDEMPTION_MOBILE_VERIFICATION_COLUMN'),
        ],
        'external_api' => [
            'class' => ExternalApiDriver::class,
            'url' => env('REDEMPTION_MOBILE_VERIFICATION_API_URL'),
            'method' => env('REDEMPTION_MOBILE_VERIFICATION_API_METHOD', 'POST'),
            'mobile_param' => env('REDEMPTION_MOBILE_VERIFICATION_API_MOBILE_PARAM', 'mobile'),
            'timeout' => (int) env('REDEMPTION_MOBILE_VERIFICATION_API_TIMEOUT', 5),
            'headers' => [
                'Authorization' => 'Bearer ' . env('REDEMPTION_MOBILE_VERIFICATION_API_TOKEN', ''),
                'Accept' => 'application/json',
            ],
            'extra_params' => [
                // additional key-value pairs sent alongside mobile
                // 'program' => 'fuel_subsidy_2026',
            ],
            'response_field' => env('REDEMPTION_MOBILE_VERIFICATION_API_RESPONSE_FIELD', 'valid'),
        ],
        'external_db' => [
            'class' => ExternalDbDriver::class,
            'connection' => env('REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION'),
            'table' => env('REDEMPTION_MOBILE_VERIFICATION_DB_TABLE'),
            'column' => env('REDEMPTION_MOBILE_VERIFICATION_DB_COLUMN'),
        ],
    ],
],
```

* `.env` example (white_list with CSV file):
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=white_list
REDEMPTION_MOBILE_VERIFICATION_ENFORCEMENT=strict
REDEMPTION_MOBILE_VERIFICATION_FILE=mobile-lists/fuel-subsidy-beneficiaries.csv
REDEMPTION_MOBILE_VERIFICATION_COLUMN=mobile_number
```

* `.env` example (external_db):
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=external_db
REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION=beneficiary_db
REDEMPTION_MOBILE_VERIFICATION_DB_TABLE=beneficiaries
REDEMPTION_MOBILE_VERIFICATION_DB_COLUMN=mobile_number
```

* The `MobileVerificationManager` reads the default driver + parameters from config. Voucher instructions can optionally override the driver name or enforcement level, but parameters always come from config (no credentials in voucher data).

### Phase 2: Voucher Policy Declaration
**2.1 — New DTO for verification config**
* `MobileVerificationConfigData` (Spatie Data): `?string $driver`, `string $enforcement` (strict/soft), `array $options`
* Added as nullable field on `CashValidationRulesData`: `public ?MobileVerificationConfigData $mobile_verification = null`
* Default: `null` (no verification — current behavior preserved)

**2.2 — Voucher instructions (lightweight)**
The voucher only declares *whether* to verify and optionally *which* driver/enforcement. All driver parameters come from `config/voucher.php` + `.env`.

No verification (default): `mobile_verification: null`

Use env default driver and enforcement:
```json
{ "mobile_verification": true }
```

Override driver (params still from config):
```json
{ "mobile_verification": { "driver": "countries" } }
```

Override enforcement:
```json
{ "mobile_verification": { "enforcement": "soft" } }
```

Override both:
```json
{ "mobile_verification": { "driver": "external_db", "enforcement": "strict" } }
```

No `options` in voucher instructions — credentials, file paths, URLs, DB connections all stay in `.env`.

### Phase 3: Specification Integration
**3.1 — New `MobileVerificationSpecification`**
* Implements `RedemptionSpecificationInterface`
* Reads `$voucher->instructions->cash->validation->mobile_verification`
* If null → passes (no verification configured)
* If present → calls `MobileVerificationManager::verify()` with mobile from context
* Strict enforcement + fail → return false (blocks redemption)
* Soft enforcement + fail → log warning, return true (proceeds, stores result in voucher log)

**3.2 — Register in `RedemptionGuard`**
* Add `MobileVerificationSpecification` to guard constructor
* Check runs in the standard voucher validation block (alongside MobileSpecification)
* Existing `MobileSpecification` (1:1 match) is SEPARATE and untouched

**3.3 — Update `VoucherRedemptionService`**
* Pass the new specification when constructing the guard
* Failure key: `'mobile_verification'` (distinct from existing `'mobile'` key)
* Error message: driver-provided reason or default "Mobile verification failed."

### Phase 4: Environment Configuration (host app)
All driver parameters configured in `.env` + `config/voucher.php`. No credentials or paths in voucher data.

**4.1 — `white_list` with inline mobiles**
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=white_list
REDEMPTION_MOBILE_VERIFICATION_MOBILES=+639171234567,+639181234567
```

**4.2 — `white_list` with CSV file**
Admin uploads CSV to `storage/app/private/mobile-lists/`. CSV must have a header row.
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=white_list
REDEMPTION_MOBILE_VERIFICATION_FILE=mobile-lists/fuel-subsidy-beneficiaries.csv
REDEMPTION_MOBILE_VERIFICATION_COLUMN=mobile_number
```
If both `MOBILES` and `FILE` are set, driver merges both sources.

**4.3 — `external_db`**
Add a Laravel DB connection in `config/database.php`, then point the driver at it:
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=external_db
REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION=beneficiary_db
REDEMPTION_MOBILE_VERIFICATION_DB_TABLE=beneficiaries
REDEMPTION_MOBILE_VERIFICATION_DB_COLUMN=mobile_number
# Plus the DB connection credentials:
BENEFICIARY_DB_HOST=127.0.0.1
BENEFICIARY_DB_DATABASE=cashless
BENEFICIARY_DB_USERNAME=root
BENEFICIARY_DB_PASSWORD=
```

**4.4 — `external_api`**
```
REDEMPTION_MOBILE_VERIFICATION_DRIVER=external_api
REDEMPTION_MOBILE_VERIFICATION_API_URL=https://agency-api.example.com/beneficiaries/check
REDEMPTION_MOBILE_VERIFICATION_API_METHOD=POST
REDEMPTION_MOBILE_VERIFICATION_API_TOKEN=your-api-token-here
REDEMPTION_MOBILE_VERIFICATION_API_TIMEOUT=5
REDEMPTION_MOBILE_VERIFICATION_API_RESPONSE_FIELD=valid
```
Structured config (headers, extra_params) lives in `config/voucher.php`. Secrets (API token) stay in `.env`.
The driver sends: `{ "mobile": "+639171234567", ...extra_params }` and reads `response.{response_field}` as boolean.

**Switching drivers is a `.env` change + `config:clear`. Same pattern as `DB_CONNECTION=mysql`.**

## End-to-End Flow (example: `external_db` with beneficiary database)
```
Redeemer enters mobile → DisburseController::redeem() / ConfirmRedemption / SMSRedeem
  → VoucherRedemptionService::validateRedemption()
    → RedemptionGuard::check()
      → MobileVerificationSpecification
        → reads voucher.instructions.cash.validation.mobile_verification
        → { driver: "external_db", enforcement: "strict", options: { connection: "beneficiary_db", ... } }
        → MobileVerificationManager::verify(mobile, context)
          → ExternalDbDriver::verify(mobile, context)
            → DB::connection('beneficiary_db')->table('beneficiaries')->where('mobile_number', normalized)->...->exists()
            → MobileVerificationResult { valid: true/false, reason: "..." }
        → strict + invalid → return false → guard adds 'mobile_verification' to failures
        → RedemptionException thrown → "Mobile number is not in the beneficiary list."
  → Redemption blocked. Same behavior across web, API, SMS.
```

## End-to-End Flow (example: `white_list` with inline list)
```
Redeemer enters mobile → same channel entry points
  → RedemptionGuard::check()
    → MobileVerificationSpecification
      → { driver: "white_list", enforcement: "strict", options: { mobiles: ["+639171234567"] } }
      → WhiteListDriver::verify(mobile, context)
        → normalize both sides, check if mobile is in the list
        → MobileVerificationResult { valid: true/false }
      → strict + invalid → blocked
  → Same enforcement, same result object, same error path.
```

### Phase 5: Generation UI + API Mapping
* Add mobile verification config to voucher generation form (optional section)
* Map API fields in `GenerateVouchers.php` to `CashValidationRulesData`
* Cost implications: none (verification doesn't affect pricing)

## Channel Coverage
All three channels are automatically covered because they all call `VoucherRedemptionService::validateRedemption()` → `RedemptionGuard::check()`:
* Web (`DisburseController::redeem()` line 247)
* API (`ConfirmRedemption::asController()` line 101)
* SMS (`RedeemViaSms` → `ConfirmRedemption` line 101)
No per-channel code needed.

## Backward Compatibility
* `mobile_verification: null` (default) = no verification = current behavior
* Existing `MobileSpecification` (1:1 recipient match via `cash.validation.mobile`) is untouched and runs independently
* Existing `VoucherRedemptionValidator::validateMobile()` (legacy) is untouched
* No existing tests affected — all new tests in `tests/Feature/MobileVerification/`

## TDD Approach
Each phase: write failing tests first, implement, verify green.
* Phase 1: Unit tests for drivers and manager
* Phase 2: Data DTO casting/serialization tests
* Phase 3: Specification passes/fails for strict/soft enforcement across all modes
* Phase 4: Integration test with beneficiary database lookup
* Phase 5: API mapping tests
