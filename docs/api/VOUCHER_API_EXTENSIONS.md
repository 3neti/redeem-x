# Voucher API Extensions - Implementation Plan

> **Single Source of Truth**: This document consolidates all implementation planning for voucher API extensions to support external integrations like QuestPay, loyalty programs, and other systems.

## Context & Motivation

### The Need
External systems (games, loyalty programs, event ticketing) need to:
- Track their own metadata with vouchers (game_id, player_id, challenge_id, etc.)
- Validate redemptions by location (geo-fencing) and time (time windows, duration tracking)
- Receive comprehensive webhook payloads with GPS, photos, timing data
- Generate multiple vouchers efficiently via API
- Query voucher status programmatically

### Current Architecture
- Base package: `frittenkeez/laravel-vouchers` (provides `metadata` JSON field)
- Extended by: `lbhurtado/voucher` (adds `VoucherInstructionsData` stored in `metadata['instructions']`)
- Instructions comprise: `cash`, `inputs`, `feedback`, `rider`, `count`, `prefix`, `mask`, `ttl`

### Solution Approach
- **Leverage existing `metadata` field** - No new database columns needed
- **Extend `VoucherInstructionsData`** - Add `validation` instruction for location/time rules
- **Use DTOs throughout** - Match existing pattern (every component is a Data object)
- **Organize with traits** - Keep Voucher model clean, group new functionality
- **API-first design** - External systems integrate before UI exists
- **Fully backward compatible** - All new features optional, existing code works unchanged

## Architecture Principles

1. **DTO-First**: Every component is a proper DTO (Data Transfer Object), not a plain array
2. **API-First**: Design and implement APIs before UI, ensuring external systems can integrate cleanly
3. **Non-Breaking**: All changes must be fully backward compatible. Existing vouchers and code continue to work
4. **Test-Driven**: Write tests for each component before or alongside implementation
5. **Trait-Based Extensions**: New voucher model functionality organized in traits to maintain clean separation
6. **Package-First**: Changes primarily in `packages/voucher`, following established patterns

```php
// Current pattern in VoucherInstructionsData
public function __construct(
    public CashInstructionData     $cash,        // DTO ✅
    public InputFieldsData         $inputs,      // DTO ✅
    public FeedbackInstructionData $feedback,    // DTO ✅
    public RiderInstructionData    $rider,       // DTO ✅
    // scalars
    public ?int                    $count,
    public ?string                 $prefix,
    public ?string                 $mask,
    public CarbonInterval|null     $ttl,
)
```

**Our additions will follow the same pattern** - Everything will be a proper DTO.

---

## Enhanced Structure (DTO-Based)

### Metadata Structure
```php
$voucher->metadata = [
    'instructions' => VoucherInstructionsData::class,
    'external' => ExternalMetadataData::class,      // NEW DTO
    'timing' => VoucherTimingData::class,           // NEW DTO
    'validation_results' => ValidationResultsData::class,  // NEW DTO
];
```

### Extended VoucherInstructionsData
```php
class VoucherInstructionsData extends Data
{
    public function __construct(
        public CashInstructionData       $cash,
        public InputFieldsData           $inputs,
        public FeedbackInstructionData   $feedback,
        public RiderInstructionData      $rider,
        public ValidationInstructionData $validation,  // NEW DTO ✅
        public ?int                      $count,
        public ?string                   $prefix,
        public ?string                   $mask,
        public CarbonInterval|null       $ttl,
    ) {
        $this->applyRulesAndDefaults();
    }
}
```

---

## Implementation Phases

### Phase 1: Core DTO Classes (Days 1-4)

#### Task 1.1: ExternalMetadataData DTO
**Effort**: 0.5 days

**Create DTO**:
```php
// packages/voucher/src/Data/ExternalMetadataData.php
namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * External system metadata for voucher tracking
 * Flexible structure - integrators can add any fields they need
 */
class ExternalMetadataData extends Data
{
    public function __construct(
        public ?string $external_id = null,
        public ?string $external_type = null,
        public ?string $reference_id = null,
        public ?string $user_id = null,
        public ?array $custom = null,  // For additional fields
    ) {}

    public static function rules(): array
    {
        return [
            'external_id' => ['nullable', 'string', 'max:255'],
            'external_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'max:255'],
            'custom' => ['nullable', 'array'],
        ];
    }

    /**
     * Get a custom field value
     */
    public function getCustom(string $key, mixed $default = null): mixed
    {
        return $this->custom[$key] ?? $default;
    }

    /**
     * Check if custom field exists
     */
    public function hasCustom(string $key): bool
    {
        return isset($this->custom[$key]);
    }
}
```

