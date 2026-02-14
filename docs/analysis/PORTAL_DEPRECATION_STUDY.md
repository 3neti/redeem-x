# Portal Deprecation Study
**Date**: 2026-02-14  
**Context**: Evaluating if `/pwa/portal` can replace desktop `/portal`

## Executive Summary

**Recommendation**: ❌ **NOT YET** - Desktop `/portal` cannot be deprecated at this time.

**Reason**: Desktop `/portal` is a full-featured **voucher generation portal** (cash register UI), while `/pwa/portal` is a **wallet-first dashboard** (home screen). They serve different purposes.

**Path Forward**: 
1. Keep both portals (different use cases)
2. OR: Build full voucher generation into `/pwa/vouchers/generate` to match desktop `/portal` features

---

## Feature Comparison

### Desktop `/portal` (resources/js/pages/Portal.vue)
**Purpose**: Full-featured voucher generation portal (cash register UI)  
**Size**: 1,693 lines  
**Route**: `/portal` (requires mobile + balance)  
**Controller**: `PortalController`

**Key Features**:
✅ **Instant voucher generation** - Cash register style quick generation  
✅ **Quick amount buttons** - Configurable (₱100, ₱500, ₱1K, etc.)  
✅ **Full voucher configuration**:
  - Redeemable, Payable, Settlement voucher types
  - Input field selection (OTP, mobile, location, selfie, signature, etc.)
  - Validation rules (location, time, secret, payee)
  - Feedback channels (email, SMS, webhook)
  - Rider config (message, URL, splash, timeouts)
  - Settlement rail selection (INSTAPAY/PESONET)
  - Fee strategy (absorb/include/add)
✅ **Smart payee detection** - Blank/CASH, mobile number, vendor alias  
✅ **OTP auto-add** - When mobile payee is set  
✅ **Real-time pricing** - Live cost breakdown using `useChargeBreakdown`  
✅ **QR code generation** - For single vouchers  
✅ **External metadata** - Freeform JSON for payable vouchers  
✅ **File attachments** - Upload invoices/bills for settlement vouchers  
✅ **Settlement vouchers** - Target amount, interest rate, envelope config  
✅ **Confirmation modal** - Before generation  
✅ **Success redirect** - To voucher show page or vouchers list  
✅ **Configurable UI** - Via `config/portal.php`  

**Authentication**: Required + onboarding (mobile + balance)  
**Use Case**: Power users, merchants, issuers generating vouchers rapidly

---

### PWA `/pwa/portal` (packages/pwa-ui/resources/js/pages/Pwa/Portal.vue)
**Purpose**: Wallet-first dashboard (home screen)  
**Size**: 161 lines  
**Route**: `/pwa/portal` (authenticated only)  
**Controller**: `PwaPortalController`

**Key Features**:
✅ **Wallet balance display** - Large, prominent balance  
✅ **Recent vouchers** - Last 5 vouchers with status  
✅ **Quick actions**:
  - Generate vouchers (navigates to `/pwa/vouchers/generate`)
  - View all vouchers (navigates to `/pwa/vouchers`)
  - Top-up wallet (navigates to `/pwa/topup`)
  - Wallet QR (navigates to `/pwa/wallet`)
  - Settings (navigates to `/pwa/settings`)
✅ **Onboarding status** - Mobile, merchant, balance checks  
✅ **Mobile-optimized** - Touch-friendly, bottom nav  

**Authentication**: Required (no onboarding checks)  
**Use Case**: Mobile subscribers, casual voucher users, wallet management

---

## Gap Analysis

### What `/pwa/portal` CANNOT do (that desktop `/portal` can):
❌ **Instant voucher generation** - No quick generation UI  
❌ **Advanced configuration** - Input fields, validation, feedback, rider  
❌ **Settlement vouchers** - No target amount, interest, envelope config  
❌ **Payable vouchers** - No external metadata, file attachments  
❌ **Smart payee** - No mobile/vendor detection  
❌ **Real-time pricing** - No cost breakdown preview  
❌ **Quick amounts** - No ₱100/₱500/₱1K buttons  
❌ **QR generation** - No immediate QR after generation  

