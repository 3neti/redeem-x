# Middleware Test Suite

TDD test suite for onboarding guard middleware.

## Running Tests

```bash
# Run all middleware tests
vendor/bin/pest --group=middleware

# Run specific test file
vendor/bin/pest tests/Feature/Middleware/RequiresMobileMiddlewareTest.php
vendor/bin/pest tests/Feature/Middleware/RequiresWalletBalanceMiddlewareTest.php

# Run with coverage
vendor/bin/pest --group=middleware --coverage

# Run in watch mode (auto-rerun on file changes)
vendor/bin/pest --group=middleware --watch
```

## Test Coverage

### RequiresMobile Middleware
- ✅ Allows users with mobile number
- ✅ Redirects users without mobile to profile
- ✅ Includes flash message on redirect
- ✅ Preserves full URL including query params
- ✅ Blocks bulk voucher generation without mobile
- ✅ Blocks top-up without mobile
- ✅ Allows top-up when mobile exists
- ✅ Handles mobile in different formats (E.164, national)
- ✅ Does not block unauthenticated users (auth middleware runs first)

### RequiresWalletBalance Middleware
- ✅ Allows users with positive balance
- ✅ Redirects users with zero balance to top-up
- ✅ Includes flash message for insufficient balance
- ✅ Blocks when balance is exactly zero
- ✅ Allows even small positive balance (₱0.05)
- ✅ Blocks negative balance (edge case)
- ✅ Blocks bulk generation with zero balance
- ✅ Preserves return URL with query params
- ✅ Does not apply to top-up routes
- ✅ Runs after mobile check in middleware chain

## Prerequisites

Before running tests, ensure:
1. Middleware files exist:
   - `app/Http/Middleware/RequiresMobile.php`
   - `app/Http/Middleware/RequiresWalletBalance.php`
2. Middleware registered in `app/Http/Kernel.php`:
   ```php
   protected $middlewareAliases = [
       'requires.mobile' => \App\Http\Middleware\RequiresMobile::class,
       'requires.balance' => \App\Http\Middleware\RequiresWalletBalance::class,
   ];
   ```
3. Routes have middleware applied (see `routes/web.php`)

## Expected Test Flow

### First Run (All Red 🔴)
Tests will fail because:
- Middleware not registered in routes
- Profile controller not handling `return_to` parameter
- TopUp controller not passing `reason` to view

### Second Run (All Green ✅)
After implementing:
1. Register middleware in `Kernel.php`
2. Apply to routes in `web.php`
3. Update `ProfileController::edit()` to pass `reason` and `return_to`
4. Update `ProfileController::update()` to handle redirects
5. Update `TopUpController::index()` to pass `reason`

## Debugging Failed Tests

```bash
# Run with verbose output
vendor/bin/pest --group=middleware -vvv

# Run single test
vendor/bin/pest --filter="allows users with mobile number to continue"

# Clear cache if routes not found
php artisan route:clear
php artisan config:clear
```

## Next Steps After Tests Pass

1. Update frontend components:
   - `resources/js/pages/settings/Profile.vue` (add banner + auto-focus)
   - `resources/js/pages/wallet/TopUp.vue` (add banner)
2. Manual testing with real users
3. Deploy to staging
4. Monitor metrics (completion rate, time-to-value)
