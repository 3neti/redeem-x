# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Development Environment

This project uses **Laravel Herd** for local development.

**Local URL**: `http://redeem-x.test`

**Key Points**:
- No need to run `php artisan serve` - Herd handles the web server
- Database, queue worker, and Vite still need to be started manually (via `composer dev`)
- When testing API endpoints, use `redeem-x.test` instead of `localhost:8000`
- Herd provides automatic HTTPS support for `.test` domains

## Development Commands

### Initial Setup
```bash
composer setup
```
Runs the complete setup: composer install, creates .env from .env.example, generates app key, runs migrations, npm install, and builds frontend assets.

### Development Server
```bash
composer dev
```
Starts all development services concurrently:
- `php artisan serve` (web server on port 8000)
- `php artisan queue:listen --tries=1` (queue worker)
- `php artisan pail --timeout=0` (log viewer)
- `npm run dev` (Vite HMR)

For SSR development:
```bash
composer dev:ssr
```

### Testing
```bash
composer test
# or directly
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run specific test
php artisan test --filter test_name
```
Uses Pest PHP testing framework.

### Testing Notifications
```bash
# Test notification system end-to-end (with preview, no actual sending)
php artisan test:notification --fake

# Send real notification to custom email
php artisan test:notification --email=your@email.com

# Send to email and SMS
php artisan test:notification --email=your@email.com --sms=+639171234567

# Test with rich inputs (location, signature, selfie)
php artisan test:notification --email=your@email.com --with-location --with-signature --with-selfie

# Test specific input combinations
php artisan test:notification --fake --with-location  # Location only
php artisan test:notification --fake --with-signature --with-selfie  # Images only
```
Generates a test voucher (₱1), redeems it, and sends/previews notifications.

**How it works:**
- Automatically disables disbursement during testing (config override)
- Waits for cash entity creation before redemption (avoids race condition)
- Tests complete notification flow: generation → redemption → email/SMS
- Uses templates from `lang/en/notifications.php`
- Test data loaded from `tests/Fixtures/` (location, signature, selfie)
- Requires queue worker running for non-fake mode

### Testing SMS
```bash
# Test SMS sending directly (bypasses notifications)
php artisan test:sms 09173011987

# Send custom message
php artisan test:sms 09173011987 "Custom test message"

# Use custom sender ID
php artisan test:sms 09173011987 --sender=TXTCMDR
```
Sends SMS directly via EngageSpark for testing SMS configuration.

### Testing Payment Gateway (Omnipay)
```bash
# Test disbursement (send money)
php artisan omnipay:disburse 100 09173011987 GXCHPHM2XXX INSTAPAY

# Generate QR code for receiving payments
php artisan omnipay:qr 09173011987 100 --save=qr_code.txt

# Check account balance (requires API access)
php artisan omnipay:balance --account=113-001-00001-9
```
Tests NetBank payment gateway integration via Omnipay framework.

**Key features:**
- Settlement rail validation (INSTAPAY/PESONET)
- EMI detection (GCash, PayMaya)
- KYC address workaround for testing
- OAuth2 with token caching
- Comprehensive logging

### Managing Feature Flags
```bash
# List all feature flags for a user
php artisan feature:list lester@hurtado.ph

# Enable a feature for a user
php artisan feature:manage settlement-vouchers user@example.com --enable

# Disable a feature for a user
php artisan feature:manage settlement-vouchers user@example.com --disable

# Check feature status
php artisan feature:manage settlement-vouchers user@example.com --status
```
Manages per-user feature flags (settlement vouchers, advanced pricing, beta features).

**Available features:**
- `settlement-vouchers` - Pay-in voucher functionality ("Settle" nav link)
- `advanced-pricing-mode` - Advanced pricing features
- `beta-features` - Experimental features

**Important:** See `docs/FEATURE_ENABLEMENT_STRATEGY.md` for complete rollout strategy and best practices.

### Testing Top-Up (Direct Checkout)
```bash
# Test top-up flow with default amount (₱500)
php artisan test:topup

# Test with custom amount
php artisan test:topup 1000

# Test with specific user
php artisan test:topup 500 --user=user@example.com

# Test with preferred institution
php artisan test:topup 500 --institution=GCASH

# Auto-simulate payment (skip manual simulation)
php artisan test:topup 500 --simulate
```
Tests the complete top-up flow: initiate → database check → payment simulation → wallet credit.

**How it works:**
- Uses first user or creates one if none exists
- Initiates top-up via NetBank Direct Checkout
- In fake mode (USE_FAKE=true), automatically redirects to callback
- Simulates payment webhook
- Credits user wallet via Bavix Wallet
- Validates amount matches expectation
- Shows before/after balance comparison

**Configuration:**
```bash
# Enable mock mode for testing without NetBank credentials
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true

# Or use real NetBank sandbox
NETBANK_DIRECT_CHECKOUT_USE_FAKE=false
NETBANK_DIRECT_CHECKOUT_ACCESS_KEY=your_access_key
NETBANK_DIRECT_CHECKOUT_SECRET_KEY=your_secret_key
NETBANK_DIRECT_CHECKOUT_ENDPOINT=https://api-sandbox.netbank.ph/v1/collect/checkout
```