### What `/pwa/vouchers/generate` provides (PWA wizard):
✅ **Full voucher configuration** - Matches desktop `/portal` capabilities  
✅ **Step-by-step wizard** - Mobile-friendly bottom sheets  
✅ **Campaign templates** - Reusable configurations  
✅ **Real-time pricing** - Cost breakdown modal  
✅ **Smart payee** - Mobile/vendor detection  
✅ **Settlement vouchers** - Target amount, interest, envelope  
✅ **Input fields** - Full selection with validation  
✅ **Feedback channels** - Email, SMS, webhook  
✅ **Rider config** - Message, URL, splash, timeouts  
✅ **State management** - Lock/unlock/cancel/extend  

**Conclusion**: `/pwa/vouchers/generate` is the PWA equivalent of desktop `/portal`, not `/pwa/portal`.

---

## Architecture Differences

### Desktop `/portal`
```
┌─────────────────────────────────────┐
│         Portal (Cash Register)      │
│                                     │
│  ┌───────────────────────────────┐ │
│  │  Quick Amounts: 100 500 1K... │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  Count: [___]  Amount: [___]  │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  Inputs: ☑ OTP ☑ Location... │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  [Generate Now]               │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

### PWA `/pwa/portal`
```
┌─────────────────────────────────────┐
│         PWA Portal (Dashboard)      │
│                                     │
│  ┌───────────────────────────────┐ │
│  │  Wallet: ₱1,234.56            │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  Recent Vouchers (5)          │ │
│  │  - ABC123 ₱500 Redeemed       │ │
│  │  - DEF456 ₱200 Active         │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  [Generate] [Vouchers] [Top-up]│
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

