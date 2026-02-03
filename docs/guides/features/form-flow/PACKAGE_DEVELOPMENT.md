# Package Development Workflow

Guide for modifying the `3neti/form-flow` package source code and publishing updates.

## Overview

When you need to fix bugs or add features to the form-flow package, follow this workflow to:
1. Develop locally with immediate feedback
2. Test changes before publishing
3. Publish package updates safely
4. Update host application to use published version

**Time estimate**: 60-90 minutes for a typical feature addition

## Prerequisites

- Package source code: `/Users/rli/PhpstormProjects/packages/form-flow-manager`
- Host application: `/Users/rli/PhpstormProjects/redeem-x`
- Git access to both repositories
- Packagist account (for publishing)

## Workflow Phases

### Phase 1: Switch to Local Development

**Goal**: Use local package instead of published version for development.

#### 1.1 Add Path Repository

Edit `composer.json` in host application:

```json
{
    "repositories": [
        // ... existing repositories
        {
            "type": "path",
            "url": "../packages/form-flow-manager",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

**Important**: Use relative path `../packages/form-flow-manager` (not `./packages/...`)

#### 1.2 Update Version Constraint

Change from published version to `@dev`:

```json
{
    "require": {
        "3neti/form-flow": "@dev"
    }
}
```

#### 1.3 Create Symlink

Remove existing package and create symlink:

```bash
cd /path/to/redeem-x

# Remove installed package
rm -rf vendor/3neti/form-flow

# Create symlink manually (composer may not do this automatically)
ln -s /Users/rli/PhpstormProjects/packages/form-flow-manager vendor/3neti/form-flow

# Regenerate autoloader
composer dumpautoload
```

#### 1.4 Verify Symlink

```bash
ls -l vendor/3neti/ | grep form-flow
# Should show: lrwxr-xr-x ... form-flow -> /Users/rli/PhpstormProjects/packages/form-flow-manager
```

---

### Phase 2: Modify Package Source

**Goal**: Make changes to package code.

#### 2.1 Navigate to Package

```bash
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
```

#### 2.2 Make Code Changes

Example: Adding new context flags to `DriverService`

```php
// src/Services/DriverService.php

protected function buildContext(Voucher $voucher): array
{
    // ... existing code
    
    return [
        // ... existing flags
        'has_reference_code' => in_array('reference_code', $fieldNames),
        'has_gross_monthly_income' => in_array('gross_monthly_income', $fieldNames),
    ];
}
```

#### 2.3 Clear Caches in Host App

After modifying package code:

```bash
cd /path/to/redeem-x
php artisan config:clear
php artisan cache:clear
```

---

### Phase 3: Modify Host Application

**Goal**: Update host application to use new package features.

#### 3.1 Update YAML Driver

Example: Add new fields to `config/form-flow-drivers/voucher-redemption.yaml`

```yaml
bio:
  handler: "form"
  step_name: "bio_fields"
  condition: "{{ has_name or has_email or has_reference_code or has_gross_monthly_income }}"
  fields:
    - name: "reference_code"
      type: "text"
      label: "Reference Code"
      required: true
      condition: "{{ has_reference_code }}"
    - name: "gross_monthly_income"
      type: "number"
      label: "Gross Monthly Income"
      required: true
      condition: "{{ has_gross_monthly_income }}"
```

#### 3.2 Update Controllers (if needed)

If the package changes affect controllers or services, update accordingly.

---

### Phase 4: Test Locally

**Goal**: Verify changes work before publishing.

#### 4.1 Unit Tests (in package)

```bash
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
./vendor/bin/pest
```

#### 4.2 Integration Tests (in host app)

```bash
cd /path/to/redeem-x
php artisan test --filter FormFlow
```

#### 4.3 Manual Testing with Tinker

```bash
php artisan tinker

# Test driver transformation
$voucher = \LBHurtado\Voucher\Models\Voucher::first();
$driver = app(\LBHurtado\FormFlowManager\Services\DriverService::class);
$result = $driver->transform($voucher);

# Verify steps include new fields
foreach ($result->steps as $step) {
    $stepArray = $step->toArray();
    echo $stepArray['config']['title'] ?? $stepArray['handler'];
    echo PHP_EOL;
}
```

#### 4.4 Browser Testing

1. Generate test voucher with new input fields
2. Navigate to redemption flow: `/disburse?code=VOUCHER-CODE`
3. Verify new fields appear in form
4. Complete redemption successfully

---

### Phase 5: Commit and Tag Package

**Goal**: Version and publish package changes.

#### 5.1 Check Current Version

```bash
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
git tag | tail -5
# Example output: v1.7.0
```

#### 5.2 Commit Changes

```bash
git add -A
git commit -m "Add support for reference_code and gross_monthly_income input fields

- Add has_reference_code context flag to DriverService::buildContext()
- Add has_gross_monthly_income context flag to DriverService::buildContext()
- Enables YAML drivers to conditionally render these input fields

