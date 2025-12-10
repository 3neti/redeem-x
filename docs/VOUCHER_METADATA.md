# Voucher Metadata

## Overview

Voucher metadata provides transparency and traceability by embedding system information directly into each voucher. This allows users to "x-ray" vouchers before redemption to verify their origin, issuer, licenses, and redemption options.

## Features

- **System Information**: Version, name, copyright
- **Regulatory Compliance**: BSP, SEC, NTC licenses (configurable)
- **Issuer Identity**: Who created the voucher
- **Redemption URLs**: Web, API, widget endpoints
- **Timestamps**: Creation and issuance dates
- **Digital Signatures**: Optional public key for offline verification
- **Backward Compatible**: Old vouchers without metadata continue to work

## Architecture

### Data Structure

Metadata is stored in `vouchers.metadata->instructions->metadata` as JSON:

```json
{
  "version": "1.0.0",
  "system_name": "Redeem-X",
  "copyright": "3neti R&D OPC",
  "licenses": {
    "BSP": "Bangko Sentral ng Pilipinas",
    "SEC": "Securities and Exchange Commission",
    "NTC": "National Telecommunications Commission"
  },
  "issuer_id": "1",
  "issuer_name": "Lester B. Hurtado",
  "issuer_email": "admin@disburse.cash",
  "redemption_urls": {
    "web": "http://redeem-x.test/redeem"
  },
  "primary_url": "http://redeem-x.test/redeem",
  "created_at": "2025-12-10 23:32:43",
  "issued_at": "2025-12-10 23:32:43"
}
```

### Files Created

**Package Layer** (`packages/voucher/`):
- `src/Data/VoucherMetadataData.php` - DTO with all metadata fields and helper methods

**Application Layer**:
- `config/voucher.php` - Configuration for metadata, redemption URLs, security
- `app/Actions/Api/Vouchers/InspectVoucher.php` - Public API endpoint for metadata inspection
- `routes/api/vouchers.php` - Added `/api/v1/vouchers/{code}/inspect` route

### Files Modified

- `packages/voucher/src/Data/VoucherInstructionsData.php` - Added `metadata` property
- `packages/voucher/src/Actions/GenerateVouchers.php` - Populates metadata during generation
- `.env` and `.env.example` - Added voucher metadata configuration variables

## Configuration

### Environment Variables

Add to `.env`:

```bash
# Voucher Metadata Configuration
VOUCHER_VERSION=1.0.0
VOUCHER_COPYRIGHT="3neti R&D OPC"

# Regulatory Licenses (leave blank if not applicable)
LICENSE_BSP="Bangko Sentral ng Pilipinas"
LICENSE_SEC="Securities and Exchange Commission"
LICENSE_NTC="National Telecommunications Commission"

# Optional: Widget/iframe redemption URL
VOUCHER_WIDGET_URL=

# Optional: Digital signature support (for offline voucher verification)
VOUCHER_ENABLE_SIGNATURES=false
VOUCHER_PUBLIC_KEY=
VOUCHER_PRIVATE_KEY=
```

### Config File

`config/voucher.php` structure:

```php
return [
    'metadata' => [
        'version' => env('VOUCHER_VERSION', '1.0.0'),
        'system_name' => env('APP_NAME', 'Redeem-X'),
        'copyright' => env('VOUCHER_COPYRIGHT', '3neti R&D OPC'),
        'licenses' => [
            'BSP' => env('LICENSE_BSP'),
            'SEC' => env('LICENSE_SEC'),
            'NTC' => env('LICENSE_NTC'),
        ],
    ],
    'redemption' => [
        'widget_url' => env('VOUCHER_WIDGET_URL'),
    ],
    'security' => [
        'enable_signatures' => env('VOUCHER_ENABLE_SIGNATURES', false),
        'public_key' => env('VOUCHER_PUBLIC_KEY'),
        'private_key' => env('VOUCHER_PRIVATE_KEY'),
    ],
];
```

## Usage

### Generating Vouchers

Metadata is automatically populated when generating vouchers:

