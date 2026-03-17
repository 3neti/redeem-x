# ADR: Verification Pipeline Generalization Strategy

**Status:** Deferred (revisit when a second verification type is needed)
**Date:** 2026-03-17
**Context:** Mobile Verification Pipeline (Phase 1-3 complete)

## Decision

Keep all mobile verification code explicitly mobile-scoped (`MobileVerification*` naming). Do not prematurely abstract into a generic `Verification` framework.

## Current Architecture

The mobile verification pipeline follows this pattern:

```
Interface (MobileVerificationDriverInterface)
  → Drivers (Basic, Countries, WhiteList, ExternalApi, ExternalDb)
    → Manager (MobileVerificationManager) — resolves drivers from config
      → ConfigData DTO (MobileVerificationConfigData) — stored in voucher instructions
        → Specification (MobileVerificationSpecification) — implements RedemptionSpecificationInterface
          → Guard (RedemptionGuard) — accepts specification as pluggable param
```

Key files:
- `monorepo-packages/voucher/src/MobileVerification/` — drivers, manager, contract, result
- `monorepo-packages/voucher/src/Data/MobileVerificationConfigData.php` — DTO
- `monorepo-packages/voucher/src/Specifications/MobileVerificationSpecification.php`
- `monorepo-packages/voucher/src/Guards/RedemptionGuard.php`
- `monorepo-packages/voucher/config/vouchers.php` — `mobile_verification` section

## When to Generalize

If a second verification type is needed (e.g. email verification, beneficiary ID check, KYC re-verification), follow this approach:

### Step 1: Replicate the Pattern (~30 min)

Create the new type following the same structure:

```
EmailVerificationDriverInterface
EmailVerificationManager
EmailVerificationConfigData
EmailVerificationSpecification
```

Config section: `voucher.email_verification.*`
Env prefix: `REDEMPTION_EMAIL_VERIFICATION_*`

### Step 2: Extract Shared Abstractions (optional, ~30 min)

If two or more types share enough structure, extract:

- `VerificationResult` — base class (mobile's `MobileVerificationResult` already fits this shape)
- `VerificationManagerInterface` — contract for `verify()` + `getEnforcement()`
- `VerificationDriverInterface` — generic `verify(string $input, array $context): VerificationResult`

The existing mobile code can implement these interfaces without breaking changes.

### Step 3: Wire into Guard

`RedemptionGuard` already accepts specifications as nullable params. Adding a new verification is a one-line addition:

```php
public function __invoke(
    // ... existing params
    ?MobileVerificationSpecification $mobileVerificationSpec = null,
    ?EmailVerificationSpecification $emailVerificationSpec = null,  // ← add
): bool
```

## Why Not Generalize Now

1. **No second use case exists yet** — abstracting without a concrete need leads to wrong abstractions
2. **Mobile naming is self-documenting** — `MobileVerificationConfigData` is clearer than `VerificationConfigData`
3. **Different types will have different drivers** — email verification drivers look nothing like mobile country-check drivers
4. **The pattern is the reusable part** — not the code itself
5. **30-minute refactor** — extracting shared interfaces later is trivial with the pattern proven

## Potential Future Verification Types

- **Email verification** — confirm redeemer email matches a list or domain
- **Beneficiary ID check** — verify government ID against an external registry
- **KYC re-verification** — require fresh KYC for high-value vouchers
- **Geofencing** — verify redeemer location is within allowed area
- **Device verification** — confirm redemption from a registered device

Each would follow the same Driver → Manager → DTO → Specification → Guard pattern.
