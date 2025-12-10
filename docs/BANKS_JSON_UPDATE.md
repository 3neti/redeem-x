# Updating banks.json

**Source of Truth:** `packages/money-issuer/resources/documents/banks.json`

## When NetBank provides new data

1. **Copy to canonical location:**
   ```bash
   cp new-banks.json packages/money-issuer/resources/documents/banks.json
   ```

2. **Validate JSON structure:**
   ```bash
   jq '.banks | length' packages/money-issuer/resources/documents/banks.json
   # Should output: number of banks (e.g., 146)
   ```

3. **Republish to app:**
   ```bash
   php artisan vendor:publish --tag=banks-registry --force
   ```

4. **Verify deployment:**
   ```bash
   md5 packages/money-issuer/resources/documents/banks.json resources/documents/banks.json
   # MD5 hashes should match
   ```

5. **Test the update:**
   ```bash
   php artisan test tests/Integration/BankRegistryBaselineTest.php
   ```

6. **Commit both files:**
   ```bash
   git add packages/money-issuer/resources/documents/banks.json
   git add resources/documents/banks.json
   git commit -m "Update banks.json from NetBank"
   ```

## Architecture

```
money-issuer (canonical source)
  └── resources/documents/banks.json
       ↓ vendor:publish
app (published copy)
  └── resources/documents/banks.json
       ↓ read by BankRegistry
payment-gateway & voucher packages
  └── use BankRegistry from money-issuer
```

## Emergency Rollback

If the update causes issues:

```bash
# Revert to previous version
git checkout HEAD~1 -- packages/money-issuer/resources/documents/banks.json
php artisan vendor:publish --tag=banks-registry --force
php artisan config:clear
```