**Create trait for external metadata functionality**:
```php
// packages/voucher/src/Traits/HasExternalMetadata.php
namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\ExternalMetadataData;

trait HasExternalMetadata
{
    /**
     * Get external metadata as DTO
     */
    public function getExternalMetadataAttribute(): ?ExternalMetadataData
    {
        if (!isset($this->metadata['external'])) {
            return null;
        }
        
        return ExternalMetadataData::from($this->metadata['external']);
    }

    /**
     * Set external metadata from DTO or array
     */
    public function setExternalMetadataAttribute(ExternalMetadataData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['external']);
            $this->metadata = $metadata;
            return;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['external'] = $value instanceof ExternalMetadataData 
            ? $value->toArray() 
            : $value;
        $this->metadata = $metadata;
    }

    /**
     * Query scope: Filter by external metadata field
     */
    public function scopeWhereExternal($query, string $field, mixed $value)
    {
        return $query->whereJsonContains("metadata->external->{$field}", $value);
    }
}
```

**Add trait to Voucher model**:
```php
// packages/voucher/src/Models/Voucher.php
use LBHurtado\Voucher\Traits\HasExternalMetadata;
use LBHurtado\Voucher\Traits\HasVoucherTiming;
use LBHurtado\Voucher\Traits\HasValidationResults;
use LBHurtado\Voucher\Traits\HasVerification;

class Voucher extends BaseVoucher implements InputInterface
{
    use WithData;
    use HasInputs;
    use HasExternalMetadata;      // NEW
    use HasVoucherTiming;         // NEW
    use HasValidationResults;     // NEW  
    use HasVerification;          // NEW
    
    // ... rest of existing code
}
```

**Usage**:
```php
// Creating voucher with external metadata
$voucher->external_metadata = ExternalMetadataData::from([
    'external_id' => 'EXT-001',
    'user_id' => 'USER-042',
    'custom' => [
        'game_id' => 'GAME-123',
        'level' => 5,
    ],
]);

// Accessing
$externalId = $voucher->external_metadata->external_id;
$gameId = $voucher->external_metadata->getCustom('game_id');

// Querying
Voucher::whereExternal('user_id', 'USER-042')->get();
```

---

#### Task 1.2: VoucherTimingData DTO
**Effort**: 0.5 days

**Create DTO**:
```php
// packages/voucher/src/Data/VoucherTimingData.php
namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * Tracks voucher lifecycle timing
 */
class VoucherTimingData extends Data
{
    public function __construct(
        public ?string $clicked_at = null,
        public ?string $started_at = null,
        public ?string $submitted_at = null,
        public ?int $duration_seconds = null,
    ) {}

    public static function rules(): array
    {
        return [
            'clicked_at' => ['nullable', 'string'],     // ISO-8601 datetime
            'started_at' => ['nullable', 'string'],     // ISO-8601 datetime
            'submitted_at' => ['nullable', 'string'],   // ISO-8601 datetime
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get clicked timestamp as Carbon instance
     */
    public function getClickedAt(): ?Carbon
    {
        return $this->clicked_at ? Carbon::parse($this->clicked_at) : null;
    }

    /**
     * Get started timestamp as Carbon instance
     */
    public function getStartedAt(): ?Carbon
    {
        return $this->started_at ? Carbon::parse($this->started_at) : null;
    }

    /**
     * Get submitted timestamp as Carbon instance
     */
    public function getSubmittedAt(): ?Carbon
    {
        return $this->submitted_at ? Carbon::parse($this->submitted_at) : null;
    }

    /**
     * Calculate duration if not already set
     */
    public function calculateDuration(): ?int
    {
        if (!$this->started_at || !$this->submitted_at) {
            return null;
        }

        return $this->getSubmittedAt()->diffInSeconds($this->getStartedAt());
    }

    /**
     * Create initial timing with click event
     */
    public static function withClick(): self
    {
        return new self(clicked_at: now()->toIso8601String());
    }
}
```

