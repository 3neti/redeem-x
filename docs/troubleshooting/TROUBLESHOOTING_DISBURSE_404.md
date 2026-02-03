# Troubleshooting: 404 on /disburse in Production

## Problem
`/disburse` route returns 404 in production but works locally.

## Diagnosis Commands

Run these on your production server:

### 1. Check if route is registered
```bash
php artisan route:list --path=disburse
```

**Expected output:**
```
GET|HEAD   disburse ........................ disburse.start › DisburseController@start
POST       disburse/{voucher}/complete ..... disburse.complete › DisburseController@complete
POST       disburse/{voucher}/redeem ....... disburse.redeem › DisburseController@redeem
GET|HEAD   disburse/cancel ................. disburse.cancel › DisburseController@cancel
GET|HEAD   disburse/{voucher}/success ...... disburse.success › DisburseController@success
```

If this is **empty**, routes aren't being loaded.

### 2. Clear all caches
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

Then check again:
```bash
php artisan route:list --path=disburse
```

### 3. Check if routes/disburse.php exists
```bash
ls -la routes/disburse.php
```

Should show the file exists with read permissions.

### 4. Check DisburseController exists
```bash
ls -la app/Http/Controllers/Disburse/DisburseController.php
```

### 5. Test route resolution
```bash
php artisan tinker --execute="echo route('disburse.start');"
```

Should output: `http://yourdomain.com/disburse`

### 6. Check web server logs
```bash
# For Nginx
tail -f /var/log/nginx/error.log

# For Apache
tail -f /var/log/apache2/error.log

# Laravel Herd (local)
# Check Herd logs in the GUI
```

---

## Common Fixes

### Fix 1: Clear Route Cache (Most Common)
```bash
php artisan route:clear
php artisan config:clear
php artisan optimize:clear
```

### Fix 2: Rebuild Caches
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Warning:** Only use `route:cache` if **all** routes use controller actions (not closures).

### Fix 3: Check Nginx Configuration

If using Nginx, ensure you have proper rewrite rules:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Fix 4: Check Apache .htaccess

If using Apache, ensure `.htaccess` in `public/` directory exists:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

### Fix 5: Verify Deployment

Ensure all files were deployed:
```bash
git status
git log --oneline -5
```

Check for uncommitted changes or missed deployment.

### Fix 6: Check File Permissions
```bash
# Laravel needs write access to storage and bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Environment Variables

The splash/success env variables you mentioned are **NOT** required for routing:
- `SPLASH_*` - Only affects splash page rendering
- `SUCCESS_*` - Only affects success page rendering

They have **defaults** in config files, so the app will work without them.

However, if you want custom branding in production, add them to `.env`:

```bash
# Splash Page
SPLASH_ENABLED=true
SPLASH_DEFAULT_TIMEOUT=5
SPLASH_APP_AUTHOR="Your Name"
SPLASH_COPYRIGHT_HOLDER="Your Company"
SPLASH_COPYRIGHT_YEAR=2026
SPLASH_BUTTON_LABEL="Continue"

# Success Page
SUCCESS_BUTTON_LABEL="Continue"
SUCCESS_DASHBOARD_BUTTON_LABEL="Go to Dashboard"
SUCCESS_REDEEM_ANOTHER_LABEL="Redeem Another"
```

---

## Quick Fix Script

Create a file `fix-routes.sh` and run it:

```bash
#!/bin/bash
echo "=== Clearing all caches ==="
php artisan optimize:clear

echo ""
echo "=== Checking route registration ==="
php artisan route:list --path=disburse

echo ""
echo "=== Testing route resolution ==="
php artisan tinker --execute="echo route('disburse.start') . PHP_EOL;"

echo ""
echo "=== Done! If routes appear above, try accessing /disburse in browser ==="
```

Run it:
```bash
chmod +x fix-routes.sh
./fix-routes.sh
```

---

## Still Not Working?

### Check Laravel Log
```bash
tail -50 storage/logs/laravel.log
```

Look for errors related to routing or missing controllers.

### Enable Debug Mode Temporarily
```bash
# In .env
APP_DEBUG=true
```

Visit `/disburse` again and check the detailed error page.

**IMPORTANT:** Set `APP_DEBUG=false` after diagnosing!

### Check PHP Version
```bash
php -v
```

Ensure it's PHP 8.2+ (required by Laravel 12).

### Verify Web Server Configuration

**For Laravel Herd:**
- Check Herd settings for the site
- Verify the site is linked correctly
- Check Herd logs in the GUI

**For Forge/Vapor/DigitalOcean:**
- Verify the deployment completed successfully
- Check deployment logs
- Ensure the correct branch was deployed

---

## Contact Support

If none of these work, provide:
1. Output of `php artisan route:list --path=disburse`
2. Laravel log excerpt: `tail -50 storage/logs/laravel.log`
3. Web server error log excerpt
4. Output of `php artisan about`
