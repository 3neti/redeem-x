# 🔍 Phase 2: Backend API Analysis - VoucherInstructionsData-Driven Architecture

**Date**: 2025-11-08  
**Status**: Analysis Complete

---

## 🎯 Core Insight: Everything Revolves Around VoucherInstructionsData

The x-change system is brilliantly architected around a **single source of truth**: `VoucherInstructionsData`. This DTO drives the entire voucher lifecycle from generation to redemption.

---

## 🏛️ Architecture Note: LBHurtado\Voucher Package

**Important:** The `lbhurtado/voucher` package is a **wrapper/extension** of FrittenKeeZ Vouchers:

```php
// lbhurtado/voucher/src/Models/Voucher.php
class Voucher extends \FrittenKeeZ\Vouchers\Models\Voucher
```

**Key enhancements:**
- ✅ `$instructions` accessor: `VoucherInstructionsData::from($metadata['instructions'])`
- ✅ `WithData` trait: Spatie Laravel Data integration
- ✅ `HasInputs` trait: Model-input package integration
- ✅ `$cash` accessor: Get attached Cash entity
- ✅ `$contact` accessor: Get redeemer's Contact
- ✅ Route model binding: Uses `code` instead of `id`
- ✅ VoucherObserver: Auto-processing logic

**Usage:**
```php
// Uses FrittenKeeZ facade, returns LBHurtado Voucher model
use FrittenKeeZ\Vouchers\Facades\Vouchers;
$vouchers = Vouchers::create(5); // Collection<LBHurtado\Voucher\Models\Voucher>

// Access instructions seamlessly
$voucher->instructions; // VoucherInstructionsData instance
$voucher->instructions->cash->amount; // 500.00
$voucher->instructions->inputs->fields; // [EMAIL, NAME, ...]
```

---

## 📊 VoucherInstructionsData Structure

```php
VoucherInstructionsData {
    // 💰 Cash Configuration
    cash: CashInstructionData {
        amount: float,
        currency: string,
        validation: CashValidationRulesData {
            secret: ?string,
            mobile: ?string,
            country: ?string,
            location: ?string,
            radius: ?string
        }
    },
    
    // 📥 Required Inputs from User
    inputs: InputFieldsData {
        fields: array<VoucherInputField> [
            EMAIL,
            MOBILE,
            NAME,
            ADDRESS,
            BIRTH_DATE,
            GROSS_MONTHLY_INCOME,
            SIGNATURE,
            LOCATION,
            REFERENCE_CODE,
            OTP
        ]
    },
    
    // 📢 Feedback Channels
    feedback: FeedbackInstructionData {
        email: ?string,
        mobile: ?string,
        webhook: ?string
    },
    
    // 🎁 Rider/Message
    rider: RiderInstructionData {
        message: ?string,
        url: ?string
    },
    
    // 🎫 Voucher Generation
    count: int,
    prefix: ?string,
    mask: ?string,
    ttl: ?CarbonInterval
}
```

---

## 🔄 The Voucher Lifecycle

### **Phase 1: Generation (Issuer → Voucher)**

```
┌─────────────────────────────────────────────────────────┐
│ 1. User fills Generate Form                             │
│    - Amount: 500 PHP                                    │
│    - Count: 10 vouchers                                 │
│    - Required Inputs: [NAME, ADDRESS, SIGNATURE]        │
│    - Feedback: email@example.com                        │
│    - TTL: 24 hours                                      │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. POST /vouchers/generate                              │
│    VoucherInstructionDataRequest validates input        │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. GenerateVouchers::run($instructions)                │
│    - Uses FrittenKeeZ\Vouchers facade                   │
│    - Returns LBHurtado\Voucher\Models\Voucher (extends) │
│    - Stores instructions in metadata                    │
│    - Sets owner, prefix, mask, TTL                      │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. VouchersGenerated Event Dispatched                   │
│    - Vouchers created with embedded instructions        │
│    - Metadata: ['instructions' => $instructions]        │
└─────────────────────────────────────────────────────────┘
```

**Key Code:**
```php
// GenerateController.php (x-change/app/Http/Controllers/Voucher/GenerateController.php)
public function store(VoucherInstructionDataRequest $request)
{
    $instructions = $request->getData(); // VoucherInstructionsData
    $vouchers = GenerateVouchers::run($instructions);
}

// GenerateVouchers.php (lbhurtado/voucher/src/Actions/GenerateVouchers.php)
use FrittenKeeZ\Vouchers\Facades\Vouchers; // Uses FrittenKeeZ facade

$vouchers = Vouchers::withPrefix($prefix)
    ->withMask($mask)
    ->withMetadata(['instructions' => $instructions->toCleanArray()]) // 🔑 KEY!
    ->withExpireTimeIn($ttl)
    ->withOwner(auth()->user())
    ->create($count);

// Returns: Collection<LBHurtado\Voucher\Models\Voucher>
// Note: LBHurtado\Voucher\Models\Voucher extends FrittenKeeZ\Vouchers\Models\Voucher
```

