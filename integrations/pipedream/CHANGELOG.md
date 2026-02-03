# Pipedream Workflows Changelog

All notable changes to Pipedream SMS integration workflows will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [3.0.0] - 2026-01-31

**File:** `token-based-routing.js`

### Changed
- **Architecture:** Simplified to authentication proxy pattern (Pipedream handles auth only, Laravel handles all business logic)
- **Code Reduction:** Reduced from 570 lines (v2.1) to ~200 lines (65% smaller)
- **Routing:** Added dual-endpoint routing (`/sms` for authenticated, `/sms/public` for unauthenticated)
- **Separation of Concerns:** Moved all voucher generation logic to Laravel

### Added
- Public endpoint support for unauthenticated commands
- Improved error handling with Laravel validation

### Removed
- Direct voucher generation logic (now handled by Laravel)
- SMS command parsing (delegated to Laravel)
- Business logic (moved to Laravel application)

### Migration
- Requires Laravel routes: `/sms` and `/sms/public`
- See `README.md` for migration guide from v2.1

## [2.1.0] - 2026-01-28

**File:** `generate-voucher.js`

### Added
- Full SMS command handling: AUTHENTICATE, GENERATE, REDEEM, BALANCE
- Token management in Pipedream Data Store
- Direct voucher generation via API calls
- Comprehensive error handling and logging
- Support for Omni Channel shortcode 22560537

### Changed
- Enhanced authentication flow with token expiry (7 days)
- Improved SMS response formatting

### Technical Details
- 570 lines of code
- Handles all business logic in Pipedream
- Stores user tokens in Data Store: `redeem-x`

## [2.0.0] - 2026-01-15 (estimated)

### Added
- Token-based authentication system
- Pipedream Data Store integration for token persistence
- AUTHENTICATE command support

### Changed
- Migrated from stateless to stateful authentication
- Enhanced security with token validation

## [1.0.0] - 2026-01-01 (estimated)

### Added
- Initial Pipedream integration
- Basic SMS command routing to Laravel
- Webhook trigger from Omni Channel

### Technical Details
- Simple HTTP webhook proxy
- No authentication (all requests forwarded to Laravel)
