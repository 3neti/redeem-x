<?php

/**
 * Demo: What happens when ADMIN_OVERRIDE_EMAILS is commented out?
 * 
 * Run: php test-env-override.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'lester@hurtado.ph')->first();

if (!$user) {
    echo "❌ User not found. Run: php artisan db:seed --class=UserSeeder\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "Testing Authorization for: {$user->email}\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Check role-based access
echo "1️⃣  ROLE-BASED ACCESS (Database)\n";
echo "   Has super-admin role: " . ($user->hasRole('super-admin') ? '✅ YES' : '❌ NO') . "\n";
echo "   Has any admin role: " . ($user->hasAnyRole(['super-admin', 'admin', 'power-user']) ? '✅ YES' : '❌ NO') . "\n";
echo "   Permissions: " . implode(', ', $user->getAllPermissions()->pluck('name')->toArray()) . "\n\n";

// Check .env override
$overrideEmails = config('admin.override_emails', []);
$isOverride = in_array($user->email, $overrideEmails);

echo "2️⃣  .ENV OVERRIDE (Configuration)\n";
echo "   ADMIN_OVERRIDE_EMAILS: " . (empty($overrideEmails) ? '(empty/commented)' : implode(', ', $overrideEmails)) . "\n";
echo "   Is in override list: " . ($isOverride ? '✅ YES' : '❌ NO') . "\n\n";

// Final result
$hasRoleAccess = $user->hasAnyRole(['super-admin', 'admin', 'power-user']);
$hasAccess = $hasRoleAccess || $isOverride;

echo "3️⃣  FINAL AUTHORIZATION RESULT\n";
echo "   Access via role: " . ($hasRoleAccess ? '✅ YES' : '❌ NO') . "\n";
echo "   Access via override: " . ($isOverride ? '✅ YES' : '❌ NO') . "\n";
echo "   ───────────────────────────────────────\n";
echo "   TOTAL ACCESS: " . ($hasAccess ? '✅ GRANTED' : '❌ DENIED') . "\n\n";

// Show what pages user can access
echo "4️⃣  ACCESSIBLE PAGES\n";
if ($hasAccess) {
    echo "   ✅ /admin/pricing (manage pricing permission)\n";
    echo "   ✅ /balances (view balance permission)\n";
    echo "   ✅ /admin/billing (view all billing permission)\n";
    echo "   ✅ Advanced voucher generation mode\n";
} else {
    echo "   ❌ No admin pages accessible\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "CONCLUSION:\n";
echo "═══════════════════════════════════════════════════════════\n";

if ($hasRoleAccess && $isOverride) {
    echo "✅ User has BOTH role AND override - fully redundant\n";
    echo "💡 You can safely comment out ADMIN_OVERRIDE_EMAILS\n";
} elseif ($hasRoleAccess) {
    echo "✅ User has role-based access (override not needed)\n";
    echo "💡 ADMIN_OVERRIDE_EMAILS can be removed completely\n";
} elseif ($isOverride) {
    echo "⚠️  User has ONLY .env override (no role assigned)\n";
    echo "💡 Run: php artisan db:seed --class=UserSeeder\n";
} else {
    echo "❌ User has NO access (neither role nor override)\n";
    echo "💡 Assign role or add to ADMIN_OVERRIDE_EMAILS\n";
}

echo "\n";