**Create trait for timing functionality**:
```php
// packages/voucher/src/Traits/HasVoucherTiming.php
namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\VoucherTimingData;

trait HasVoucherTiming
{
    /**
     * Get timing data as DTO
     */
    public function getTimingAttribute(): ?VoucherTimingData
    {
        if (!isset($this->metadata['timing'])) {
            return null;
        }
        
        return VoucherTimingData::from($this->metadata['timing']);
    }

    /**
     * Set timing data from DTO or array
     */
    public function setTimingAttribute(VoucherTimingData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['timing']);
            $this->metadata = $metadata;
            return;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['timing'] = $value instanceof VoucherTimingData 
            ? $value->toArray() 
            : $value;
        $this->metadata = $metadata;
    }

    /**
     * Track click event (idempotent)
     */
    public function trackClick(): void
    {
        if ($this->timing && $this->timing->clicked_at) {
            return; // Already tracked
        }
        
        $timing = $this->timing ?? VoucherTimingData::from([]);
        $timing->clicked_at = now()->toIso8601String();
        $this->timing = $timing;
        $this->save();
    }

    /**
     * Track redemption start
     */
    public function trackRedemptionStart(): void
    {
        $timing = $this->timing ?? VoucherTimingData::from([]);
        $timing->started_at = now()->toIso8601String();
        $this->timing = $timing;
        $this->save();
    }

    /**
     * Track redemption submission and calculate duration
     */
    public function trackRedemptionSubmit(): void
    {
        $timing = $this->timing ?? VoucherTimingData::from([]);
        $timing->submitted_at = now()->toIso8601String();
        $timing->duration_seconds = $timing->calculateDuration();
        $this->timing = $timing;
        $this->save();
    }
}
```

---

#### Task 1.3: ValidationInstructionData DTO (extends instructions)
**Effort**: 1.5 days

**Create validation DTOs**:
```php
// packages/voucher/src/Data/ValidationInstructionData.php
namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

class ValidationInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?LocationValidationData $location = null,
        public ?TimeValidationData $time = null,
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'location' => ['nullable', 'array'],
            'time' => ['nullable', 'array'],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'location' => [
                ['nullable'],
                null,
            ],
            'time' => [
                ['nullable'],
                null,
            ],
        ];
    }
}

// packages/voucher/src/Data/LocationValidationData.php
class LocationValidationData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public bool $required,
        public float $target_lat,
        public float $target_lng,
        public int $radius_meters,
        public string $on_failure = 'block', // 'block' or 'warn'
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'required' => ['required', 'boolean'],
            'target_lat' => ['required', 'numeric', 'between:-90,90'],
            'target_lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:1', 'max:10000'],
            'on_failure' => ['required', 'in:block,warn'],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'required' => [
                ['required', 'boolean'],
                config('instructions.validation.location.required', true),
            ],
            'radius_meters' => [
                ['required', 'integer', 'min:1'],
                config('instructions.validation.location.default_radius_meters', 50),
            ],
            'on_failure' => [
                ['required', 'in:block,warn'],
                config('instructions.validation.location.on_failure', 'block'),
            ],
        ];
    }

    /**
     * Validate user's location against target
     */
    public function validate(float $userLat, float $userLng): LocationValidationResultData
    {
        $distance = $this->calculateDistance($userLat, $userLng);
        $withinRadius = $distance <= $this->radius_meters;

        return LocationValidationResultData::from([
            'validated' => $withinRadius,
            'distance_meters' => round($distance, 2),
            'should_block' => !$withinRadius && $this->on_failure === 'block',
        ]);
    }

    /**
     * Calculate distance using Haversine formula
     */
    protected function calculateDistance(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($this->target_lat - $lat);
        $dLon = deg2rad($this->target_lng - $lng);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat)) * cos(deg2rad($this->target_lat)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}

// packages/voucher/src/Data/TimeValidationData.php
class TimeValidationData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?TimeWindowData $window = null,
        public ?int $limit_minutes = null,
        public bool $track_duration = false,
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'window' => ['nullable', 'array'],
            'limit_minutes' => ['nullable', 'integer', 'min:1'],
            'track_duration' => ['nullable', 'boolean'],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'track_duration' => [
                ['boolean'],
                config('instructions.validation.time.track_duration', false),
            ],
        ];
    }
}

// packages/voucher/src/Data/TimeWindowData.php
class TimeWindowData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public string $start_time,
        public string $end_time,
        public string $timezone = 'Asia/Manila',
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'timezone' => [
                ['required', 'timezone'],
                config('instructions.validation.time.default_timezone', 'Asia/Manila'),
            ],
        ];
    }

    /**
     * Check if current time is within window
     */
    public function isWithinWindow(): bool
    {
        $now = now($this->timezone);
        $start = Carbon::parse($this->start_time, $this->timezone);
        $end = Carbon::parse($this->end_time, $this->timezone);

        return $now->between($start, $end);
    }
}
```

