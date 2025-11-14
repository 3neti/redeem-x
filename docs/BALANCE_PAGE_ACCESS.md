# Balance Page Access Control

The Balance Monitoring page (`/balances`) is protected by role-based access control and can be configured via environment variables.

## URL

```
https://your-app.com/balances
```

## Default Configuration

By default:
- ✅ **Enabled**: Balance page is accessible
- 🔒 **Role Required**: `admin` role
- 📊 **Account**: Uses first available from config

## Environment Variables

### `.env` Configuration

Add these lines to your `.env` file to customize access control:

```bash
# Enable or disable balance viewing globally
BALANCE_VIEW_ENABLED=true

# Required role to view balance page (default: admin)
BALANCE_VIEW_ROLE=admin

# Default account number for balance monitoring
BALANCE_DEFAULT_ACCOUNT=113-001-00001-9

# Optional: Alert configuration
BALANCE_ALERT_THRESHOLD=1000000  # ₱10,000.00 (in centavos)
BALANCE_ALERT_RECIPIENTS=admin@yourcompany.com,finance@yourcompany.com

# Optional: Scheduling configuration
BALANCE_SCHEDULE_ENABLED=true
BALANCE_SCHEDULE_CRON="0 * * * *"  # Hourly
```

## Access Control Options

### 1. Change Required Role

**Default (admin only):**
```bash
BALANCE_VIEW_ROLE=admin
```

**Allow managers:**
```bash
BALANCE_VIEW_ROLE=manager
```

**Allow super-admin only:**
```bash
BALANCE_VIEW_ROLE=super-admin
```

### 2. Disable Balance Page Completely

```bash
BALANCE_VIEW_ENABLED=false
```

Users will see: `403 - Balance viewing is currently disabled.`

### 3. Change Default Account

```bash
BALANCE_DEFAULT_ACCOUNT=456-789-012-3
```

If not set, falls back to:
1. `PAYMENT_GATEWAY_DEFAULT_ACCOUNT`
2. `OMNIPAY_TEST_ACCOUNT`
3. `DISBURSEMENT_ACCOUNT_NUMBER`

## Assigning Roles to Users

Use Spatie Permissions to assign roles:

### Via Tinker

```bash
php artisan tinker
```

```php
// Give user admin role
$user = App\Models\User::find(1);
$user->assignRole('admin');

// Create role if it doesn't exist
Spatie\Permission\Models\Role::create(['name' => 'admin']);

// Give multiple users admin role
App\Models\User::whereIn('email', [
    'admin@yourcompany.com',
    'finance@yourcompany.com'
])->each(fn($user) => $user->assignRole('admin'));
```

### Via Database Seeder

```php
// database/seeders/RoleSeeder.php
public function run()
{
    $admin = Role::create(['name' => 'admin']);
    
    User::where('email', 'admin@yourcompany.com')
        ->first()
        ->assignRole('admin');
}
```

## Error Messages

### 403 - Role Required
```
You do not have permission to view balance information.
```

**Solution:** Assign the required role to the user:
```php
$user->assignRole('admin');
```

### 403 - Disabled
```
Balance viewing is currently disabled.
```

**Solution:** Enable in `.env`:
```bash
BALANCE_VIEW_ENABLED=true
```

### 500 - No Account Configured
```
No default account configured. Set BALANCE_DEFAULT_ACCOUNT in .env
```

**Solution:** Set account number in `.env`:
```bash
BALANCE_DEFAULT_ACCOUNT=113-001-00001-9
```

## Multiple Roles Setup

To allow multiple roles (e.g., admin OR finance):

**Option 1: Use permission instead of role**

```php
// In BalancePageController.php, change:
if (!auth()->user()->hasRole($requiredRole)) {
    abort(403);
}

// To:
if (!auth()->user()->can('view balance')) {
    abort(403);
}
```

**Option 2: Check multiple roles**

```php
$allowedRoles = explode(',', config('balance.view_role', 'admin'));
if (!auth()->user()->hasAnyRole($allowedRoles)) {
    abort(403);
}
```

Then in `.env`:
```bash
BALANCE_VIEW_ROLE=admin,manager,finance
```

## Testing Access

### Test as Admin User

```bash
# Login as admin
php artisan tinker
```

```php
$user = User::where('email', 'admin@yourcompany.com')->first();
Auth::login($user);
```

Then visit: `http://localhost:8000/balances`

### Test with Different Role

```bash
BALANCE_VIEW_ROLE=manager
```

```php
$user->removeRole('admin');
$user->assignRole('manager');
```

## Security Best Practices

1. ✅ **Keep default role strict**: Use `admin` or `super-admin`
2. ✅ **Limit alert recipients**: Only trusted email addresses
3. ✅ **Monitor access logs**: Track who views balance data
4. ✅ **Use HTTPS**: Always in production
5. ✅ **Rotate API keys**: Change payment gateway credentials regularly

## API Access

The balance API endpoints (`/api/v1/balances/*`) use Sanctum authentication and do NOT check roles. To add role checking to API:

```php
// In routes/api.php
Route::prefix('balances')
    ->middleware(['role:admin'])  // Add this
    ->group(function () {
        // ... balance routes
    });
```

## FAQ

**Q: Can I make the balance page public?**  
A: Not recommended. You can create a custom controller without role checks, but balance data is sensitive.

**Q: How do I add sidebar link to balance page?**  
A: Add to `resources/js/components/NavMain.vue` in the navigation items array.

**Q: Can regular users view their own balance?**  
A: Currently, the page shows the default account. To show user-specific balances, you'd need to modify the controller to get the user's account number.

**Q: How do I log who accesses the balance page?**  
A: Add logging in the controller:
```php
Log::info('Balance page accessed', [
    'user_id' => auth()->id(),
    'user_email' => auth()->user()->email,
    'ip' => request()->ip(),
]);
```

## Example Configurations

### Production (High Security)
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=super-admin
BALANCE_DEFAULT_ACCOUNT=113-001-00001-9
BALANCE_ALERT_THRESHOLD=5000000  # ₱50,000
BALANCE_ALERT_RECIPIENTS=ceo@company.com
```

### Development (Relaxed)
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=admin
BALANCE_DEFAULT_ACCOUNT=113-001-00001-9
BALANCE_ALERT_THRESHOLD=100000  # ₱1,000
BALANCE_ALERT_RECIPIENTS=dev@company.com
```

### Staging (Disabled)
```bash
BALANCE_VIEW_ENABLED=false
```

---

**Need help?** Check the main balance monitoring documentation in `docs/BALANCE_MONITORING_PHASE3_COMPLETE.md`.
