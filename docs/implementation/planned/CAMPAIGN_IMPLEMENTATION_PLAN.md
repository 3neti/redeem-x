# Campaign System Implementation Plan

## Overview
Implement a "Campaign" system that acts as reusable templates for voucher generation. Each campaign defines:
- Required inputs (selfie, signature, location, custom text fields)
- Validation rules
- Amount configuration
- Messages and feedbacks
- Rider assignment
- Image quality overrides

Users can select a campaign in the Generate Vouchers form to auto-populate all fields, with the ability to override before generation.

## Goals
1. **Reusability**: Save time by creating voucher templates for common use cases
2. **Consistency**: Ensure vouchers in a campaign follow the same rules
3. **Auditability**: Track which campaign generated each voucher via snapshot
4. **Multi-tenancy**: Each user owns and manages their own campaigns
5. **Defaults**: Seed new users with 2 starter templates

---

## Phase 1: Database Schema

### New Table: `campaigns`

```sql
CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    status VARCHAR(50) DEFAULT 'draft',
    instructions JSON NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Field Descriptions:**
- `user_id`: Owner of the campaign (multi-tenant isolation)
- `name`: Display name (e.g., "Food Relief - Barangay 12")
- `slug`: URL-friendly unique identifier
- `description`: Optional notes about the campaign
- `status`: `draft`, `active`, or `archived`
- `instructions`: JSON field storing complete `VoucherInstructionsData` (cash, inputs, feedback, rider, count, prefix, mask, ttl)

**Note:** Campaign uses the existing `VoucherInstructionsData` DTO from the voucher package, ensuring consistency across voucher generation.

### Pivot Table: `campaign_voucher`

```sql
CREATE TABLE campaign_voucher (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    instructions_snapshot JSON NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    UNIQUE (campaign_id, voucher_id)
);
```

**Field Descriptions:**
- `campaign_id`: Reference to the campaign
- `voucher_id`: Reference to the voucher
- `instructions_snapshot`: Full JSON snapshot of `VoucherInstructionsData` at generation time (immutable historical record)
- Uses many-to-many relationship instead of modifying the vouchers table directly

---

## Phase 2: JSON Schema Examples

### `required_inputs` Example
```json
[
  "SELFIE",
  "SIGNATURE",
  "LOCATION",
  "TEXT:name",
  "TEXT:email",
  "TEXT:mobile",
  "TEXT:household_size"
]
```

### `validations` Example
```json
{
  "can_redeem_once": true,
  "min_distance_m": 0,
  "expiry_days": 14,
  "max_redemptions_per_day": 100
}
```

### `amount` Example
```json
{
  "type": "fixed",
  "value": 500
}
```
*(Future: support "range" or "per-voucher")*

### `feedbacks` Example
```json
[
  {
    "channel": "sms",
    "recipient": "+639171234567",
    "template": "Thank you for redeeming your voucher!"
  },
  {
    "channel": "email",
    "recipient": "user@example.com",
    "template": "Your redemption has been confirmed."
  }
]
```

### `rider` Example
```json
{
  "assignment": "Rider-23",
  "notes": "Evening deliveries only"
}
```

### `image_overrides` Example
```json
{
  "selfie": {
    "width": 480,
    "height": 360,
    "quality": 0.7,
    "format": "image/jpeg"
  },
  "signature": {
    "quality": 0.8,
    "format": "image/png"
  }
}
```

---

## Phase 3: Backend Implementation

### 3.1 Models

**`app/Models/Campaign.php`**
```php
class Campaign extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'status',
        'required_inputs', 'validations', 'amount', 'message',
        'feedbacks', 'rider', 'image_overrides',
        'window_start', 'window_end', 'usage_limits', 'meta',
    ];

    protected $casts = [
        'required_inputs' => 'array',
        'validations' => 'array',
        'amount' => 'array',
        'feedbacks' => 'array',
        'rider' => 'array',
        'image_overrides' => 'array',
        'usage_limits' => 'array',
        'meta' => 'array',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'campaign_voucher')
            ->withPivot('instructions_snapshot')
            ->withTimestamps();
    }

    protected static function booted()
    {
        static::creating(function ($campaign) {
            if (empty($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->name . '-' . Str::random(6));
            }
        });
    }
}
```

**Update `packages/voucher/src/Models/Voucher.php`**
```php
public function campaigns(): BelongsToMany
{
    return $this->belongsToMany(Campaign::class, 'campaign_voucher')
        ->withPivot('instructions_snapshot')
        ->withTimestamps();
}
```

### 3.2 Policies

**`app/Policies/CampaignPolicy.php`**
```php
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Users can list their own campaigns
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->user_id;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->user_id;
    }

    public function duplicate(User $user, Campaign $campaign): bool
    {
        return $user->id === $campaign->user_id;
    }
}
```

Register in `AuthServiceProvider`:
```php
protected $policies = [
    Campaign::class => CampaignPolicy::class,
];
```

### 3.3 Form Requests

**`app/Http/Requests/Settings/StoreCampaignRequest.php`**
```php
class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles this
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,archived',
            'required_inputs' => 'nullable|array',
            'required_inputs.*' => 'string',
            'validations' => 'nullable|array',
            'amount' => 'nullable|array',
            'amount.type' => 'required_with:amount|in:fixed,range',
            'amount.value' => 'required_with:amount|numeric|min:0',
            'message' => 'nullable|string',
            'feedbacks' => 'nullable|array',
            'feedbacks.*.channel' => 'required|in:sms,email',
            'feedbacks.*.recipient' => 'required|string',
            'feedbacks.*.template' => 'required|string',
            'rider' => 'nullable|array',
            'image_overrides' => 'nullable|array',
            'window_start' => 'nullable|date',
            'window_end' => 'nullable|date|after:window_start',
            'usage_limits' => 'nullable|array',
            'meta' => 'nullable|array',
        ];
    }
}
```

**`app/Http/Requests/Settings/UpdateCampaignRequest.php`**
```php
class UpdateCampaignRequest extends StoreCampaignRequest
{
    // Inherits same rules
}
```

### 3.4 Controllers

**`app/Http/Controllers/Settings/CampaignController.php`**
```php
class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return Inertia::render('Settings/Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('Settings/Campaigns/Create');
    }

    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        $campaign = Campaign::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        return Inertia::render('Settings/Campaigns/Show', [
            'campaign' => $campaign,
        ]);
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        return Inertia::render('Settings/Campaigns/Edit', [
            'campaign' => $campaign,
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $campaign->update($request->validated());

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    public function duplicate(Campaign $campaign)
    {
        $this->authorize('duplicate', $campaign);

        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->slug = null; // Will auto-generate
        $newCampaign->status = 'draft';
        $newCampaign->save();

        return redirect()->route('settings.campaigns.edit', $newCampaign)
            ->with('success', 'Campaign duplicated successfully.');
    }
}
```

### 3.5 Routes

**`routes/settings.php`**
```php
Route::middleware(['auth:sanctum'])->prefix('settings')->name('settings.')->group(function () {
    // Existing routes...

    Route::resource('campaigns', Settings\CampaignController::class);
    Route::post('campaigns/{campaign}/duplicate', [Settings\CampaignController::class, 'duplicate'])
        ->name('campaigns.duplicate');
});
```

### 3.6 API Routes for Generate Vouchers

**`routes/api.php`**
```php
Route::middleware('auth:sanctum')->group(function () {
    // Fetch user's campaigns for dropdown
    Route::get('/campaigns', function (Request $request) {
        return Campaign::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->select('id', 'name', 'slug')
            ->get();
    });

    // Fetch single campaign details
    Route::get('/campaigns/{campaign}', function (Campaign $campaign) {
        Gate::authorize('view', $campaign);
        return $campaign;
    });
});
```

---

## Phase 4: Seed Default Campaigns on User Registration

### 4.1 Create Action

**`app/Actions/CreateDefaultCampaigns.php`**
```php
namespace App\Actions;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Str;

class CreateDefaultCampaigns
{
    public function handle(User $user): void
    {
        // 1. Blank Template
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Blank Template',
            'slug' => Str::slug('blank-template-' . $user->id),
            'status' => 'active',
            'description' => 'Start from scratch with no pre-filled fields',
            'required_inputs' => [],
            'validations' => null,
            'amount' => null,
            'message' => null,
            'feedbacks' => null,
            'rider' => null,
        ]);

        // 2. Standard Campaign
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Standard Campaign',
            'slug' => Str::slug('standard-campaign-' . $user->id),
            'status' => 'active',
            'description' => 'Full verification with selfie, signature, location, and contact info',
            'required_inputs' => [
                'SELFIE',
                'SIGNATURE',
                'LOCATION',
                'TEXT:name',
                'TEXT:email',
                'TEXT:mobile',
            ],
            'validations' => [
                'can_redeem_once' => true,
                'expiry_days' => 30,
            ],
            'amount' => [
                'type' => 'fixed',
                'value' => 100,
            ],
            'message' => 'Present this voucher to redeem.',
            'feedbacks' => [
                [
                    'channel' => 'sms',
                    'recipient' => $user->mobile ?? '',
                    'template' => 'Thank you for redeeming your voucher!',
                ],
                [
                    'channel' => 'email',
                    'recipient' => $user->email,
                    'template' => 'Your redemption has been confirmed.',
                ],
            ],
            'rider' => null,
        ]);
    }
}
```

### 4.2 Hook into User Creation

**In `app/Providers/FortifyServiceProvider.php`** (or wherever user creation happens):
```php
use App\Actions\CreateDefaultCampaigns;