**Update VoucherInstructionsData**:
```php
class VoucherInstructionsData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public CashInstructionData       $cash,
        public InputFieldsData           $inputs,
        public FeedbackInstructionData   $feedback,
        public RiderInstructionData      $rider,
        public ValidationInstructionData $validation,  // NEW ✅
        public ?int                      $count,
        public ?string                   $prefix,
        public ?string                   $mask,
        #[WithTransformer(TtlToStringTransformer::class)]
        #[WithCast(CarbonIntervalCast::class)]
        public CarbonInterval|null       $ttl,
    ){
        $this->applyRulesAndDefaults();
    }

    // Update generateFromScratch to include validation
    public static function generateFromScratch(): VoucherInstructionsData
    {
        $data_array = [
            'cash' => [...],
            'inputs' => [...],
            'feedback' => [...],
            'rider' => [...],
            'validation' => [  // NEW
                'location' => null,
                'time' => null,
            ],
            'count' => 1,
            'prefix' => null,
            'mask' => null,
            'ttl' => null,
        ];

        return VoucherInstructionsData::from($data_array);
    }
}
```

---

#### Task 1.4: ValidationResultsData DTO
**Effort**: 0.5 days

**Create DTO**:
```php
// packages/voucher/src/Data/ValidationResultsData.php
namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Stores validation results after redemption
 */
class ValidationResultsData extends Data
{
    public function __construct(
        public ?LocationValidationResultData $location = null,
        public ?TimeValidationResultData $time = null,
    ) {}

    public static function rules(): array
    {
        return [
            'location' => ['nullable', 'array'],
            'time' => ['nullable', 'array'],
        ];
    }
}

// packages/voucher/src/Data/LocationValidationResultData.php
class LocationValidationResultData extends Data
{
    public function __construct(
        public bool $validated,
        public float $distance_meters,
        public bool $should_block,
    ) {}

    public static function rules(): array
    {
        return [
            'validated' => ['required', 'boolean'],
            'distance_meters' => ['required', 'numeric', 'min:0'],
            'should_block' => ['required', 'boolean'],
        ];
    }
}

// packages/voucher/src/Data/TimeValidationResultData.php
class TimeValidationResultData extends Data
{
    public function __construct(
        public bool $within_window,
        public bool $within_time_limit,
        public ?int $elapsed_minutes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'within_window' => ['required', 'boolean'],
            'within_time_limit' => ['required', 'boolean'],
            'elapsed_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

**Create trait for validation results**:
```php
// packages/voucher/src/Traits/HasValidationResults.php
namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\ValidationResultsData;

trait HasValidationResults
{
    /**
     * Get validation results as DTO
     */
    public function getValidationResultsAttribute(): ?ValidationResultsData
    {
        if (!isset($this->metadata['validation_results'])) {
            return null;
        }
        
        return ValidationResultsData::from($this->metadata['validation_results']);
    }

    /**
     * Set validation results from DTO or array
     */
    public function setValidationResultsAttribute(ValidationResultsData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['validation_results']);
            $this->metadata = $metadata;
            return;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['validation_results'] = $value instanceof ValidationResultsData 
            ? $value->toArray() 
            : $value;
        $this->metadata = $metadata;
    }
}
```

---

#### Task 1.5: Enhanced Webhook Payload
**Effort**: 1 day

**Update webhook service to use DTOs**:
```php
// app/Services/VoucherWebhookService.php (or in VoucherObserver)
protected function buildEnhancedPayload(Voucher $voucher): array
{
    return [
        'event' => 'voucher.redeemed',
        'voucher_code' => $voucher->code,
        'amount' => $voucher->instructions->cash->amount,
        'currency' => $voucher->instructions->cash->currency,
        
        // External metadata (DTO)
        'external_metadata' => $voucher->external_metadata?->toArray(),
        
        // Collected input data
        'collected_data' => [
            'location' => $this->formatLocation($voucher),
            'photos' => $this->formatPhotos($voucher),
            'text_responses' => $this->formatTextResponses($voucher),
            'signature' => $voucher->getInputValue('signature'),
        ],
        
        // Timing information (DTO)
        'timing' => $voucher->timing?->toArray(),
        
        // Validation results (DTO)
        'validation_results' => $voucher->validation_results?->toArray(),
        
        'redeemed_at' => $voucher->redeemed_at->toIso8601String(),
    ];
}