This allows vouchers with reference_code or gross_monthly_income inputs
to properly display these fields in the form flow.

Co-Authored-By: Warp <agent@warp.dev>"
```

#### 5.3 Tag New Version

Use semantic versioning:
- **Patch** (v1.7.0 → v1.7.1): Bug fixes, minor additions
- **Minor** (v1.7.0 → v1.8.0): New features, backward compatible
- **Major** (v1.7.0 → v2.0.0): Breaking changes

```bash
# For patch release
git tag v1.7.1

# For minor release
git tag v1.8.0
```

#### 5.4 Push to GitHub

```bash
git push origin main --tags
```

**Verify on GitHub**:
- Visit: https://github.com/3neti/form-flow/tags
- Confirm new tag appears

---

### Phase 6: Publish to Packagist

**Goal**: Make package available via Composer.

#### 6.1 Automatic Update

If package is linked to Packagist (GitHub hook enabled):
- Packagist updates automatically within 5-15 minutes
- Check: https://packagist.org/packages/3neti/form-flow

#### 6.2 Manual Trigger

If auto-update fails:
1. Visit: https://packagist.org/packages/3neti/form-flow
2. Click "Update" button
3. Wait for indexing to complete

#### 6.3 Verify Publication

```bash
# Check latest version on Packagist
curl -s https://repo.packagist.org/p2/3neti/form-flow.json | jq '.packages."3neti/form-flow"' | head -20
```

---

### Phase 7: Switch to Published Package

**Goal**: Use published package instead of local symlink.

#### 7.1 Remove Path Repository

Edit `composer.json`:

```json
{
    "repositories": [
        // Remove this block:
        // {
        //     "type": "path",
        //     "url": "../packages/form-flow-manager",
        //     "options": {
        //         "symlink": true
        //     }
        // }
    ]
}
```

#### 7.2 Update Version Constraint

```json
{
    "require": {
        "3neti/form-flow": "^1.7.1"  // or ~1.7.1 for more strict
    }
}
```

#### 7.3 Update Package

```bash
cd /path/to/redeem-x
composer update 3neti/form-flow
```

**Expected output**:
```
Upgrading 3neti/form-flow (v1.7.0 => v1.7.1)
```

#### 7.4 Verify Installation

```bash
# Check it's no longer a symlink
ls -l vendor/3neti/ | grep form-flow
# Should show: drwxr-xr-x (directory, not symlink)

# Check version
composer show 3neti/form-flow | grep versions
# Should show: versions : * v1.7.1
```

---

### Phase 8: Commit Host Application

**Goal**: Record changes in host application repository.

#### 8.1 Commit Configuration Changes

```bash
cd /path/to/redeem-x

git add composer.json composer.lock config/form-flow-drivers/

git commit -m "Add support for reference_code and GMI fields in form flow

- Add reference_code and gross_monthly_income field definitions to bio step
- Expand bio step condition to render when these fields are enabled
- Requires 3neti/form-flow v1.7.1+ (adds has_reference_code and has_gross_monthly_income flags)

Fixes issue where vouchers with reference_code or gross_monthly_income
inputs enabled would not display these fields in the redemption flow,
causing form validation to fail silently.

Related package: 3neti/form-flow@v1.7.1

Co-Authored-By: Warp <agent@warp.dev>"
```

#### 8.2 Commit Composer Changes

```bash
git add composer.json composer.lock

git commit -m "Switch to published 3neti/form-flow v1.7.1 package

- Remove local path repository for form-flow-manager
- Update version constraint from @dev to ^1.7.1
- Package now installed from Packagist (v1.7.1)

Verified working with reference_code and gross_monthly_income fields.

Co-Authored-By: Warp <agent@warp.dev>"
```

#### 8.3 Push to Origin

```bash
git push origin main
```

---

## Troubleshooting

### Issue: Composer doesn't create symlink

**Symptom**: After `composer update`, package is extracted instead of symlinked.

**Solution**: Create symlink manually:
```bash
rm -rf vendor/3neti/form-flow
ln -s /Users/rli/PhpstormProjects/packages/form-flow-manager vendor/3neti/form-flow
composer dumpautoload
```

### Issue: Changes not reflected in host app

**Symptom**: Modified package code doesn't affect host application.

**Solutions**:
1. Clear caches:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   composer dumpautoload
   ```

2. Verify symlink:
   ```bash
   ls -l vendor/3neti/form-flow
   # Should point to local package directory
   ```

3. Check autoloader:
   ```bash
   composer dump-autoload
   ```

### Issue: Packagist not updating

**Symptom**: New tag pushed, but Packagist still shows old version.

**Solutions**:
1. Wait 15-30 minutes (indexing delay)
2. Manually trigger update on Packagist website
3. Check GitHub webhook settings:
   - Repository Settings → Webhooks
   - Should have Packagist webhook enabled

