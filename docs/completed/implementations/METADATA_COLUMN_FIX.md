# Metadata Column Size Fix

## Problem

When redeeming vouchers with large inputs (especially base64-encoded images like signatures), the redemption would fail with:

```
SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'metadata' at row 1
```

This error occurs because the `metadata` column in both `vouchers` and `redeemers` tables was defined as `TEXT`, which in MySQL has a maximum size of 65,535 bytes (64KB).

## Root Cause

The `ProcessRedemption` action stores all user inputs in the metadata field:

```php
protected function prepareMetadata(array $inputs, array $bankAccount): array
{
    $meta = [];
    
    if (! empty($inputs)) {
        $meta['inputs'] = $inputs;
    }
    
    // ...
    return $meta;
}
```

When vouchers require inputs like:
- **Signature images** (base64-encoded, often 50-100KB)
- **Selfie images** (base64-encoded, often 100-200KB)
- **Location data** with address and map snapshots
- **Multiple text fields**

The combined JSON payload easily exceeds 64KB.

## Solution

Changed the `metadata` column type from `TEXT` to `LONGTEXT` in MySQL:

- `TEXT`: 65,535 bytes (64KB)
- `LONGTEXT`: 4,294,967,295 bytes (4GB)

### Migration

Created migration `2026_01_04_102010_change_metadata_to_longtext_in_voucher_tables.php`:

```php
public function up(): void
{
    // Change metadata column in vouchers table
    Schema::table(Config::table('vouchers'), function (Blueprint $table) {
        $table->longText('metadata')->nullable()->change();
    });

    // Change metadata column in redeemers table
    Schema::table(Config::table('redeemers'), function (Blueprint $table) {
        $table->longText('metadata')->nullable()->change();
    });
}
```

## Database Compatibility

- **MySQL/MariaDB**: Changes TEXT (64KB) → LONGTEXT (4GB)
- **PostgreSQL**: TEXT type has no size limit (no change needed)
- **SQLite**: TEXT type has no size limit (no change needed)

## Testing

After applying the migration:

```bash
php artisan migrate
```

Test redemption with vouchers that require:
1. Signature input (large base64 image)
2. Location input (with address and map data)
3. Multiple text inputs
4. Combination of all above

## Performance Considerations

- LONGTEXT stores data outside the table row, so very large metadata won't slow down table scans
- Indexes cannot be created on LONGTEXT columns (but metadata is not indexed anyway)
- For typical redemptions (< 500KB metadata), performance impact is negligible

## Future Optimization (Optional)

If metadata size becomes a concern, consider:

1. **Store images separately**: Save signature/selfie images via Spatie Media Library instead of embedding in metadata
2. **Compress JSON**: Use gzip compression for metadata before storing
3. **Separate table**: Create `voucher_inputs` table with one row per input field

For now, LONGTEXT is sufficient and maintains backward compatibility.