protected function formatLocation(Voucher $voucher): ?array
{
    $location = $voucher->getInputValue('location');
    if (!$location) return null;
    
    $locationResult = $voucher->validation_results?->location;
    
    return [
        'lat' => $location['latitude'] ?? $location['lat'],
        'lng' => $location['longitude'] ?? $location['lng'],
        'accuracy' => $location['accuracy'] ?? null,
        'validated' => $locationResult?->validated,
        'distance_meters' => $locationResult?->distance_meters,
    ];
}
```

---

### Phase 2: Validation Implementation (Days 5-8)

#### Task 2.1: Location Validation Flow
**Effort**: 1.5 days

**Redemption controller integration**:
```php
// app/Http/Controllers/VoucherRedemptionController.php
public function submit(RedeemVoucherRequest $request, Voucher $voucher)
{
    // Track redemption start
    $voucher->trackRedemptionStart();
    
    // Initialize validation results
    $validationResults = ValidationResultsData::from([
        'location' => null,
        'time' => null,
    ]);
    
    // Validate location if configured
    if ($locationValidation = $voucher->instructions->validation?->location) {
        $userLocation = $request->input('location');
        
        $locationResult = $locationValidation->validate(
            $userLocation['latitude'],
            $userLocation['longitude']
        );
        
        // Store result
        $validationResults->location = $locationResult;
        
        // Block if validation failed
        if ($locationResult->should_block) {
            $voucher->validation_results = $validationResults;
            $voucher->save();
            
            return back()->withErrors([
                'location' => sprintf(
                    'You must be within %dm of the target location. You are %.2fm away.',
                    $locationValidation->radius_meters,
                    $locationResult->distance_meters
                )
            ]);
        }
    }
    
    // Save validation results
    $voucher->validation_results = $validationResults;
    
    // Track submission and continue redemption
    $voucher->trackRedemptionSubmit();
    
    // ... rest of redemption logic
}
```

---

#### Task 2.2: Time Validation Flow
**Effort**: 1.5 days

```php
public function submit(RedeemVoucherRequest $request, Voucher $voucher)
{
    // ... location validation above ...
    
    // Validate time constraints
    $timeResult = TimeValidationResultData::from([
        'within_window' => true,
        'within_time_limit' => true,
        'elapsed_minutes' => null,
    ]);
    
    if ($timeValidation = $voucher->instructions->validation?->time) {
        // Check time window
        if ($timeWindow = $timeValidation->window) {
            $timeResult->within_window = $timeWindow->isWithinWindow();
            
            if (!$timeResult->within_window) {
                return back()->withErrors([
                    'time' => sprintf(
                        'This voucher can only be redeemed between %s and %s (%s).',
                        $timeWindow->start_time,
                        $timeWindow->end_time,
                        $timeWindow->timezone
                    )
                ]);
            }
        }
        
        // Check time limit
        if ($timeLimit = $timeValidation->limit_minutes) {
            $clickedAt = $voucher->timing?->getClickedAt();
            if ($clickedAt) {
                $elapsed = now()->diffInMinutes($clickedAt);
                $timeResult->elapsed_minutes = $elapsed;
                $timeResult->within_time_limit = $elapsed <= $timeLimit;
                
                if (!$timeResult->within_time_limit) {
                    return back()->withErrors([
                        'time' => sprintf(
                            'Time limit of %d minutes exceeded. Elapsed: %d minutes.',
                            $timeLimit,
                            $elapsed
                        )
                    ]);
                }
            }
        }
    }
    
    // Update validation results with time result
    $validationResults->time = $timeResult;
    $voucher->validation_results = $validationResults;
    
    // ... continue redemption ...
}
```

---

#### Task 2.3: Bulk Voucher Generation API
**Effort**: 0.5 days

**API-First Design**: Create API endpoint before UI implementation.

```php
// routes/api.php
Route::post('/vouchers/bulk-create', [VoucherApiController::class, 'bulkCreate'])
    ->middleware('auth:sanctum');

// app/Http/Requests/BulkCreateVoucherRequest.php
class BulkCreateVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'exists:campaigns,id'],
            'vouchers' => ['required', 'array', 'min:1', 'max:1000'],
            'vouchers.*.mobile' => ['required', 'string'],
            'vouchers.*.external_metadata' => ['nullable', 'array'],
        ];
    }
}