### Code Quality
```bash
# Format code (Prettier)
npm run format

# Check formatting
npm run format:check

# Lint (ESLint with auto-fix)
npm run lint

# PHP linting
./vendor/bin/pint
```

### Frontend Build
```bash
npm run build        # Build for production
npm run build:ssr    # Build with SSR support
npm run dev          # Development with HMR
```

## UI Improvement Methodology

When implementing UI improvements (e.g., progressive disclosure, simplified/advanced modes), follow this proven approach:

### Phase 1: Infrastructure Setup
1. **Create Feature Branch**
   ```bash
   git checkout -b feature/ui-component-name-v2
   ```

2. **Document the Plan**
   - Create implementation plan in `docs/COMPONENT_NAME_PLAN.md`
   - Include: scope, problem statement, solution, technical decisions
   - Save plan before starting implementation

3. **Add Feature Flags**
   ```php
   // config/component.php
   'ui_version' => env('COMPONENT_UI_VERSION', 'v2'),
   'feature_flags' => [
       'new_ui' => env('COMPONENT_UI_V2_ENABLED', false),
   ],
   ```

4. **Add User Preferences Storage**
   - Create migration for `ui_preferences` JSON column (if not exists)
   - Add to User model fillable and casts
   - Store user's UI mode preference (simple/advanced)

5. **Update .env.example**
   ```bash
   COMPONENT_UI_VERSION=v2
   COMPONENT_UI_V2_ENABLED=false
   ```

### Phase 2: Parallel Development
1. **Preserve Legacy UI**
   - **DO NOT** modify existing component file
   - Legacy file remains frozen during development

2. **Create New UI Version**
   ```bash
   # Copy existing to new version
   cp resources/js/pages/Component.vue resources/js/pages/ComponentV2.vue
   ```

3. **Create Composables**
   ```typescript
   // resources/js/composables/useComponentMode.ts
   export function useComponentMode(initialMode = 'simple') {
     const mode = ref(initialMode)
     const switchMode = async (newMode) => {
       mode.value = newMode
       await axios.put('/api/v1/preferences/component-mode', { mode: newMode })
     }
     return { mode, switchMode }
   }
   ```

4. **Add Dual Routes**
   ```php
   // routes/web.php
   Route::get('/component', [Controller::class, 'create'])->name('component');
   Route::get('/component/legacy', [Controller::class, 'createLegacy'])->name('component.legacy');
   ```

5. **Controller Logic**
   ```php
   public function create(Request $request) {
       $useV2 = config('component.feature_flags.new_ui');
       
       if ($request->user()->ui_preferences['component_ui_version'] ?? null === 'legacy') {
           $useV2 = false;
       }
       
       $component = $useV2 ? 'ComponentV2' : 'Component';
       return Inertia::render($component, [...]);
   }
   ```

### Phase 3: Implementation
1. **Build Simple Mode First**
   - Minimal fields only (3-5 fields max)
   - Clear "Switch to Advanced" link
   - Test generation workflow

2. **Build Advanced Mode**
   - Use Collapsible components for cards
   - Add expand/collapse all controls
   - Default collapsed for non-essential cards

3. **Test Both UIs**
   ```bash
   # Test new UI
   open http://localhost:8000/component
   
   # Test legacy UI
   open http://localhost:8000/component/legacy
   ```

### Phase 4: Deployment
1. **Gradual Rollout**
   - Week 1: Enable for team (set `COMPONENT_UI_V2_ENABLED=true` locally)
   - Week 2: Beta release (25% of users)
   - Week 3: Gradual increase (50% → 75% → 100%)
   - Month 2-3: Stabilization (legacy still available)
   - Month 4: Remove legacy route

2. **Rollback Plan**
   ```bash
   # Option 1: Disable feature flag
   COMPONENT_UI_V2_ENABLED=false
   
   # Option 2: Emergency rollback
   git revert <commit-hash>
   ```

### Key Principles
- **Never break existing UI** - Legacy route always works
- **Progressive disclosure** - Show essential first, advanced on request
- **User preference storage** - Persist mode choice in database
- **Feature flags** - Easy toggle between versions
- **Parallel development** - Build new without touching old
- **Semantic commits** - Clear commit messages per phase

### File Organization Pattern
```
resources/js/pages/component/
├── Component.vue           # Legacy (frozen)
├── ComponentV2.vue         # New UI (development target)
└── components/
    ├── ModeToggle.vue      # Mode switcher
    ├── SimpleModeForm.vue  # Minimal form
    └── AdvancedModeForm.vue # Full form
```

### Testing Checklist
- [ ] New UI works: Simple mode generates correctly
- [ ] New UI works: Advanced mode has all features
- [ ] Legacy UI still works (no regressions)
- [ ] Mode preference persists across sessions
- [ ] Both UIs submit to same endpoint
- [ ] Form validation works in both modes
- [ ] Cost breakdown updates correctly

