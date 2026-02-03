# UI/UX Improvement Plan: Simplified/Advanced Mode & Organization

## Scope
**This plan focuses exclusively on the Voucher Generation UI** (`/vouchers/generate` page).

**Out of scope for this iteration:**
* Voucher list page (`/vouchers`)
* Voucher detail/show page (`/vouchers/{id}`)
* Bulk generation page (`/vouchers/generate/bulk`) - UI remains as-is, only navigation/consistency improvements
* Campaign management pages
* Other voucher-related pages

**Future considerations:**
Once the generation page redesign is validated and stable, similar progressive disclosure patterns could be applied to:
* Campaign creation/editing (similar complexity to voucher generation)
* Bulk generation (could benefit from simplified/advanced modes)
* Voucher filtering/search (could use collapsible filter sections)

## Problem Statement
The current voucher generation UI presents all configuration options at once (10+ cards including Basic Settings, Input Fields, Validation Rules, Location/Time Validation, Feedback Channels, Rider, Preview Controls, and JSON Preview). This creates cognitive overload for casual users who want to quickly generate a simple voucher, while power users may appreciate seeing all options.

## Solution: Progressive Disclosure Pattern
Using a hybrid approach combining Quick Action Mode (Simple/Advanced toggle) + Collapsible Sections.

### Implementation Phases
1. **Phase 1:** Core infrastructure (feature flags, user preferences, routes)
2. **Phase 2:** Simple Mode UI (minimal 3-field form)
3. **Phase 3:** Advanced Mode UI (collapsible cards, expand/collapse controls)
4. **Phase 4:** Campaign integration improvements
5. **Phase 5:** Bulk generation UX consistency

### Transition Strategy
- **Feature Flag:** `GENERATE_UI_V2_ENABLED` to toggle between legacy and v2
- **Dual Routes:** 
  - `/vouchers/generate` → New UI (when feature flag enabled)
  - `/vouchers/generate/legacy` → Old UI (always available for testing)
- **File Organization:**
  - `Create.vue` → Legacy (frozen, untouched)
  - `CreateV2.vue` → New UI (development target)

## Key Technical Decisions
1. **User Preference Storage:** JSON column on users table (`ui_preferences`)
2. **No Role/Permission System:** Simple user preference toggle instead
3. **Progressive Disclosure:** Show essential fields first, advanced on request
4. **Parallel Development:** Build new UI while keeping old UI functional

## Success Metrics
- 60%+ users stay in Simple Mode
- 30% reduction in time-to-first-generation
- 40%+ users explore Advanced Mode within 5 generations
- 25% reduction in generation errors

## Resources
- Full plan: This document
- UX Research: Nielsen Norman Group - Progressive Disclosure
- Existing components: `resources/js/components/ui/collapsible/`
- Config: `config/generate.php`

For complete details, see sections below.
