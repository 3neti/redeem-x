# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

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

### Notification Templates
**Admin-level customizable templates** for voucher redemption notifications:
- Templates stored in `lang/en/notifications.php` using `{{ variable }}` syntax
- Supports dynamic variables: `{{ code }}`, `{{ formatted_amount }}`, `{{ mobile }}`, `{{ formatted_address }}`, etc.
- Powered by `TemplateProcessor` service with support for dot notation and recursive search
- `VoucherTemplateContextBuilder` flattens voucher data for easy templating
- Used in: Email notifications, SMS notifications (EngageSpark), webhook payloads
- See `docs/NOTIFICATION_TEMPLATES.md` for full documentation and customization guide

## Important Notes

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