// app/Http/Controllers/Api/VoucherApiController.php
public function bulkCreate(BulkCreateVoucherRequest $request)
{
    $campaign = Campaign::findOrFail($request->campaign_id);
    $instructions = $campaign->instructions;
    $requestedCount = count($request->vouchers);
    
    // Respect campaign's count setting (backward compatible)
    // If campaign has count=1, generate 1 voucher per request item
    // This allows external systems to control generation
    $vouchersPerRequest = $instructions->count ?? 1;
    
    $vouchers = [];
    
    DB::transaction(function () use ($request, $campaign, $vouchersPerRequest, &$vouchers) {
        foreach ($request->vouchers as $voucherData) {
            // Generate vouchers based on campaign count setting
            for ($i = 0; $i < $vouchersPerRequest; $i++) {
                // Create voucher using campaign's instructions
                $voucher = $campaign->createVoucher([
                    'mobile' => $voucherData['mobile'],
                ]);
                
                // Add external metadata if provided (as DTO)
                if (isset($voucherData['external_metadata'])) {
                    $voucher->external_metadata = ExternalMetadataData::from(
                        $voucherData['external_metadata']
                    );
                    $voucher->save();
                }
                
                $vouchers[] = [
                    'code' => $voucher->code,
                    'mobile' => $voucher->mobile,
                    'external_metadata' => $voucher->external_metadata?->toArray(),
                ];
            }
        }
    });
    
    return response()->json([
        'created' => count($vouchers),
        'requested' => $requestedCount,
        'per_request' => $vouchersPerRequest,
        'vouchers' => $vouchers,
    ], 201);
}
```

**Testing**:
```php
// tests/Feature/BulkVoucherCreationTest.php
public function test_respects_campaign_count_setting()
{
    $campaign = Campaign::factory()->create([
        'instructions' => [..., 'count' => 3],
    ]);
    
    $response = $this->postJson('/api/vouchers/bulk-create', [
        'campaign_id' => $campaign->id,
        'vouchers' => [
            ['mobile' => '09171234567'],
            ['mobile' => '09177654321'],
        ],
    ]);
    
    // Should create 6 vouchers (2 requests × 3 count)
    $response->assertJson(['created' => 6]);
}

public function test_backward_compatible_with_count_one()
{
    $campaign = Campaign::factory()->create([
        'instructions' => [..., 'count' => 1], // Default
    ]);
    
    $response = $this->postJson('/api/vouchers/bulk-create', [
        'campaign_id' => $campaign->id,
        'vouchers' => [
            ['mobile' => '09171234567'],
        ],
    ]);
    
    // Should create 1 voucher (backward compatible)
    $response->assertJson(['created' => 1]);
}
```

---

#### Task 2.4: Voucher Status Query API
**Effort**: 0.5 days

```php
// routes/api.php
Route::get('/vouchers/{code}/status', [VoucherApiController::class, 'status'])
    ->middleware('auth:sanctum');

// Controller
public function status(string $code)
{
    $voucher = Voucher::where('code', $code)->firstOrFail();
    
    return response()->json([
        'code' => $voucher->code,
        'status' => $this->determineStatus($voucher),
        'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
        'expires_at' => $voucher->expires_at?->toIso8601String(),
        'external_metadata' => $voucher->external_metadata?->toArray(),
        'timing' => $voucher->timing?->toArray(),
        'validation_results' => $voucher->validation_results?->toArray(),
        'collected_data' => $voucher->isRedeemed() 
            ? $this->formatCollectedData($voucher) 
            : null,
    ]);
}

protected function determineStatus(Voucher $voucher): string
{
    if ($voucher->isRedeemed()) return 'redeemed';
    if ($voucher->isExpired()) return 'expired';
    if ($voucher->timing?->clicked_at) return 'clicked';
    return 'pending';
}
```

---

### Phase 3: Advanced Features (Days 9-12)

#### Task 3.1: Extend FeedbackInstructionData for Multiple Events
**Effort**: 1.5 days

```php
// packages/voucher/src/Data/FeedbackInstructionData.php
class FeedbackInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $webhook = null,
        public ?WebhookConfigData $webhook_config = null,  // NEW ✅
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'webhook' => ['nullable', 'url'],
            'webhook_config' => ['nullable', 'array'],
        ];
    }
}

// packages/voucher/src/Data/WebhookConfigData.php
class WebhookConfigData extends Data
{
    public function __construct(
        public array $events = ['redeemed'],
        public int $timeout_seconds = 10,
        public int $retry_attempts = 3,
    ) {}

    public static function rules(): array
    {
        return [
            'events' => ['required', 'array'],
            'events.*' => ['string', 'in:clicked,started,redeemed,verified'],
            'timeout_seconds' => ['integer', 'min:1', 'max:30'],
            'retry_attempts' => ['integer', 'min:0', 'max:5'],
        ];
    }