---

### **Phase 2: Redemption (Redeemer → Cash)**

The redemption flow is **dynamically generated** based on the instructions!

```
┌─────────────────────────────────────────────────────────┐
│ 1. User enters voucher code                             │
│    GET /redeem/{voucher}/wallet                         │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Collect Bank Account                                 │
│    - Mobile (validated via Laravel Phone)               │
│    - Bank Code + Account Number                         │
│    POST /redeem/{voucher}/wallet                        │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. RedeemPluginSelector::fromVoucher($voucher)         │
│    📊 Analyzes: $voucher->instructions->inputs->fields  │
│    📊 Returns: ['inputs', 'signature'] (dynamic!)       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Plugin Flow (Dynamic Wizard)                         │
│                                                          │
│    IF voucher requires [NAME, EMAIL]:                   │
│      → GET /redeem/{voucher}/inputs                     │
│      → Render Redeem/Inputs.vue with NAME, EMAIL fields │
│      → POST /redeem/{voucher}/inputs/store              │
│                                                          │
│    IF voucher requires [SIGNATURE]:                     │
│      → GET /redeem/{voucher}/signature                  │
│      → Render Redeem/Signature.vue                      │
│      → POST /redeem/{voucher}/signature/store           │
│                                                          │
│    IF voucher requires [OTP]:                           │
│      → Generate TOTP from voucher code                  │
│      → Validate OTP in plugin validation                │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Finalize & Confirm                                   │
│    GET /redeem/{voucher}/finalize                       │
│    - Shows summary of all collected inputs              │
│    - User confirms                                       │
│    POST /redeem/{voucher}/confirm                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Voucher Redeemed                                     │
│    - Inputs attached to voucher                         │
│    - Cash disbursed via payment gateway                 │
│    - Feedback sent (email/SMS/webhook)                  │
│    - Success page with rider message                    │
└─────────────────────────────────────────────────────────┘
```

---

## 🧩 The Plugin System (Dynamic UX)

### **Plugin Configuration** (`config/x-change.php`)

```php
'plugins' => [
    'inputs' => [
        'enabled' => true,
        'page' => 'Redeem/Inputs',
        'session_key' => 'inputs',
        'fields' => [
            VoucherInputField::EMAIL,
            VoucherInputField::NAME,
            VoucherInputField::ADDRESS,
            VoucherInputField::BIRTH_DATE,
            VoucherInputField::GROSS_MONTHLY_INCOME,
            VoucherInputField::LOCATION,
            VoucherInputField::REFERENCE_CODE,
            VoucherInputField::OTP,
        ],
    ],
    
    'signature' => [
        'enabled' => true,
        'page' => 'Redeem/Signature',
        'session_key' => 'signature',
        'fields' => [VoucherInputField::SIGNATURE],
    ],
]
```

### **Dynamic Plugin Selection** (The Magic!)

```php
// RedeemPluginSelector::fromVoucher($voucher)

// Step 1: Get required fields from voucher
$voucherFieldKeys = $voucher->instructions->inputs->fields; 
// Example: [EMAIL, NAME, SIGNATURE]

// Step 2: Check each plugin for field intersection
foreach ($plugins as $plugin => $config) {
    $pluginFields = $config['fields']; // [EMAIL, NAME, ADDRESS, ...]
    
    $intersection = array_intersect($pluginFields, $voucherFieldKeys);
    
    if ($intersection) {
        $selectedPlugins[] = $plugin; // "inputs" plugin selected
    }
}

// Result: ['inputs', 'signature']
```

### **Dynamic Form Rendering**

```php
// RedeemWizardController::plugin($voucher, 'inputs')

// Step 1: Get plugin fields
$pluginFields = RedeemPluginMap::fieldsFor('inputs'); 
// [EMAIL, NAME, ADDRESS, BIRTH_DATE, GMI, LOCATION, REF_CODE, OTP]

// Step 2: Intersect with voucher requirements
$voucherFields = $voucher->instructions->inputs->fields;
// [EMAIL, NAME, SIGNATURE]

$requestedFields = array_intersect($pluginFields, $voucherFields);
// Result: [EMAIL, NAME] (only what's needed!)

// Step 3: Build dynamic validation rules
$rules = InputRuleBuilder::from($voucher->instructions->inputs);
// Generates rules based on required fields

// Step 4: Render page with only required fields
return Inertia::render('Redeem/Inputs', [
    'requestedFields' => $requestedFields, // [EMAIL, NAME]
    'default_values' => $defaultValues,
]);
```

