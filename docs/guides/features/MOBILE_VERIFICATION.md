# Mobile Verification — User Manual

Mobile verification validates a redeemer's phone number against a configurable policy before allowing voucher redemption. It uses a driver-based architecture — you pick the verification strategy, and the system enforces it at redemption time.

## Quick Start

1. Go to **PWA → Vouchers → Generate**
2. Toggle **Mobile Verification** on (shield icon in the config chips)
3. (Optional) Tap **Configure** to override driver/enforcement
4. Generate the voucher

The redeemer's mobile number will be verified during redemption according to the selected policy.

## Concepts

### Drivers

A driver is the verification strategy — *how* the mobile number is checked. Five drivers are available:

| Driver | What it does | Key env vars |
|---|---|---|
| **Basic** | Validates format only (≥7 digits, normalizes to E.164). No external checks. | — |
| **Countries** | Parses the number and verifies it belongs to an allowed set of country codes. Falls back to trying each allowed country as a hint if detection fails. | `REDEMPTION_MOBILE_VERIFICATION_COUNTRIES` (comma-separated, default: `PH`) |
| **White List** | Checks against an explicit allow-list of numbers. Supports inline numbers and/or a CSV file. | `REDEMPTION_MOBILE_VERIFICATION_MOBILES` (comma-separated), `REDEMPTION_MOBILE_VERIFICATION_FILE` (path in `storage/app/`), `REDEMPTION_MOBILE_VERIFICATION_COLUMN` |
| **External API** | Calls an external HTTP endpoint to validate. Supports GET/POST, custom headers, bearer token auth, and configurable response field. | `REDEMPTION_MOBILE_VERIFICATION_API_URL`, `_API_METHOD`, `_API_TOKEN`, `_API_TIMEOUT`, `_API_MOBILE_PARAM`, `_API_RESPONSE_FIELD` |
| **External DB** | Queries a separate database connection/table to check if the number exists. Tries both normalized and raw formats. | `REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION`, `_DB_TABLE`, `_DB_COLUMN` |

### Enforcement

Enforcement controls what happens when verification **fails**:

- **Strict** (default) — Blocks redemption. The redeemer sees an error.
- **Soft** — Logs a warning but allows redemption to proceed. Useful for monitoring before enforcing.

### Defaults vs. Per-Voucher Overrides

There are two layers of configuration:

1. **Server defaults** — Set via `.env` variables. Applied when no per-voucher override exists.
2. **Per-voucher overrides** — Set in the Generate UI. Stored in the voucher's instructions. Only override the driver name and enforcement mode — driver *parameters* (API keys, file paths, DB credentials) always come from `.env`.

This means you cannot accidentally embed credentials in voucher data.

## Environment Variables

Add these to your `.env` (all optional — the system defaults to `basic` driver with `strict` enforcement):

```bash
# Default driver: basic, countries, white_list, external_api, external_db
REDEMPTION_MOBILE_VERIFICATION_DRIVER=basic

# Default enforcement: strict, soft
REDEMPTION_MOBILE_VERIFICATION_ENFORCEMENT=strict

# Countries driver
REDEMPTION_MOBILE_VERIFICATION_COUNTRIES=PH

# White List driver
REDEMPTION_MOBILE_VERIFICATION_MOBILES=09171234567,09181234567
REDEMPTION_MOBILE_VERIFICATION_FILE=whitelist/approved-numbers.csv
REDEMPTION_MOBILE_VERIFICATION_COLUMN=mobile

# External API driver
REDEMPTION_MOBILE_VERIFICATION_API_URL=https://api.example.com/verify
REDEMPTION_MOBILE_VERIFICATION_API_METHOD=POST
REDEMPTION_MOBILE_VERIFICATION_API_TOKEN=your-bearer-token
REDEMPTION_MOBILE_VERIFICATION_API_TIMEOUT=5
REDEMPTION_MOBILE_VERIFICATION_API_MOBILE_PARAM=mobile
REDEMPTION_MOBILE_VERIFICATION_API_RESPONSE_FIELD=valid

# External DB driver
REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION=beneficiaries
REDEMPTION_MOBILE_VERIFICATION_DB_TABLE=approved_mobiles
REDEMPTION_MOBILE_VERIFICATION_DB_COLUMN=phone_number
```

## UI Guide

### Generating a Voucher with Mobile Verification

In **PWA → Vouchers → Generate**:

1. In the config chips area, find the **Mobile Verification** row with the shield icon
2. Toggle the switch **on** — the row highlights and a "Configure" link appears
3. Without tapping Configure, the voucher will use server defaults
4. Tap **Configure** to open the configuration sheet:
   - **Driver** — Select a specific driver or leave as "Server default"
   - **Enforcement** — Select strict/soft or leave as "Server default"