### PWA `/pwa/vouchers/generate`
```
┌─────────────────────────────────────┐
│    PWA Voucher Generation Wizard    │
│                                     │
│  [Amount]  [Count]  [Type]          │
│  ┌───────────────────────────────┐ │
│  │  ₱500.00  ×1  Redeemable      │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  Input Fields (tap to config) │ │
│  │  Validation (tap to config)   │ │
│  │  Feedback (tap to config)     │ │
│  └───────────────────────────────┘ │
│  ┌───────────────────────────────┐ │
│  │  [Generate Redeemable Voucher]│ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

---

## Use Case Mapping

| Use Case | Desktop | PWA |
|----------|---------|-----|
| Quick voucher generation | `/portal` | `/pwa/vouchers/generate` |
| Wallet dashboard | - | `/pwa/portal` |
| View recent vouchers | `/vouchers` | `/pwa/portal` (5 recent) |
| View all vouchers | `/vouchers` | `/pwa/vouchers` |
| Voucher details | `/vouchers/{code}` | `/pwa/vouchers/{code}` |
| Top-up wallet | `/wallet/topup` | `/pwa/topup` |
| Wallet QR | `/wallet/qr` | `/pwa/wallet` |
| Settings | `/settings/*` | `/pwa/settings` |

---

## Migration Scenarios

### Scenario 1: Keep Both (Recommended)
**Strategy**: Maintain both portals for different audiences

**Desktop `/portal`**:
- Target: Power users, merchants, desktop users
- Features: Full configuration, quick generation, keyboard-first
- Access: Web browser on desktop/tablet

**PWA `/pwa/portal`**:
- Target: Mobile subscribers, casual users
- Features: Wallet-first, simple navigation, touch-friendly
- Access: PWA on mobile devices

**Pros**:
- No migration risk
- Best UX for each platform
- Users choose based on device/preference

**Cons**:
- Maintain two codebases
- Feature parity challenges
- Potential confusion about which to use

---

### Scenario 2: Deprecate Desktop, Enhance PWA
**Strategy**: Build desktop `/portal` features into `/pwa/vouchers/generate`, then deprecate

**Required Work**:
1. ✅ **PWA wizard already has**:
   - Full voucher configuration
   - Campaign templates
   - Real-time pricing
   - Smart payee detection
   - Settlement vouchers
   - State management

2. ❌ **Still missing in PWA**:
   - Quick amount buttons (easy to add)
   - QR generation after creation (easy to add)
   - File attachments for settlement (medium effort)
   - External metadata editor (medium effort)
   - Configurable quick amounts (easy to add)

3. **Deprecation Path**:
   - Week 1-2: Add missing features to `/pwa/vouchers/generate`
   - Week 3-4: Beta testing with power users
   - Month 2: Add desktop layout for PWA (responsive)
   - Month 3: Deprecation notice on `/portal`
   - Month 4: Hard redirect `/portal` → `/pwa/portal`

**Pros**:
- Single codebase to maintain
- PWA works on all devices
- Modern architecture (Vue 3, Inertia.js)

**Cons**:
- Desktop users lose keyboard-optimized UI
- Migration risk for power users
- PWA requires mobile-first thinking

---

### Scenario 3: Unify as Responsive Portal
**Strategy**: Build single `/portal` that adapts to mobile/desktop

**Required Work**:
1. Refactor desktop `/portal` to use responsive layout
2. Add bottom sheet UI for mobile
3. Add touch gestures for mobile
4. Keep keyboard shortcuts for desktop
5. Maintain feature parity across breakpoints

**Pros**:
- Single URL for all users
- Single codebase
- Responsive design benefits

**Cons**:
- Massive refactor effort
- Risk of compromised UX (neither great for mobile nor desktop)
- Desktop users get mobile-first UI

---

## Recommendation: Keep Both (Scenario 1)

**Rationale**:
1. **Different purposes**: Desktop `/portal` is a **voucher generator**, PWA `/pwa/portal` is a **wallet dashboard**
2. **Different audiences**: Desktop for power users, PWA for mobile subscribers
3. **Feature complete**: `/pwa/vouchers/generate` already matches desktop `/portal` capabilities
4. **Low maintenance**: Both codebases are stable and well-tested
5. **User choice**: Let users pick based on device/workflow

**Action Items**:
- ✅ **No deprecation needed** - Keep both portals
- ✅ **Document use cases** - Update user docs to clarify when to use each
- ✅ **Add cross-links**:
  - Desktop `/portal` → "Try mobile app" link to `/pwa/portal`
  - PWA `/pwa/portal` → "Desktop version" link to `/portal`
- ✅ **Monitor usage** - Track analytics to see which portal is preferred
- ✅ **Consider renaming**:
  - Desktop `/portal` → `/generate` (clearer purpose)
  - PWA `/pwa/portal` → `/pwa/home` or `/pwa/dashboard` (clearer purpose)

---

## Appendix: File Locations

### Desktop Portal
- **Route**: `routes/web.php:70` (`/portal`)
- **Controller**: `app/Http/Controllers/PortalController.php`
- **View**: `resources/js/pages/Portal.vue` (1,693 lines)
- **Config**: `config/portal.php`
- **Middleware**: `requires.mobile`, `requires.balance`

### PWA Portal
- **Route**: `packages/pwa-ui/routes/pwa.php:27` (`/pwa/portal`)
- **Controller**: `packages/pwa-ui/src/Http/Controllers/PwaPortalController.php`
- **View**: `packages/pwa-ui/resources/js/pages/Pwa/Portal.vue` (161 lines)
- **Middleware**: `auth`, `ValidateSessionWithWorkOS`

### PWA Voucher Generate
- **Route**: `packages/pwa-ui/routes/pwa.php:34` (`/pwa/vouchers/generate`)
- **Controller**: `packages/pwa-ui/src/Http/Controllers/PwaVoucherController.php::create()`
- **View**: `packages/pwa-ui/resources/js/pages/Pwa/Vouchers/Generate.vue` (2,481 lines)
- **Features**: Full voucher wizard with all desktop `/portal` capabilities

---

## Conclusion

**Desktop `/portal` should NOT be deprecated** because:
1. It serves a different purpose (voucher generator vs wallet dashboard)
2. Desktop users need keyboard-optimized quick generation UI
3. PWA `/pwa/vouchers/generate` already exists as the mobile equivalent
4. Both can coexist peacefully with clear use case separation

**If deprecation is still desired**, the path is:
1. Add missing quick-generation features to `/pwa/vouchers/generate`
2. Build desktop-optimized layout for PWA (responsive)
3. Migrate power users gradually
4. Hard redirect `/portal` → `/pwa/portal` after 3-6 months

**Estimated effort for deprecation**: 6-8 weeks of development + 3-6 months of migration