```php
use LBHurtado\Voucher\Actions\GenerateVouchers;

$instructions = [
    'cash' => [
        'amount' => 100,
        'currency' => 'PHP',
        'validation' => ['country' => 'PH'],
    ],
    'inputs' => ['fields' => []],
    'feedback' => [],
    'rider' => [],
    'count' => 1,
    'prefix' => 'TEST',
    'mask' => '****',
];

$vouchers = GenerateVouchers::run($instructions);
$voucher = $vouchers->first();

// Access metadata
$metadata = $voucher->metadata['instructions']['metadata'];
echo $metadata['version']; // 1.0.0
echo $metadata['issuer_name']; // Lester B. Hurtado
```

### Inspecting Vouchers via API

**Public endpoint** (no authentication required):

```bash
curl http://redeem-x.test/api/v1/vouchers/INSP-6DSP/inspect
```

**Response for voucher with metadata**:

```json
{
  "success": true,
  "code": "INSP-6DSP",
  "status": "active",
  "metadata": {
    "version": "1.0.0",
    "system_name": "Redeem-X",
    "copyright": "3neti R&D OPC",
    "licenses": {
      "BSP": "Bangko Sentral ng Pilipinas",
      "SEC": "Securities and Exchange Commission",
      "NTC": "National Telecommunications Commission"
    },
    "issuer_id": "1",
    "issuer_name": "Lester B. Hurtado",
    "issuer_email": "admin@disburse.cash",
    "redemption_urls": {
      "web": "http://redeem-x.test/redeem"
    },
    "primary_url": "http://redeem-x.test/redeem",
    "created_at": "2025-12-10 23:32:43",
    "issued_at": "2025-12-10 23:32:43"
  },
  "info": {
    "version": "1.0.0",
    "system_name": "Redeem-X",
    "copyright": "3neti R&D OPC",
    "licenses": {
      "BSP": "Bangko Sentral ng Pilipinas",
      "SEC": "Securities and Exchange Commission",
      "NTC": "National Telecommunications Commission"
    },
    "issuer": {
      "name": "Lester B. Hurtado",
      "email": "admin@disburse.cash"
    },
    "redemption_urls": {
      "web": "http://redeem-x.test/redeem"
    },
    "primary_url": "http://redeem-x.test/redeem",
    "issued_at": "2025-12-10 23:32:43"
  }
}
```

**Response for voucher without metadata** (old vouchers):

```json
{
  "success": true,
  "code": "RP5N",
  "status": "active",
  "metadata": null,
  "info": {
    "message": "This voucher was created before metadata tracking was implemented."
  }
}
```

**Response for non-existent voucher**:

```json
{
  "success": false,
  "message": "Voucher not found"
}
```

### VoucherMetadataData Helper Methods

```php
use LBHurtado\Voucher\Data\VoucherMetadataData;

$metadata = VoucherMetadataData::from($voucher->metadata['instructions']['metadata']);

// Get redemption URL (with fallback)
$url = $metadata->getRedemptionUrl('web'); // Returns web URL
$url = $metadata->getRedemptionUrl(); // Returns primary URL

// Check if tag exists
$hasTag = $metadata->hasTag('priority'); // Boolean

// Get single license
$license = $metadata->getLicense('BSP'); // "Bangko Sentral ng Pilipinas"

// Get all active licenses (filters out nulls)
$licenses = $metadata->getActiveLicenses(); // ['BSP' => '...', 'SEC' => '...']

// Verify signature (if signatures enabled)
$isValid = $metadata->verify($signature); // Boolean
```

## Backward Compatibility

The implementation is **fully backward compatible**:

- All metadata fields are nullable
- Old vouchers without metadata continue to function normally
- No database migrations required
- Metadata is stored in existing JSON `metadata` column
- API endpoint gracefully handles vouchers with/without metadata

**Testing backward compatibility**:

```php
// Old voucher (no metadata)
$oldVoucher = Voucher::where('prefix', '!=', 'META')->first();
$hasMetadata = isset($oldVoucher->metadata['instructions']['metadata']); // false
$cashAmount = $oldVoucher->metadata['instructions']['cash']['amount']; // Still works!

// New voucher (with metadata)
$newVoucher = GenerateVouchers::run($instructions)->first();
$hasMetadata = isset($newVoucher->metadata['instructions']['metadata']); // true
```