## Git Workflow

### Branch Strategy
All feature development should use feature branches, not direct commits to `main`.

```bash
# Create a new feature branch
git checkout -b feature/descriptive-name

# Examples:
git checkout -b feature/emi-rail-restrictions
git checkout -b fix/settlement-rail-validation
git checkout -b refactor/cleanup-debug-logs
```

### Development Workflow
```bash
# 1. Create feature branch from main
git checkout main
git pull origin main
git checkout -b feature/your-feature

# 2. Make changes and commit
git add -A
git commit -m "descriptive commit message"

# 3. Push feature branch
git push origin feature/your-feature

# 4. Create PR or merge to main after review
git checkout main
git merge feature/your-feature
git push origin main

# 5. Clean up feature branch
git branch -d feature/your-feature
git push origin --delete feature/your-feature
```

### Commit Message Guidelines
- Use descriptive commit messages
- First line: brief summary (50 chars or less)
- Add detailed description if needed
- Reference related issues/tickets if applicable

**Good examples:**
```
Add EMI rail restriction validation

Fix settlement rail validation for GCash transactions

Refactor: Clean up debug logging in redemption flow
```

**Bad examples:**
```
fixed bug
update
WIP
```

### IMPORTANT: Avoid Direct Main Commits
- **DO NOT** commit directly to `main` for feature work
- Use feature branches for all changes
- Exception: Hotfixes in production emergencies (document why)

## Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Vue 3 + TypeScript + Inertia.js
- **Styling**: Tailwind CSS v4
- **Authentication**: Laravel Fortify + WorkOS AuthKit
- **UI Components**: reka-ui (headless components)
- **Database**: SQLite (default), supports MySQL/PostgreSQL
- **Testing**: Pest PHP

### Campaign System
**Campaigns** are reusable voucher generation templates that store complete `VoucherInstructionsData`:
- Each user automatically gets 2 default campaigns: "Blank Template" and "Standard Campaign"
- Campaigns define: cash amount, input fields, validations, feedback, rider info, count, prefix, mask, TTL
- Uses many-to-many relationship with vouchers via `campaign_voucher` pivot table
- **CampaignVoucher Pivot Model**: Dedicated `CampaignVoucher` model (extends `Pivot`) for type-safe access
  - Auto-casts `instructions_snapshot` to array
  - Provides `campaign()` and `voucher()` relationships
  - Used via `$campaign->campaignVouchers()` or `$campaign->vouchers()` (with `->using(CampaignVoucher::class)`)
  - Keeps Voucher package clean (no modifications needed)
- Pivot table stores `instructions_snapshot` for historical auditability
- Settings > Campaigns UI for CRUD operations

### Reusable Components
**VoucherInstructionsForm.vue** - Shared form component for voucher instructions:
- Used in: Generate Vouchers, Create Campaign, Edit Campaign, View Voucher (readonly)
- Props: `modelValue`, `inputFieldOptions`, `validationErrors`, `showCountField`, `showJsonPreview`, `readonly`
- Supports v-model binding for reactive form data
- Readonly mode for displaying voucher/campaign details (used in Voucher Show page)
- Includes: Basic Settings, Input Fields, Validation Rules, Feedback Channels, Rider, JSON Preview
- Voucher Show page uses tabs to separate Details and Instructions views

**RedeemWidget.vue** - Configurable voucher redemption widget for iframe embedding:
- Single input field for voucher code with submit button
- Configurable via `config/redeem.php` or environment variables
- Elements can be shown/hidden: logo, app name, label, title, description
- Customizable text: title, label, placeholder, button text
- Uses Wayfinder route functions for type-safe navigation
- Perfect for embedding in external sites via iframe

**Voucher QR Code Components** - Reusable QR generation and sharing:
- `useVoucherQr.ts` - Client-side QR generation composable using `qrcode` npm package
- `QrDisplay.vue` - Shared component for displaying QR codes with loading/error states
- `QrSharePanel.vue` - Sharing panel with copy, download, email, SMS, WhatsApp, native share
- `VoucherQrSharePanel.vue` - Voucher-specific wrapper for QR sharing
- Voucher Show page displays QR code for unredeemed, non-expired vouchers
- QR codes encode redemption URL: `http://domain/redeem?code={CODE}`
- Instant generation (client-side, no API latency)
- Reuses 80% of wallet QR components for consistent UX

### KYC Redemption System
**HyperVerge Integration** for identity verification during voucher redemption:
- When the `kyc` input field is enabled on a voucher, the redeemer must complete KYC verification before redemption
- **Important**: KYC is NOT a text input - it's handled as a special flow on the Finalize page (like location/selfie/signature)
- KYC data is stored in the `Contact` model using **schemaless attributes** (via `HasAdditionalAttributes` trait) for flexibility
- No additional database columns needed - all KYC data stored in the `meta` JSON column
- Package: `3neti/hyperverge` v1.0+ (published on Packagist)

