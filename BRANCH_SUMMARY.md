# Branch: feature/deprecate-php-driver

## Purpose
Implement 5-phase deprecation strategy to make YAML driver the permanent and only transformation path for form-flow-manager, removing legacy PHP driver methods.

## Background
- YAML driver fully implemented with 105/105 tests passing
- A/B testing infrastructure deployed (merged to main)
- `/disburse` and `/disburse-yaml` routes both functional
- Current default: PHP driver (feature flag = false)
- Goal: YAML becomes permanent, PHP driver removed

## Strategy Summary

### Phase 1: Make YAML Default (Non-Breaking) 
**Timeline:** 1-2 days | **Risk:** Low | **Reversible:** Yes

**Changes:**
- Update feature flag default from `false` → `true`
- Add `FORM_FLOW_USE_YAML_DRIVER=true` to .env.example
- Add `@deprecated` tags to all PHP driver methods
- Add trigger_error() warnings when PHP methods called
- Update documentation with migration guide

**Impact:** YAML becomes default, PHP still available as fallback

### Phase 2: Extract YamlDriverService (Refactoring)
**Timeline:** 3-5 days | **Risk:** Medium | **Reversible:** Yes

**Changes:**
- Create `AbstractDriverService` base class
- Extract `YamlDriverService` extending abstract class
- Extract `LegacyPhpDriverService` for PHP methods
- Update `DriverService` to delegate based on flag (facade pattern)
- Update service provider bindings

**Impact:** Better architecture, separation of concerns, extensible for future drivers

### Phase 3: Remove A/B Testing Infrastructure
**Timeline:** 1 day | **Risk:** Low | **Reversible:** Yes

**Changes:**
- Delete `DisburseYamlController.php`
- Delete `routes/disburse-yaml.php`
- Remove route registration from bootstrap/app.php
- Simplify Complete.vue (remove yaml variant logic)
- Delete `DisburseYamlRouteTest.php`
- Archive or delete AB_TESTING_YAML_DRIVER.md

**Impact:** Cleaner codebase, no redundant routes

### Phase 4: Remove PHP Driver (Breaking Change)
**Timeline:** Coordinate with major release | **Risk:** High | **Reversible:** No

**Changes:**
- Remove `use_yaml_driver` feature flag
- Delete `LegacyPhpDriverService.php`
- Remove all PHP build methods from codebase
- Update controllers to use YamlDriverService directly
- Bump version to 2.0.0
- Create MIGRATION_PHP_TO_YAML.md guide

**Impact:** Breaking change, users on 1.x cannot upgrade without migration

### Phase 5: Cleanup and Polish
**Timeline:** Ongoing | **Risk:** Low | **Reversible:** Yes

**Changes:**
- Rename YamlDriverService → DriverService (simpler naming)
- Cache parsed YAML in production
- Add YAML schema validation
- Performance benchmarks
- Test simplification

**Impact:** Optimized, maintainable codebase

## Implementation Plan
See detailed plan document: Plan ID `58842d20-c9eb-4e6a-ad44-61338fea4383`

## Current Status
- ✅ A/B testing merged to main
- ✅ Branch created: feature/deprecate-php-driver
- ✅ Phase 1: Complete (commit cac66f88)
- ⏳ Phase 2-5: Ready to implement

## Rollback Strategy
Each phase independently reversible:
- **Phase 1:** Set `FORM_FLOW_USE_YAML_DRIVER=false` in .env
- **Phase 2:** Use DriverService instead of specific services
- **Phase 3:** Restore deleted files from git
- **Phase 4:** Users stay on 1.x (cannot rollback)
- **Phase 5:** Git revert specific changes

## Success Metrics
- All 105+ tests passing at each phase
- Zero production incidents
- Fewer lines of code
- Better separation of concerns
- Improved performance

## Dependencies
- packages/form-flow-manager/src/Services/DriverService.php
- packages/form-flow-manager/config/form-flow.php
- config/form-flow-drivers/voucher-redemption.yaml
- app/Http/Controllers/Disburse/DisburseController.php
- app/Http/Controllers/Disburse/DisburseYamlController.php

## Documentation
- Main plan: Use `read_plans` with ID `58842d20-c9eb-4e6a-ad44-61338fea4383`
- A/B Testing Guide: docs/AB_TESTING_YAML_DRIVER.md
- Migration Guide: docs/MIGRATION_PHP_TO_YAML.md (to be created in Phase 4)

---
Created: 2025-12-14
Author: WARP AI Assistant
Base Commit: 77ae7250