## Security Considerations

### Digital Signatures (Optional)

When `VOUCHER_ENABLE_SIGNATURES=true`:

1. Private key signs voucher data at creation
2. Public key stored in metadata for verification
3. Enables offline voucher verification without database access
4. Signature generated once at creation (immutable)

**Use cases**:
- Distributed systems
- Offline mobile apps
- Third-party verification
- Audit trails

### License Information

- License values are configurable per environment
- Leave blank if not applicable
- Displayed to end users for transparency
- Can be updated via environment variables

## Testing

### Manual Testing

1. **Generate voucher with metadata**:
```bash
php artisan tinker --execute="
use LBHurtado\Voucher\Actions\GenerateVouchers;
\$user = User::first();
auth()->login(\$user);
\$voucher = GenerateVouchers::run([
    'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['country' => 'PH']],
    'inputs' => ['fields' => []],
    'feedback' => [],
    'rider' => [],
    'count' => 1,
    'prefix' => 'TEST',
    'mask' => '****'
])->first();
echo \$voucher->code;
"
```

2. **Inspect via API**:
```bash
curl http://redeem-x.test/api/v1/vouchers/TEST-XXXX/inspect | jq
```

3. **Check old voucher compatibility**:
```bash
curl http://redeem-x.test/api/v1/vouchers/OLD-CODE/inspect | jq
```

### Automated Tests

Run existing tests:

```bash
# Voucher generation tests (includes metadata test)
php artisan test --filter VoucherGenerationFlowTest

# Specific metadata test
php artisan test --filter "stores instructions in metadata"
```

## Future Enhancements

### Potential Features

1. **Campaign metadata**: Link vouchers to campaigns with metadata
2. **Frontend UI**: Display metadata in voucher show page
3. **License badges**: Visual license indicators in UI
4. **QR code metadata**: Embed metadata in QR codes
5. **Metadata search**: Filter vouchers by issuer, license, version
6. **Audit logs**: Track metadata changes over time
7. **Multi-language**: Translate license names

### Digital Signature Implementation

When implementing signature verification:

```php
// In VoucherMetadataData
public function verify(string $signature): bool
{
    if (empty($this->public_key)) {
        return false;
    }

    $data = json_encode([
        'version' => $this->version,
        'issuer_id' => $this->issuer_id,
        'created_at' => $this->created_at,
        // Add other immutable fields
    ]);

    return openssl_verify($data, base64_decode($signature), $this->public_key, OPENSSL_ALGO_SHA256) === 1;
}

// In GenerateVouchers
private function signMetadata(VoucherMetadataData $metadata): string
{
    $privateKey = config('voucher.security.private_key');
    if (empty($privateKey)) {
        return '';
    }

    $data = json_encode([
        'version' => $metadata->version,
        'issuer_id' => $metadata->issuer_id,
        'created_at' => $metadata->created_at,
    ]);

    $signature = '';
    openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    return base64_encode($signature);
}
```

## Troubleshooting

### Metadata not appearing in new vouchers

1. Check `.env` has metadata variables
2. Clear config cache: `php artisan config:clear`
3. Verify route exists: `php artisan route:list | grep inspect`
4. Check auth()->user() is not null during generation

### Old vouchers return 404 on inspect

Check voucher exists in database:
```bash
php artisan tinker --execute="echo Voucher::where('code', 'YOUR-CODE')->exists() ? 'Exists' : 'Not found';"
```

### License values not filtering out

Ensure `array_filter()` is applied in `GenerateVouchers::createMetadata()`:
```php
$licenses = array_filter(config('voucher.metadata.licenses', []));
```

## References

- **Plan**: `/plans/66fad9b9-c010-4886-98e4-d8f1bb3238a6` (if available)
- **Feature Branch**: `feature/voucher-metadata`
- **Related Documentation**:
  - `docs/BANKS_JSON_UPDATE.md` - Similar metadata pattern
  - `docs/NOTIFICATION_TEMPLATES.md` - Template customization
  - `config/voucher.php` - Configuration reference