**Redemption Flow with KYC**:
1. User enters mobile number on Wallet page
2. Completes all required inputs (location, selfie, signature, etc.)
3. On Finalize page, if KYC required:
   - "Identity Verification Required" card is displayed
   - Click "Start Identity Verification" → Redirects to HyperVerge mobile flow
4. User completes KYC in HyperVerge app (selfie + ID verification)
5. Callback returns to KYCStatus page with auto-polling (every 5 seconds)
6. On approval: Auto-redirects to Finalize page with KYC verified badge
7. Confirm button enabled → Completes redemption with KYC validation

**Contact Model KYC Fields** (schemaless attributes via `meta` column):
- `kyc_transaction_id` - Unique HyperVerge transaction ID
- `kyc_status` - Enum: pending, processing, approved, rejected, needs_review
- `kyc_onboarding_url` - HyperVerge verification URL
- `kyc_submitted_at` - When user completed KYC in app
- `kyc_completed_at` - When HyperVerge processed results
- `kyc_rejection_reasons` - Array of rejection reasons (if rejected)
- All accessed via `$contact->kyc_status`, `$contact->kyc_transaction_id`, etc.
- Stored in JSON `meta` column for flexibility (no schema changes needed)

**KYC Actions**:
- `InitiateContactKYC` - Generates HyperVerge onboarding link for contact
- `ValidateContactKYC` - Checks if contact has approved KYC
- `FetchContactKYCResult` - Retrieves results from HyperVerge, stores images, updates status
- `ProcessRedemption::validateKYC()` - Validates KYC before redemption (blocks if not approved)

**Controllers & Routes**:
- `KYCRedemptionController` - Handles KYC flow:
  - `GET /redeem/{voucher}/kyc/initiate` - Start KYC flow
  - `GET /redeem/{voucher}/kyc/callback` - Handle HyperVerge callback
  - `GET /redeem/{voucher}/kyc/status` - AJAX polling for status updates
- `RedeemController::finalize()` - Checks KYC status and passes to frontend

**Frontend Pages**:
- `KYCStatus.vue` - Status page with auto-polling, shows pending/approved/rejected states
- `Finalize.vue` - Updated with KYC card section:
  - Shows "Start Identity Verification" button if not completed
  - Shows "✓ Identity Verified" badge if approved
  - Disables confirm button until KYC approved

**Environment Variables**:
```bash
# HyperVerge API credentials (from 3neti/hyperverge package)
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=your_app_id
HYPERVERGE_APP_KEY=your_app_key
HYPERVERGE_URL_WORKFLOW=onboarding
```

**Key Features**:
- **Contact-level KYC**: Once verified, contact can redeem multiple KYC vouchers without re-verification
- **Auto-polling**: Status page updates every 5 seconds until approved/rejected
- **Media storage**: KYC ID cards and selfies stored via Spatie Media Library
- **Face verification**: `HasFaceVerification` trait enables future biometric auth
- **Graceful errors**: Handles API timeouts, network errors, session expiration
- **Mobile-optimized**: HyperVerge flow designed for mobile camera access

### Form Flow System
**Fully Autonomous Multi-Step Form System** for collecting user inputs in a wizard-style flow:
- Package: `lbhurtado/form-flow-manager` (mono-repo package)
- DirXML-style driver architecture for transforming domain data into form flows
- Built-in FormHandler for basic inputs (text, email, date, number, select, checkbox, textarea, file)
- Plugin system for specialized handlers (location, selfie, signature, KYC)
- HyperVerge-style two-session flow: server-to-server POST → browser GET

**Architecture**:
```
Host App → POST /form-flow/start {reference_id, steps, callbacks}
           ↓
           Returns {flow_url}
           ↓
User → GET flow_url (separate session)
       ↓
       Renders UI → Collects data → Stores by reference_id
       ↓
       Triggers on_complete callback with collected data
```

**Built-in Form Handler**:
- Handler name: `form`
- Supports 8 field types: text, email, date, number, textarea, select, checkbox, file
- Dynamic validation rules based on field configuration
- Renders GenericForm.vue component
- Used when no specialized plugin exists for an input type

**Field Configuration**:
```json
{
  "name": "field_name",
  "type": "text|email|date|number|textarea|select|checkbox|file",
  "label": "Field Label",
  "placeholder": "Optional placeholder",
  "required": true,
  "options": ["Option1", "Option2"],  // For select type
  "validation": ["email", "max:255"]  // Additional Laravel rules
}
```

**Usage Example**:
```bash
# Create a form flow
curl -X POST http://app.test/form-flow/start \
  -H "Content-Type: application/json" \
  -d '{
    "reference_id": "unique-ref-123",
    "steps": [
      {
        "handler": "form",
        "config": {
          "title": "Personal Information",
          "description": "Please provide your details",
          "fields": [
            {"name": "name", "type": "text", "required": true},
            {"name": "email", "type": "email", "required": true},
            {"name": "birthdate", "type": "date", "required": true}
          ]
        }
      },
      {
        "handler": "location",
        "config": {"require_address": true}
      }
    ],
    "callbacks": {
      "on_complete": "https://app.test/callback",
      "on_cancel": "https://app.test/cancel"
    }
  }'

# Response:
# {
#   "success": true,
#   "reference_id": "unique-ref-123",
#   "flow_url": "http://app.test/form-flow/flow-abc123"
# }
```