5. Tap **Done** to close the sheet
6. Selected overrides appear as badges below the toggle (e.g., `countries`, `strict`)

The mobile verification setting is:
- Saved/restored with localStorage (persists across page reloads)
- Included when saving as a campaign
- Restored when applying a campaign
- Cleared when resetting the form

### Viewing Verification Config on a Voucher

In **PWA → Vouchers → Show** (tap any voucher):

- If mobile verification was enabled, a **shield badge** appears in the details sheet
- Shows the driver and enforcement if overrides were set, or "enabled" for server defaults
- Read-only — cannot be changed after generation

## How It Works at Redemption

```
Redeemer enters mobile number
        ↓
RedemptionGuard runs specifications
        ↓
MobileVerificationSpecification checks voucher instructions
        ↓
  ┌─ No mobile_verification in instructions? → PASS (skip)
  │
  └─ mobile_verification present?
          ↓
     MobileVerificationManager resolves driver
     (voucher override → .env default → 'basic')
          ↓
     Driver.verify(mobile, config) → Result
          ↓
     ┌─ Result passed? → ALLOW redemption
     │
     └─ Result failed?
            ↓
        ┌─ Enforcement = soft? → LOG warning, ALLOW redemption
        └─ Enforcement = strict? → BLOCK redemption
```

All phone numbers are normalized to E.164 format (`+639171234567`) before comparison.

## Phone Number Normalization

All drivers normalize numbers using this logic:
- Leading `0` → assumed PH: `09171234567` → `+639171234567`
- Leading `63` → add `+`: `639171234567` → `+639171234567`
- Leading `+` → keep as-is: `+639171234567` → `+639171234567`
- Other → assume PH and prepend `+63`

## Common Scenarios

### "Only Philippine numbers can redeem"
- Driver: **Countries**
- `.env`: `REDEMPTION_MOBILE_VERIFICATION_COUNTRIES=PH`
- Enforcement: **Strict**

### "Only pre-approved beneficiaries can redeem"
- Driver: **White List**
- `.env`: `REDEMPTION_MOBILE_VERIFICATION_MOBILES=09171234567,09181234567`
- Or use a CSV: `REDEMPTION_MOBILE_VERIFICATION_FILE=whitelist/beneficiaries.csv`
- Enforcement: **Strict**

### "Log unrecognized numbers but don't block"
- Driver: **Countries** (or any driver)
- Enforcement: **Soft**
- Check logs for `Mobile verification failed (soft enforcement)` entries

### "Validate against our CRM database"
- Driver: **External DB**
- `.env`: Configure connection, table, column pointing to your CRM
- Enforcement: **Strict**

### "Validate via third-party API"
- Driver: **External API**
- `.env`: Configure URL, auth token, response field
- Enforcement: **Strict**

## Troubleshooting

**Verification always passes / seems inactive:**
- Check the voucher was generated with mobile verification enabled (check Show page for shield badge)
- If no badge appears, the voucher has no verification config — generate a new one with it enabled

**"Driver [X] is not configured" error:**
- The voucher references a driver that isn't in `config/vouchers.php` → check `.env` and run `php artisan config:clear`

**White List driver fails with "No whitelist configured":**
- Neither `REDEMPTION_MOBILE_VERIFICATION_MOBILES` nor `REDEMPTION_MOBILE_VERIFICATION_FILE` is set

**External API/DB driver fails:**
- Check connectivity, credentials, and timeout settings
- Failures are fail-closed (treated as verification failure), not silently skipped

**Number format mismatches:**
- All drivers normalize to E.164 before comparison, so `09171234567`, `+639171234567`, and `639171234567` are equivalent for PH numbers

## Architecture Reference

- **Package**: `monorepo-packages/voucher/src/MobileVerification/`
- **Config**: `monorepo-packages/voucher/config/vouchers.php` → `mobile_verification` section
- **Specification**: `MobileVerificationSpecification` (wired into `RedemptionGuard`)
- **DTO**: `MobileVerificationConfigData` (stored in voucher instructions at `cash.validation.mobile_verification`)
- **API request**: `VoucherGenerationRequest` maps `mobile_verification` param to DTO
- **UI**: `Generate.vue` (toggle + config sheet), `VoucherDetailsSheet.vue` (readonly badge)
- **Tests**: 31 tests, 58 assertions covering all drivers and integration points
- **ADR**: `docs/decisions/VERIFICATION_PIPELINE_GENERALIZATION.md`