### Issue: Network timeout during composer update

**Symptom**: `curl error 28 while downloading`

**Solution**: Use cached packages:
```bash
composer update --prefer-dist --no-plugins 3neti/form-flow
```

### Issue: Version conflict after publishing

**Symptom**: `composer update` says "nothing to update"

**Solutions**:
1. Clear composer cache:
   ```bash
   composer clear-cache
   composer update 3neti/form-flow
   ```

2. Force reinstall:
   ```bash
   composer reinstall 3neti/form-flow
   ```

---

## Best Practices

### Commit Messages

Use conventional commit format:

**Package commits**:
```
Add support for [feature]

- Bullet point 1
- Bullet point 2

[Optional: Longer description]

Co-Authored-By: Warp <agent@warp.dev>
```

**Host app commits**:
```
[Action] [what changed]

- Configuration changes
- Dependencies updated
- Requires package@version

Fixes issue where [problem description]

Related package: [package]@[version]

Co-Authored-By: Warp <agent@warp.dev>
```

### Semantic Versioning

- **Patch** (x.x.1): Bug fixes, typos, documentation
- **Minor** (x.1.x): New features, backward compatible additions
- **Major** (1.x.x): Breaking changes, API redesigns

### Testing Checklist

Before publishing:

- [ ] Package unit tests pass
- [ ] Host app integration tests pass
- [ ] Manual browser testing complete
- [ ] No breaking changes (or documented if major version)
- [ ] CHANGELOG.md updated (if package has one)
- [ ] Documentation updated (if API changed)

### Version Constraints

Choose appropriate constraint in host app:

- `^1.7.1` - Allow minor and patch updates (recommended)
- `~1.7.1` - Allow patch updates only (conservative)
- `1.7.1` - Exact version (not recommended, prevents security patches)

---

## Example: Complete Workflow

Here's a real example from adding `reference_code` and `gross_monthly_income` fields:

### 1. Setup (5 minutes)

```bash
# Add path repository to composer.json
# Change version to @dev
# Create symlink
ln -s /Users/rli/PhpstormProjects/packages/form-flow-manager vendor/3neti/form-flow
composer dumpautoload
```

### 2. Package Modification (10 minutes)

```bash
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
# Edit src/Services/DriverService.php
# Add two lines to buildContext()
```

### 3. Host App Modification (15 minutes)

```bash
cd /path/to/redeem-x
# Edit config/form-flow-drivers/voucher-redemption.yaml
# Add 12 lines for two new fields
# Update step condition (1 line)
php artisan config:clear
```

### 4. Testing (20 minutes)

```bash
# Unit tests
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
./vendor/bin/pest

# Integration tests
cd /path/to/redeem-x
php artisan tinker
# Test transformation

# Browser testing
# Create test voucher, verify fields appear
```

### 5. Publish Package (10 minutes)

```bash
cd /Users/rli/PhpstormProjects/packages/form-flow-manager
git add -A
git commit -m "Add support for reference_code and gross_monthly_income"
git tag v1.7.1
git push origin main --tags
# Wait for Packagist update (5-15 min)
```

### 6. Switch to Published (5 minutes)

```bash
cd /path/to/redeem-x
# Remove path repository from composer.json
# Change @dev to ^1.7.1
composer update 3neti/form-flow
ls -l vendor/3neti/form-flow  # Verify not symlink
```

### 7. Commit Host App (5 minutes)

```bash
git add composer.json composer.lock config/
git commit -m "Add support for reference_code and GMI fields"
git add composer.json composer.lock
git commit -m "Switch to published 3neti/form-flow v1.7.1"
git push origin main
```

**Total Time**: ~70 minutes

---

## Quick Reference

### Commands Cheat Sheet

```bash
# Setup local development
ln -s ../packages/form-flow-manager vendor/3neti/form-flow
composer dumpautoload

# Clear caches
php artisan config:clear && php artisan cache:clear

# Test package
cd ../packages/form-flow-manager && ./vendor/bin/pest

# Publish package
git tag v1.7.1 && git push origin main --tags

# Switch to published
composer update 3neti/form-flow

# Verify version
composer show 3neti/form-flow | grep versions
```

### File Locations

- **Package source**: `/Users/rli/PhpstormProjects/packages/form-flow-manager`
- **Package vendor**: `/path/to/redeem-x/vendor/3neti/form-flow`
- **Host composer**: `/path/to/redeem-x/composer.json`
- **YAML driver**: `/path/to/redeem-x/config/form-flow-drivers/voucher-redemption.yaml`

---

## Related Documentation

- [INTEGRATION.md](./INTEGRATION.md) - Form-flow integration guide
- [HANDLERS.md](./HANDLERS.md) - Creating custom handlers
- [API.md](./API.md) - Package API reference
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues

---

**Last Updated**: February 2026  
**Package**: 3neti/form-flow  
**Example Version**: v1.7.0 → v1.7.1