**Components & Files**:
- Backend:
  - `FormFlowController` - HTTP endpoints for flow management
  - `FormFlowService` - Session-based state management
  - `FormHandler` - Built-in handler for basic inputs
  - `FormHandlerInterface` - Contract for plugin handlers
  - `FormFlowInstructionsData` - DTO for flow configuration
  - `FormFlowStepData` - DTO for individual step configuration
- Frontend:
  - `GenericForm.vue` - Dynamic form component (published to `resources/js/pages/FormFlow/`)
  - Inertia.js for seamless navigation
  - Tailwind CSS + shadcn/ui components
- Testing:
  - 75 tests (175 assertions) covering all functionality
  - TDD approach with Pest PHP

**Routes**:
- `POST /form-flow/start` - Create new flow (CSRF exempt, server-to-server)
- `GET /form-flow/{flow_id}` - Render current step or get state (JSON)
- `POST /form-flow/{flow_id}/step/{step}` - Submit step data
- `POST /form-flow/{flow_id}/complete` - Mark flow complete, trigger callback
- `POST /form-flow/{flow_id}/cancel` - Cancel flow, trigger cancel callback
- `DELETE /form-flow/{flow_id}` - Clear flow state

**Session Storage**:
- Flow state stored in session: `form_flow.{flow_id}`
- Reference mapping: `form_flow_ref.{reference_id}` → `flow_id`
- Data structure: status, current_step, completed_steps, collected_data, timestamps

**Plugin Development**:
Create specialized handlers by:
1. Implement `FormHandlerInterface`
2. Create service provider that auto-registers handler
3. Publish Vue components to `resources/js/pages/FormFlow/`
4. Package auto-discovers via Laravel Package Discovery

**Plugin Architecture**:
- Core (`form-flow-manager`): Lightweight orchestration engine
- Plugins (`form-handler-*`): Optional specialized handlers
- Auto-discovery: Plugins self-register via service providers
- No hardcoded dependencies: Core doesn't know about plugins
- Host app chooses: Install only the plugins you need

See [Plugin Architecture Documentation](packages/form-flow-manager/PLUGIN_ARCHITECTURE.md) for creating custom handlers.

**Available Plugins**:
- `form-handler-location` - GPS capture, reverse geocoding, map snapshots
- `form-handler-selfie` - Camera capture (planned)
- `form-handler-signature` - Digital signature (planned)
- `form-handler-kyc` - Identity verification (planned)

**Driver System** (Advanced):
- Transform domain-specific data (e.g., VoucherInstructionsData) to FormFlowInstructionsData
- YAML-based driver configs in `config/form-flow-drivers/`
- Template rendering with Twig-style expressions
- Mapping engine with conditional logic
- See `packages/form-flow-manager/config/form-flow-drivers/` for examples

**Key Features**:
- **Autonomous**: No manual step management - flows handle navigation automatically
- **Validation**: Step-level validation with Laravel rules
- **Callbacks**: Webhook notifications on completion/cancellation
- **Reusable**: Share flows across multiple applications
- **Type-safe**: Full TypeScript support on frontend
- **Testable**: Comprehensive test coverage with Pest PHP

### Laravel Wayfinder Integration
This project uses Laravel Wayfinder to generate type-safe, auto-generated TypeScript route definitions from Laravel controllers. Route files are generated in `resources/js/actions/` mirroring the controller structure.

**Key concepts:**
- Generated route files in `resources/js/actions/` map directly to Laravel controllers
- Route functions provide `.url()`, `.get()`, `.post()`, etc. methods
- Forms can use `.form` property for proper method spoofing
- Routes are regenerated automatically during development

Example usage:
```typescript
import { edit, update } from '@/actions/App/Http/Controllers/Settings/ProfileController'

// Navigate to route
router.visit(edit.url())

// Make request
router.patch(update.url(), { name: 'New Name' })
```

### Project Structure

#### Backend (`app/`)
- `Http/Controllers/` - Controllers organized by domain (Settings, etc.)
- `Http/Middleware/` - Custom middleware (HandleInertiaRequests, HandleAppearance)
- `Http/Requests/` - Form requests organized by domain
- `Models/` - Eloquent models
- `Providers/` - Service providers (AppServiceProvider, FortifyServiceProvider)

#### Frontend (`resources/js/`)
- `actions/` - Auto-generated Wayfinder route definitions (mirrors controller structure)
- `components/` - Vue components
  - `ui/` - Reusable UI components (shadcn-style)
  - App-specific components (AppShell, AppHeader, AppSidebar, NavMain, NavUser)