---

## 🎨 Frontend Implications

The Vue components must be **data-driven**:

```vue
<!-- Redeem/Inputs.vue -->
<template>
  <form @submit="handleSubmit">
    <!-- Dynamically render based on requestedFields -->
    <div v-for="field in requestedFields" :key="field">
      
      <InputField 
        v-if="field === 'name'"
        v-model="form.name"
        label="Full Name"
      />
      
      <InputField 
        v-if="field === 'email'"
        v-model="form.email"
        type="email"
        label="Email Address"
      />
      
      <InputField 
        v-if="field === 'address'"
        v-model="form.address"
        label="Complete Address"
      />
      
      <InputField 
        v-if="field === 'birth_date'"
        v-model="form.birth_date"
        type="date"
        label="Birth Date"
      />
      
      <OTPInput 
        v-if="field === 'otp'"
        v-model="form.otp"
      />
      
    </div>
  </form>
</template>
```

---

## 📡 Required API Endpoints for Phase 2

### **Generation Endpoints**

| Method | Endpoint | Controller | Action |
|--------|----------|------------|--------|
| GET | `/vouchers/create` | `VoucherController@create` | Show generation form |
| POST | `/vouchers` | `VoucherController@store` | Generate vouchers |
| GET | `/vouchers` | `VoucherController@index` | List user's vouchers |
| GET | `/vouchers/{id}` | `VoucherController@show` | Show voucher details |

### **Redemption Endpoints**

| Method | Endpoint | Controller | Action |
|--------|----------|------------|--------|
| GET | `/redeem/{voucher}` | `RedeemController@start` | Start redemption |
| GET | `/redeem/{voucher}/wallet` | `RedeemWizardController@wallet` | Collect bank account |
| POST | `/redeem/{voucher}/wallet` | `RedeemWizardController@storeWallet` | Save bank account |
| GET | `/redeem/{voucher}/{plugin}` | `RedeemWizardController@plugin` | Show plugin form |
| POST | `/redeem/{voucher}/{plugin}` | `RedeemWizardController@storePlugin` | Save plugin inputs |
| GET | `/redeem/{voucher}/finalize` | `RedeemWizardController@finalize` | Review & confirm |
| POST | `/redeem/{voucher}/confirm` | `RedeemController@confirm` | Execute redemption |
| GET | `/redeem/{voucher}/success` | `RedeemController@success` | Success page |

---

## 🔑 Key Takeaways

1. **VoucherInstructionsData is the blueprint** - It defines:
   - What inputs to collect
   - Which plugins to show
   - What validation to apply
   - Where to send feedback
   - What message to show

2. **The UX is dynamically generated** - No hardcoded forms!
   - Plugins are selected based on instructions
   - Forms only show required fields
   - Validation rules are built from instructions

3. **Instructions are metadata** - Stored in voucher:
   ```php
   $voucher->metadata = [
       'instructions' => VoucherInstructionsData
   ];
   ```

4. **Plugin system is modular**:
   - Easy to add new plugins
   - Each plugin handles specific input types
   - Plugins auto-enable based on voucher needs

---

## 🚀 Next Steps for Phase 2

1. **Create Controllers** (following x-change pattern):
   - `VoucherController` (generation)
   - `RedeemWizardController` (redemption flow)
   - `RedeemController` (confirmation)

2. **Copy Support Classes**:
   - `RedeemPluginMap`
   - `RedeemPluginSelector`
   - `InputRuleBuilder`

3. **Create Form Requests**:
   - `VoucherInstructionDataRequest`
   - `WalletFormRequest`

4. **Build Vue Pages** (Phase 3):
   - `Generate.vue` (instruction builder)
   - `Redeem/Wallet.vue`
   - `Redeem/Inputs.vue` (dynamic)
   - `Redeem/Signature.vue`
   - `Redeem/Finalize.vue`
   - `Redeem/Success.vue`

---

## 💡 The Genius of This Design

This architecture allows **infinite flexibility**:

- **Low-amount voucher**: Just mobile + bank account
- **KYC voucher**: Mobile + bank + name + address + birth date
- **Survey voucher**: Mobile + bank + custom questions + signature
- **OTP-secured voucher**: All above + time-based PIN
- **Feedback voucher**: + webhook notification to partner

**All driven by VoucherInstructionsData!** 🎯