Fortify::createUsersUsing(function ($request) {
    // Existing user creation logic...
    $user = User::create([...]);

    // Seed default campaigns
    app(CreateDefaultCampaigns::class)->handle($user);

    return $user;
});
```

---

## Phase 5: Frontend Implementation

### 5.1 Settings > Campaigns (Admin Panel)

**Navigation Update**
Add "Campaigns" link in Settings navigation (alongside SMS, Profile, etc.)

**Pages Structure:**
```
resources/js/pages/Settings/Campaigns/
  ├── Index.vue       (list all campaigns, search, filter by status)
  ├── Create.vue      (form to create new campaign)
  ├── Edit.vue        (form to edit existing campaign)
  └── Show.vue        (read-only view with usage stats)
```

**Index.vue Features:**
- Table with columns: Name, Status, Created, Actions
- Search bar
- Filter by status (draft/active/archived)
- Actions: Edit, Duplicate, Delete, View

**Create/Edit.vue Form Sections:**
1. **Basic Info**: Name, Description, Status
2. **Required Inputs**: 
   - Checkboxes for SELFIE, SIGNATURE, LOCATION
   - Dynamic text field builder (add TEXT:field_name)
3. **Validations**: 
   - Toggle "Redeem once"
   - Number input "Expiry days"
   - Other validation rules as needed
4. **Amount**: 
   - Radio: Fixed / Range (future)
   - Number input for value
5. **Message**: Textarea
6. **Feedbacks**:
   - Repeater: Channel (SMS/Email), Recipient, Template
   - "Use my profile mobile/email" quick-fill button
7. **Rider**: Free-text JSON or structured inputs
8. **Image Overrides** (collapsible/optional):
   - Selfie: width, height, quality, format
   - Signature: quality, format
9. **Advanced** (collapsible):
   - Window start/end dates
   - Usage limits JSON

**Show.vue Features:**
- Display all campaign config (read-only)
- Stats: Total vouchers generated, redeemed, pending
- "Edit" and "Duplicate" buttons

### 5.2 Generate Vouchers Integration

**Update `resources/js/pages/Vouchers/Create.vue`** (or wherever Generate Vouchers form lives):

1. Add "Campaign Template" dropdown at the top:
   ```vue
   <Select v-model="selectedCampaignId">
     <option value="">None (manual entry)</option>
     <option v-for="c in campaigns" :value="c.id">{{ c.name }}</option>
   </Select>
   ```

2. Watch `selectedCampaignId`:
   ```js
   watch(selectedCampaignId, async (id) => {
     if (!id) return;
     const campaign = await fetchCampaign(id);
     
     // Populate form fields
     form.required_inputs = campaign.required_inputs;
     form.validations = campaign.validations;
     form.amount = campaign.amount?.value;
     form.message = campaign.message;
     form.feedbacks = campaign.feedbacks;
     form.rider = campaign.rider;
     // etc.
   });
   ```

3. Add "Reset to campaign defaults" button (only show when campaign selected)

4. When submitting voucher generation:
   ```js
   const payload = {
     ...form,
     campaign_id: selectedCampaignId, // Will be attached to vouchers via pivot table
   };
   ```
   
   Backend will attach campaign to generated vouchers:
   ```php
   $vouchers = Voucher::generate($instructions);
   
   if ($campaignId) {
       $campaign = Campaign::find($campaignId);
       foreach ($vouchers as $voucher) {
           $voucher->campaigns()->attach($campaignId, [
               'instructions_snapshot' => $campaign->instructions->toArray()
           ]);
       }
   }
   ```

### 5.3 Redemption Flow (Image Overrides)

**Update `app/Http/Controllers/Redeem/RedeemController.php`:**
```php
public function selfie(Request $request)
{
    $voucher = Voucher::where('code', $request->code)->first();
    $imageConfig = $voucher?->campaign_snapshot['image_overrides']['selfie'] 
        ?? config('model-input.image_quality.selfie');

    return Inertia::render('Redeem/Selfie', [
        'code' => $request->code,
        'image_config' => $imageConfig,
    ]);
}
```

**Update `resources/js/pages/Redeem/Selfie.vue`:**
```js
const props = defineProps<{
  code: string;
  image_config: {
    width: number;
    height: number;
    quality: number;
    format: string;
  };
}>();