- `composables/` - Vue composables (useAppearance, useInitials, useTwoFactorAuth)
- `layouts/` - Layout components
  - `app/` - Main app layouts (AppHeaderLayout, AppSidebarLayout)
  - `settings/` - Settings-specific layout
- `pages/` - Inertia page components
  - `settings/` - Settings pages
  - `auth/` - Authentication pages (defined in FortifyServiceProvider)
- `routes/` - Frontend route configuration/helpers
- `types/` - TypeScript type definitions
- `wayfinder/` - Wayfinder core utilities

#### Routes (`routes/`)
- `web.php` - Main web routes with WorkOS authentication
- `auth.php` - Authentication routes (Laravel Fortify)
- `settings.php` - Settings-related routes
- `console.php` - Artisan console commands

### Authentication Flow
Uses **Laravel WorkOS** for authentication:
- WorkOS session validation via `ValidateSessionWithWorkOS` middleware
- Two-factor authentication support
- Account deletion integrated with WorkOS AuthKit
- Configure WorkOS credentials in `.env`:
  - `WORKOS_CLIENT_ID`
  - `WORKOS_API_KEY`
  - `WORKOS_REDIRECT_URL`

### Inertia.js Conventions
- Page components in `resources/js/pages/` are rendered by controllers using `Inertia::render()`
- Props passed from controllers are available in Vue components
- Shared data configured in `HandleInertiaRequests` middleware
- Use `router.visit()`, `router.get()`, `router.post()` for navigation

### Database
- Default connection: SQLite (`database/database.sqlite`)
- Migrations in `database/migrations/`
- Factories in `database/factories/`
- Uses database driver for sessions, cache, and queue by default

### TypeScript Configuration
- Strict TypeScript enabled
- Path aliases configured: `@/` resolves to `resources/js/`
- Types in `resources/js/types/`

### Settlement Rail Selection
**INSTAPAY vs PESONET** - Users can choose disbursement rail when generating vouchers:
- **INSTAPAY**: Real-time transfer, ≤₱50k, ₱10 fee
- **PESONET**: Next business day, ≤₱1M, ₱25 fee  
- **Auto mode**: Smart selection based on amount (<₱50k = INSTAPAY, ≥₱50k = PESONET)

**Fee Strategy Options**:
- `absorb`: Issuer pays the fee (default)
- `include`: Fee deducted from voucher amount
- `add`: Fee added to disbursement (redeemer receives voucher + fee)

**UI Location**: Voucher Generation page → Basic Settings → Disbursement Settings

**Data Flow**:
1. Frontend: `CashInstructionForm.vue` captures rail + fee strategy
2. API: `GenerateVouchers` action validates and stores in `VoucherInstructionsData`
3. Backend: `DisburseInputData::fromVoucher()` reads from voucher instructions
4. Gateway: `OmnipayPaymentGateway` sends to NetBank with selected rail

**Important**: See `docs/DEBUGGING_SETTLEMENT_RAIL.md` for debugging guide and common pitfalls when adding new form fields.

### Payment Gateway Configuration

**Dual Gateway Support:**
The application supports two payment gateway implementations that can be switched via environment variable:
- **Old implementation**: Direct API calls (x-change style) - `NetbankPaymentGateway`
- **New implementation**: Omnipay framework (recommended) - `OmnipayPaymentGateway`

**Environment Variables:**
```bash
USE_OMNIPAY=true|false        # Switch between implementations
PAYMENT_GATEWAY=netbank       # Gateway selection (netbank, icash, etc.)
DISBURSE_DISABLE=true|false   # Enable/disable disbursement in redemption flow
```

**Recommended for production:** `USE_OMNIPAY=true`

**Benefits of Omnipay Implementation:**
- Settlement rail validation (INSTAPAY vs PESONET)
- EMI detection (GCash, PayMaya must use INSTAPAY)
- KYC address workaround for testing
- OAuth2 with token caching for better performance
- Better error handling and structured logging
- Comprehensive testing via Artisan commands
- Amount limit validation per rail
- Bank capability checking

**Disbursement Flow:**
1. User redeems voucher
2. Post-redemption pipeline runs (config/voucher-pipeline.php)
3. If `DISBURSE_DISABLE=false`, DisburseCash pipeline stage executes
4. Gateway resolves based on `USE_OMNIPAY` flag
5. Funds disbursed via selected implementation
6. Transaction logged and events fired

**Switching Implementations:**
```bash
# Enable Omnipay (recommended)
USE_OMNIPAY=true
php artisan config:clear
php artisan config:cache

# Rollback to direct API
USE_OMNIPAY=false
php artisan config:clear
php artisan config:cache
```

See `docs/OMNIPAY_INTEGRATION_PLAN.md` for detailed architecture and migration guide.

### Disbursement Failure Alerting & Audit Trail
**Comprehensive system for tracking and alerting on disbursement failures:**