    public function shouldSendFor(string $eventName): bool
    {
        return in_array($eventName, $this->events);
    }
}
```

**Event listener**:
```php
class SendVoucherWebhook
{
    public function handle($event)
    {
        $voucher = $event->voucher;
        $feedback = $voucher->instructions->feedback;
        
        if (!$feedback->webhook) return;
        
        // Check if subscribed to this event
        $eventName = $this->getEventName($event);
        $config = $feedback->webhook_config ?? WebhookConfigData::from([]);
        
        if (!$config->shouldSendFor($eventName)) {
            return;
        }
        
        // Send webhook with retry logic
        Http::timeout($config->timeout_seconds)
            ->retry($config->retry_attempts)
            ->post($feedback->webhook, $this->buildPayload($event));
    }
}
```

---

#### Task 3.2: Manual Verification with DTO
**Effort**: 1 day

```php
// packages/voucher/src/Data/VerificationData.php
class VerificationData extends Data
{
    public function __construct(
        public string $status,     // 'approved' or 'rejected'
        public int $verified_by,
        public string $verified_at,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'verified_by' => ['required', 'integer'],
            'verified_at' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

**Create trait for verification**:
```php
// packages/voucher/src/Traits/HasVerification.php
namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\VerificationData;

trait HasVerification
{
    /**
     * Get verification data as DTO
     */
    public function getVerificationAttribute(): ?VerificationData
    {
        if (!isset($this->metadata['verification'])) {
            return null;
        }
        
        return VerificationData::from($this->metadata['verification']);
    }

    /**
     * Set verification data from DTO or array
     */
    public function setVerificationAttribute(VerificationData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['verification']);
            $this->metadata = $metadata;
            return;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['verification'] = $value instanceof VerificationData 
            ? $value->toArray() 
            : $value;
        $this->metadata = $metadata;
    }
}
```

**Admin controller**:
```php
public function approve(Request $request, Voucher $voucher)
{
    $voucher->verification = VerificationData::from([
        'status' => 'approved',
        'verified_by' => auth()->id(),
        'verified_at' => now()->toIso8601String(),
        'notes' => $request->notes,
    ]);
    $voucher->save();
    
    event(new VoucherManuallyVerified($voucher, true));
    
    return back()->with('success', 'Voucher approved');
}
```

---

## Complete Metadata Structure (All DTOs)

```php
// Voucher metadata with all DTOs
$voucher->metadata = [
    'instructions' => [
        'cash' => CashInstructionData,
        'inputs' => InputFieldsData,
        'feedback' => FeedbackInstructionData,  // with WebhookConfigData
        'rider' => RiderInstructionData,
        'validation' => ValidationInstructionData,  // NEW
        'count' => 1,
        'prefix' => 'VCH',
        'mask' => '****',
        'ttl' => 'PT12H',
    ],
    'external' => ExternalMetadataData,         // NEW
    'timing' => VoucherTimingData,              // NEW
    'validation_results' => ValidationResultsData,  // NEW
    'verification' => VerificationData,         // NEW (optional)
];
```

---

## Database Schema

**No changes needed!** Everything uses existing `metadata` JSON field.

---

## Testing Strategy

**Testing is mandatory** - All new functionality must have comprehensive tests before merging.

### Test-Driven Development Approach
1. Write API contract tests first
2. Write unit tests for DTOs
3. Implement functionality
4. Write integration tests
5. Verify backward compatibility

### Unit Tests
```bash
# DTO validation and behavior
php artisan test tests/Unit/Data/ExternalMetadataDataTest.php
php artisan test tests/Unit/Data/VoucherTimingDataTest.php
php artisan test tests/Unit/Data/LocationValidationDataTest.php
php artisan test tests/Unit/Data/TimeValidationDataTest.php
php artisan test tests/Unit/Data/ValidationResultsDataTest.php
php artisan test tests/Unit/Data/WebhookConfigDataTest.php
php artisan test tests/Unit/Data/VerificationDataTest.php

# Distance calculation accuracy
php artisan test tests/Unit/LocationValidationDistanceTest.php
```

### Feature Tests
```bash
# API endpoints
php artisan test tests/Feature/Api/VoucherCreationApiTest.php
php artisan test tests/Feature/Api/BulkVoucherCreationApiTest.php
php artisan test tests/Feature/Api/VoucherStatusApiTest.php

# Traits functionality
php artisan test tests/Feature/VoucherExternalMetadataTraitTest.php
php artisan test tests/Feature/VoucherTimingTraitTest.php
php artisan test tests/Feature/VoucherValidationResultsTraitTest.php
php artisan test tests/Feature/VoucherVerificationTraitTest.php

# Validation flows
php artisan test tests/Feature/LocationValidationFlowTest.php
php artisan test tests/Feature/TimeValidationFlowTest.php

# Webhooks
php artisan test tests/Feature/WebhookPayloadTest.php
php artisan test tests/Feature/WebhookEventSubscriptionTest.php
```

### Backward Compatibility Tests
```bash
# Critical: Ensure existing functionality works
php artisan test tests/Feature/BackwardCompatibility/ExistingVoucherRedemptionTest.php
php artisan test tests/Feature/BackwardCompatibility/ExistingCampaignTest.php
php artisan test tests/Feature/BackwardCompatibility/ExistingWebhookTest.php

# Test cases:
# - Existing vouchers without new metadata continue to work
# - Existing campaigns generate vouchers normally
# - Vouchers without validation instructions redeem normally
# - Webhooks work without new payload fields
```

### Integration Tests
```bash
# End-to-end flows
php artisan test tests/Integration/VoucherLifecycleWithValidationTest.php
php artisan test tests/Integration/BulkGenerationToRedemptionTest.php

# Test complete flow:
# 1. Create campaign with validation instructions
# 2. Generate vouchers via API
# 3. Track timing through redemption
# 4. Validate location and time
# 5. Send webhook with full payload
# 6. Query status via API
```

### Performance Tests
```bash
# Ensure new features don't degrade performance
php artisan test tests/Performance/BulkVoucherCreationPerformanceTest.php

# Test cases:
# - Generate 1000 vouchers in < 10 seconds
# - Location validation adds < 50ms per redemption
# - JSON metadata queries perform well
```

---

## Timeline

| Phase | Duration | Tasks |
|-------|----------|-------|
| Phase 1 | 4 days | All DTO classes, webhook enhancements |
| Phase 2 | 4 days | Validation flows, APIs |
| Phase 3 | 4 days | Advanced features |
| **Total** | **12 days** | Full implementation |

**Buffer**: +3 days = **15 days total**

---

## Key Advantages

1. ✅ **Type-safe throughout** - Every component is a proper DTO
2. ✅ **Follows existing patterns** - Matches `VoucherInstructionsData` architecture
3. ✅ **No database migrations** - Uses existing `metadata` field
4. ✅ **Validation built-in** - Spatie Data validates on instantiation
5. ✅ **Easy serialization** - `toArray()` for JSON/webhooks
6. ✅ **IDE-friendly** - Full autocomplete and type hints
7. ✅ **Fully backward compatible** - Existing vouchers unaffected, all new features optional
8. ✅ **Clean separation** - Traits organize new functionality without cluttering core model
9. ✅ **API-first design** - External systems can integrate before UI exists
10. ✅ **Test coverage** - Comprehensive tests ensure stability

---

## Implementation Guidelines

### API-First Development
1. **Design API contract** - Document endpoints, request/response formats
2. **Write API tests** - Test contract before implementation
3. **Implement endpoint** - Make tests pass
4. **Document in OpenAPI** - Keep API docs current
5. **Build UI** - Consume your own API

### Backward Compatibility Checklist
Before merging any task:
- [ ] Existing vouchers redeem without errors
- [ ] Existing campaigns generate vouchers normally  
- [ ] Existing webhooks continue to fire
- [ ] New fields are optional/nullable
- [ ] Default values preserve old behavior
- [ ] No breaking changes to existing DTOs
- [ ] All existing tests still pass

### Code Organization
- **DTOs** → `packages/voucher/src/Data/`
- **Traits** → `packages/voucher/src/Traits/`
- **Tests** → `tests/Unit/Data/`, `tests/Feature/Api/`, `tests/Feature/BackwardCompatibility/`
- **API Controllers** → `app/Http/Controllers/Api/`
- **API Routes** → `routes/api.php`

### Testing Requirements
- **Unit tests** for all DTOs (validation, methods, defaults)
- **Feature tests** for all API endpoints
- **Integration tests** for complete flows
- **Backward compatibility tests** for existing functionality
- **Minimum 90% code coverage** for new code

## Next Steps

1. ✅ Review this implementation plan
2. Create feature branch: `git checkout -b feature/voucher-api-extensions`
3. Start with API design and tests
4. Implement Phase 1, Task 1.1 (ExternalMetadataData + trait)
5. Write comprehensive tests for each component
6. Update WARP.md with new testing commands
7. Document new APIs

**Ready to begin with API-first, test-driven, non-breaking implementation?**
