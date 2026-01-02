# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Voucher-Based Payment System** - Pay with vouchers directly to wallet without bank rails
  - New `/pay/voucher` endpoint for wallet-to-wallet transfers
  - `PayWithVoucher` action transfers funds from Cash escrow to User wallet
  - `VoucherPaymentModal` component in TopUp page for easy voucher redemption
  - Two redemption paths: `/disburse` (cash out to bank) vs `/pay/voucher` (wallet credit)
  - Rate limiting: 5 requests per minute per user
  - Comprehensive test suite: 10 tests validating money flow, metadata, and edge cases
  - Metadata tracking: `redemption_type`, `redeemer_user_id`, `transfer_uuid`
  - Bypasses post-redemption disbursement pipeline to keep money in-system
  - Voucher reclaim: Issuers can redeem their own unredeemed vouchers

### Technical Details
- Money flow: `Cash::transfer(User, amount)` for atomic wallet updates
- Voucher marked redeemed via direct update (bypasses VoucherObserver)
- Code normalization: Lowercase codes accepted and normalized to uppercase
- Error handling: ValidationException for invalid/expired/redeemed vouchers
- Frontend: Modal with loading, error, and success states
- Backend: Thin controller with action-based business logic

### Security
- Authentication required via `auth` middleware
- Rate limiting: 5 requests/minute to prevent abuse
- Validation reuses existing `ValidateVoucherCode` action

[Unreleased]: https://github.com/your-org/redeem-x/compare/main...HEAD