**Phase 1: Immediate Alerting** ✅
- Real-time email notifications to admins/support when disbursements fail
- Configurable via `DISBURSEMENT_ALERT_ENABLED` and `DISBURSEMENT_ALERT_EMAILS`
- Email includes: voucher code, amount, redeemer mobile, error message, timestamp
- Non-blocking queued delivery (doesn't impact redemption flow)

**Phase 2: Audit Trail Database** ✅
- Every disbursement attempt logged in `disbursement_attempts` table
- Tracks: status (pending/success/failed), error details, request/response payloads
- Stored in payment-gateway package for reusability across projects
- Scopes for reporting: `failed()`, `success()`, `recent()`, `byGateway()`, `byErrorType()`

**Configuration:**
```bash
# .env
DISBURSEMENT_ALERT_ENABLED=true
DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com
```

**Key Features:**
- **Customer Service**: Immediate notification enables fast response to user issues
- **Bank Reconciliation**: Complete audit trail with reference IDs matching bank reports
- **Pattern Analysis**: Query failed attempts by error type, gateway, time period
- **Accountability**: Immutable record of all disbursement attempts
- **Package Architecture**: Audit infrastructure in payment-gateway package (reusable), notification logic in host app (customizable)

**Database Schema:**
```sql
-- Stores in packages/payment-gateway/database/migrations/
CREATE TABLE disbursement_attempts (
    id, voucher_id, user_id, voucher_code, amount, currency, mobile,
    bank_code, account_number, settlement_rail, gateway,
    reference_id UNIQUE, gateway_transaction_id,
    status, error_type, error_message, error_details (JSON),
    request_payload (JSON), response_payload (JSON),
    attempted_at, completed_at, timestamps
);
```

**Usage Examples:**
```php
// Query failed disbursements
$failures = DisbursementAttempt::failed()->recent(7)->get();

// Get timeout errors
$timeouts = DisbursementAttempt::byErrorType('network_timeout')->count();

// Find by reference for bank reconciliation
$attempt = DisbursementAttempt::where('reference_id', 'ABC-09171234567')->first();
```

See `docs/DISBURSEMENT_FAILURE_ALERTS.md` for complete documentation.

### Top-Up / Direct Checkout System
**Hybrid Architecture** allows users to add funds to their wallet via NetBank Direct Checkout:

**Package Layer** (`lbhurtado/payment-gateway`):
- `TopUpInterface` - Contract defining required methods for top-up models
- `TopUpResultData` - DTO for gateway responses
- `HasTopUps` trait - Reusable logic for any model that needs top-up functionality
- `CanCollect` trait - NetBank Direct Checkout API integration
- Gateway-agnostic design (supports netbank, future: stripe, paypal, etc.)

**Application Layer** (`app/`):
- `TopUp` model - Implements `TopUpInterface`, tracks payment status
- `User` model - Uses `HasTopUps` trait for wallet top-up functionality
- `TopUpController` - Handles initiation, callback, status polling
- `NetBankWebhookController` - Processes payment confirmations

**Top-Up Flow:**
1. User visits `/topup` → Enters amount → Selects payment method (optional)
2. Backend calls `$user->initiateTopUp(500, 'netbank', 'GCASH')`
3. Creates `TopUp` record with status `PENDING`
4. Redirects user to NetBank payment page (or mock callback in fake mode)
5. User completes payment in GCash/Maya/Bank app
6. NetBank webhook calls `/webhooks/netbank/payment`
7. Webhook marks TopUp as `PAID` and credits wallet
8. User returns to callback page, sees success status

**Key Features:**
- **Mock Mode**: Test without real API credentials (`USE_FAKE=true`)
- **Real-time Polling**: Status updates every 3 seconds on callback page
- **Wallet Integration**: Automatic credit via Bavix Wallet
- **Multi-Gateway Ready**: Interface-based design for future gateways
- **Payment History**: Tracks all top-up attempts with status
- **Institution Preference**: Users can specify GCash, Maya, BDO, BPI, etc.

**Available Methods** (via `HasTopUps` trait):
```php
$user->initiateTopUp(500, 'netbank', 'GCASH'); // Start top-up
$user->getTopUps(); // All top-ups
$user->getPendingTopUps(); // Pending payments
$user->getPaidTopUps(); // Successful top-ups
$user->getTopUpByReference('TOPUP-ABC123'); // Find by reference
$user->getTotalTopUps(); // Sum of paid top-ups
$user->creditWalletFromTopUp($topUp); // Credit wallet
```

**Routes:**
- `GET /topup` - Top-up page
- `POST /topup` - Initiate payment
- `GET /topup/callback` - Return after payment
- `GET /topup/status/{ref}` - Poll payment status
- `POST /webhooks/netbank/payment` - Payment webhook (no auth)

### Notification Templates
**Admin-level customizable templates** for voucher redemption notifications:
- Templates stored in `lang/en/notifications.php` using `{{ variable }}` syntax
- Supports dynamic variables: `{{ code }}`, `{{ formatted_amount }}`, `{{ mobile }}`, `{{ formatted_address }}`, etc.
- Powered by `TemplateProcessor` service with support for dot notation and recursive search
- `VoucherTemplateContextBuilder` flattens voucher data for easy templating
- Used in: Email notifications, SMS notifications (EngageSpark), webhook payloads
- See `docs/NOTIFICATION_TEMPLATES.md` for full documentation and customization guide

## Important Notes

### Configuration Data in Migrations
**CRITICAL: This project uses migrations for configuration data, not seeders.**

**When adding settings to VoucherSettings or any Spatie Settings class:**

1. ✅ **ALWAYS create a migration** - Never add to seeders
2. ✅ **Use insertOrIgnore()** - Ensure idempotency
3. ✅ **Implement down()** - Enable rollback capability
4. ✅ **Use config() fallbacks** - Don't hardcode environment-specific values
5. ✅ **Descriptive migration names** - e.g., `add_auto_disburse_minimum_to_voucher_settings`
6. ✅ **Group related settings** - Add multiple related settings in one migration

**Why migrations instead of seeders?**
- Settings are **required configuration** - app crashes without them
- `php artisan migrate` always runs in production, `db:seed` never does
- Guaranteed execution order and idempotency
- Rollback support for failed deployments
- Team coordination - new settings added automatically on `git pull` + `migrate`

**Example:**
```php
// ✅ CORRECT: Migration with insertOrIgnore()
return new class extends Migration {
    public function up(): void {
        DB::table('settings')->insertOrIgnore([
            'group' => 'voucher',
            'name' => 'new_setting',
            'payload' => json_encode(config('default.value', 50)),
            'locked' => false,
        ]);
    }
    
    public function down(): void {
        DB::table('settings')
            ->where('name', 'new_setting')
            ->delete();
    }
};
```

```php
// ❌ WRONG: Adding to seeder
class VoucherSettingsSeeder extends Seeder {
    public function run(): void {
        DB::table('settings')->insert([...]);
    }
}
```

**Historical context:**
- `VoucherSettingsSeeder` is deprecated (see deprecation notice in file)
- All voucher settings now managed via migrations
- Seeders are for test/demo data only, never for required configuration

**See full documentation:** `docs/CONFIGURATION_DATA_IN_MIGRATIONS.md`

### AI Development with Laravel Boost
This project uses Laravel Boost to provide AI agents (Claude Code, PhpStorm Junie, etc.) with contextual understanding of the codebase.

**Available MCP Tools:**
- `search-docs` - Search Laravel ecosystem documentation (version-specific)
- `tinker` - Execute PHP code in application context
- `database-query` - Query database directly
- `database-schema` - Read database schema
- `list-artisan-commands` - List available Artisan commands
- `browser-logs` - Read frontend error logs
- `application-info` - PHP/Laravel versions, packages, models
- Full list: `php artisan boost:mcp --help`

**Proactive Tool Usage Pattern:**
AI assistants should use Boost tools proactively at these stages:

**Investigation Phase** (before making changes):
```bash
php artisan boost:mcp database-query "SELECT COUNT(*) FROM table WHERE condition"
php artisan boost:mcp database-schema table_name
php artisan boost:mcp tinker "\$model = Model::first(); \$model->relationship;"
php artisan boost:mcp search-docs "laravel model events"
```

**Development Phase** (while coding):
```bash
php artisan boost:mcp tinker "// Prototype solution interactively"
php artisan boost:mcp list-artisan-commands | grep -i keyword
```

**Validation Phase** (after changes):
```bash
php artisan boost:mcp tinker "// Test changes interactively"
php artisan boost:mcp browser-logs  # For frontend debugging
```

**When NOT to use:** Simple file reads, git operations, or when traditional tools are more efficient.

**Custom Guidelines Location:**
Project-specific AI guidelines are in `.ai/guidelines/` and include:
- **Domain knowledge** - Vouchers, cash entities, payments, top-up system
- **Package documentation** - All 9 mono-repo packages
- **Testing patterns** - Pest v4, factories, mocking strategies
- **Frontend conventions** - Vue 3, Inertia.js, Wayfinder patterns
- **Artisan commands** - Custom test commands with examples

**Updating Guidelines:**
```bash
# Refresh Laravel ecosystem guidelines after package updates
php artisan boost:update

# Regenerate all guideline files
php artisan boost:install
```

Custom guidelines in `.ai/guidelines/` take precedence over generic guidelines. See `.ai/guidelines/README.md` for complete documentation.

### Wayfinder Route Generation
When adding or modifying Laravel routes, Wayfinder automatically regenerates TypeScript route definitions. Do not manually edit files in `resources/js/actions/` - they are auto-generated.

### WorkOS Integration
Authentication is managed by WorkOS. User management, password resets, and email verification go through WorkOS AuthKit, not Laravel's built-in auth system.

### Code Style
- PHP: Uses Laravel Pint (opinionated PSR-12)
- TypeScript/Vue: ESLint + Prettier with Tailwind CSS plugin
- Run formatters before committing

### Environment Setup
Copy `.env.example` to `.env` and configure:
- Database connection (SQLite by default, no config needed)
- WorkOS credentials (required for authentication)
- Mail configuration (defaults to log driver)