// Use props.image_config instead of global config
```

Same pattern for `Signature.vue`.

---

## Phase 6: Testing

### 6.1 Feature Tests

**`tests/Feature/Settings/CampaignTest.php`**
```php
test('user can create campaign', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'Test Campaign',
        'status' => 'active',
        'required_inputs' => ['SELFIE', 'SIGNATURE'],
    ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('campaigns', [
        'user_id' => $user->id,
        'name' => 'Test Campaign',
    ]);
});

test('user cannot view another users campaign', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user1->id]);
    
    $response = $this->actingAs($user2)->get("/settings/campaigns/{$campaign->id}");
    
    $response->assertForbidden();
});

test('duplicate campaign creates draft copy', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    
    $response = $this->actingAs($user)->post("/settings/campaigns/{$campaign->id}/duplicate");
    
    $response->assertRedirect();
    $this->assertDatabaseHas('campaigns', [
        'user_id' => $user->id,
        'name' => $campaign->name . ' (Copy)',
        'status' => 'draft',
    ]);
});
```

### 6.2 Unit Tests

**`tests/Unit/Models/CampaignTest.php`**
```php
test('campaign auto-generates slug on creation', function () {
    $campaign = Campaign::factory()->create(['name' => 'Test Campaign', 'slug' => null]);
    
    expect($campaign->slug)->toBeString()->toContain('test-campaign');
});

