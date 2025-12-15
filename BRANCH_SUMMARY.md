# Branch: feature/named-step-references

## Overview
Implements **named step references** for order-independent variable resolution in form flows. This solves the problem where changing step order breaks hardcoded index-based references like `$step1_name`.

## Problem
Currently, BIO step references KYC data using:
```yaml
variables:
  $kyc_name: "$step1_name"  # Hardcoded to step index 1
```

If a new step is added before KYC, the index shifts and the reference breaks.

## Solution
Enable semantic, name-based references:
```yaml
steps:
  kyc:
    step_name: "kyc_verification"  # Named identifier
  bio:
    step_name: "bio_fields"
    config:
      variables:
        $name: "$kyc_verification.name"  # Name-based reference
```

## Implementation Plan
See plan ID: `efad54a4-3112-48ef-800e-a776f77b3cd2`

**Key changes:**
1. Store `_step_name` in session collected_data
2. DriverService extracts `step_name` from YAML
3. FormHandler creates both index-based and name-based variables
4. YAML config migrated to new syntax
5. Backward compatibility maintained

## Status
✅ **Implemented** - All tasks complete, tests passing (6/6)

## Related Files
- `packages/form-flow-manager/src/Services/FormFlowService.php`
- `packages/form-flow-manager/src/Services/DriverService.php`
- `packages/form-flow-manager/src/Handlers/FormHandler.php`
- `config/form-flow-drivers/voucher-redemption.yaml`
- `docs/YAML_DRIVER_ARCHITECTURE.md`

## Testing Strategy
- Named variables resolve correctly
- Index-based variables still work (backward compat)
- Step order changes don't break references
- Missing step names degrade gracefully

---
**Created:** 2025-12-15  
**Branch:** feature/named-step-references  
**Plan Status:** Awaiting user approval