test('campaign casts json fields correctly', function () {
    $campaign = Campaign::factory()->create([
        'required_inputs' => ['SELFIE'],
        'validations' => ['can_redeem_once' => true],
    ]);
    
    expect($campaign->required_inputs)->toBeArray();
    expect($campaign->validations)->toBeArray();
});
```

### 6.3 Integration Tests

**`tests/Feature/Vouchers/GenerateWithCampaignTest.php`**
```php
test('generating voucher with campaign stores snapshot', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)->post('/vouchers', [
        'campaign_id' => $campaign->id,
        'quantity' => 1,
        // other fields...
    ]);
    
    $voucher = Voucher::first();
    expect($voucher->campaign_id)->toBe($campaign->id);
    expect($voucher->campaign_snapshot)->toMatchArray([
        'name' => $campaign->name,
        'required_inputs' => $campaign->required_inputs,
        // etc.
    ]);
});
```

### 6.4 Seeding Tests

**`tests/Feature/Auth/CreateDefaultCampaignsTest.php`**
```php
test('new user gets two default campaigns', function () {
    $user = User::factory()->create();
    
    app(CreateDefaultCampaigns::class)->handle($user);
    
    expect($user->campaigns)->toHaveCount(2);
    expect($user->campaigns->pluck('name'))->toContain('Blank Template', 'Standard Campaign');
});
```

---

## Phase 7: Migration and Deployment

### 7.1 Migration Files

**`database/migrations/xxxx_create_campaigns_table.php`**
```php
public function up()
{
    Schema::create('campaigns', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('status')->default('draft');
        $table->json('required_inputs')->nullable();
        $table->json('validations')->nullable();
        $table->json('amount')->nullable();
        $table->text('message')->nullable();
        $table->json('feedbacks')->nullable();
        $table->json('rider')->nullable();
        $table->json('image_overrides')->nullable();
        $table->timestamp('window_start')->nullable();
        $table->timestamp('window_end')->nullable();
        $table->json('usage_limits')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
}
```

**`database/migrations/xxxx_create_campaign_voucher_table.php`**
```php
public function up()
{
    Schema::create('campaign_voucher', function (Blueprint $table) {
        $table->id();
        $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
        $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
        $table->json('instructions_snapshot');
        $table->timestamps();
        
        $table->unique(['campaign_id', 'voucher_id']);
    });
}
```

### 7.2 Seeder for Existing Users

**`database/seeders/BackfillDefaultCampaignsSeeder.php`**
```php
public function run()
{
    User::whereDoesntHave('campaigns')->chunk(100, function ($users) {
        foreach ($users as $user) {
            app(CreateDefaultCampaigns::class)->handle($user);
        }
    });
}
```

Run after deployment:
```bash
php artisan db:seed --class=BackfillDefaultCampaignsSeeder
```

### 7.3 Factory

**`database/factories/CampaignFactory.php`**
```php
class CampaignFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->sentence,
            'status' => 'active',
            'required_inputs' => ['SELFIE', 'SIGNATURE'],
            'validations' => ['can_redeem_once' => true],
            'amount' => ['type' => 'fixed', 'value' => 100],
            'message' => $this->faker->sentence,
        ];
    }
}
```

---

## Phase 8: Documentation Updates

### 8.1 Update `WARP.md`

Add sections:
- **Campaign Management**: routes, controller actions
- **Default Campaign Templates**: describe Blank and Standard
- **Generate Vouchers with Campaigns**: how to select and override

### 8.2 User Guide (Optional)

Create `docs/USER_GUIDE_CAMPAIGNS.md` with:
- How to create a campaign
- How to use a campaign in voucher generation
- How to duplicate and modify campaigns
- Best practices for campaign naming and organization

---

## Acceptance Criteria

- ✅ Migration creates `campaigns` table with pivot table `campaign_voucher`
- ✅ New users automatically receive 2 default campaigns (Blank Template + Standard Campaign)
- ✅ Campaign model uses `VoucherInstructionsData` for instructions field
- ✅ Policies enforce campaign ownership (multi-tenancy)
- ✅ Users can view their campaigns in Settings > Campaigns (Index page)
- ✅ Backend tests pass (user seeding, factory, relationships)
- ⏳ Generate Vouchers has campaign dropdown (Phase 11)
- ⏳ Selecting campaign populates form fields (Phase 11)
- ⏳ Generated vouchers attach to campaigns via pivot table (Phase 11)
- ⏳ Full CRUD UI (Create/Edit/Show pages with form builder)
- ⏳ Duplicate action UI
- ⏳ Formal test suite (Phase 12)

---

## Optional Future Enhancements

1. **Campaign Analytics Dashboard**: Show metrics per campaign (redemption rate, total amount redeemed, etc.)
2. **Campaign Versioning**: Immutable snapshots when status changes to "active"
3. **Campaign Templates Marketplace**: Share/import campaigns between users
4. **Scheduled Campaigns**: Auto-activate/archive based on window_start/end
5. **Advanced Amount Types**: Range (min/max), per-voucher custom amounts
6. **Rider Management**: Separate `riders` table with assignments
7. **Bulk Campaign Operations**: Archive/delete multiple campaigns at once

---

## Timeline Estimate

| Phase | Estimated Time |
|-------|----------------|
| Phase 1: Database schema | 30 min |
| Phase 2-3: Backend (models, policies, controllers, routes) | 2 hours |
| Phase 4: Seed default campaigns | 30 min |
| Phase 5: Frontend (Settings UI + Generate Vouchers) | 3-4 hours |
| Phase 6: Testing | 1-2 hours |
| Phase 7: Migration, deployment | 30 min |
| Phase 8: Documentation | 30 min |
| **Total** | **8-10 hours** |

---

## Questions for Clarification

1. **Riders**: Should this be a separate `riders` table, or is JSON free-text sufficient for now?
   - **Decision**: JSON free-text for MVP

2. **Amount flexibility**: Do you need "range" (min/max) or just "fixed" for now?
   - **Decision**: Fixed for MVP, add range later if needed

3. **Campaign status transitions**: Any workflow rules? (e.g., can't edit "active" campaigns)
   - **Decision**: Simple edit-in-place for MVP; vouchers use snapshot so historical data is safe

4. **Feedback recipient**: Should default to user's profile, or require manual entry?
   - **Decision**: Standard template uses profile mobile/email; users can override

5. **Image overrides**: Should these be required or optional (fall back to global config)?
   - **Decision**: Optional; fall back to global config if not set

---

## Implementation Order

1. ✅ Document plan (this file)
2. ✅ Phase 1: Run migrations (campaigns + campaign_voucher pivot table)
3. ✅ Phase 2-3: Backend implementation (models, policies, controllers, routes)
4. ✅ Phase 4: Seed logic (UserObserver + CreateDefaultCampaigns action)
5. ✅ Phase 5: CampaignFactory for testing
6. ✅ Phase 6: Backend tests (manual tinker validation)
7. ✅ Phase 7: Frontend UI (Settings > Campaigns Index page MVP)
8. ✅ Phase 8: Update docs (WARP.md + this plan)
9. ⏳ Phase 9: Generate Vouchers integration
10. ⏳ Phase 10: Formal test suite (Pest)
11. ⏳ Phase 11: Full CRUD UI (Create/Edit/Show forms)

---

**End of Plan**
