# Voucher Component Architecture

This directory contains **reusable, composable Vue components** for voucher management, following **Domain-Driven Design (DDD)** principles by mapping directly to PHP Data Transfer Objects (DTOs).

## 📁 Directory Structure

```
voucher/
├── forms/                      # Form components (create/edit)
│   ├── CashValidationRulesForm.vue
│   ├── CashInstructionForm.vue
│   ├── InputFieldsForm.vue
│   ├── FeedbackInstructionForm.vue
│   ├── RiderInstructionForm.vue
│   ├── VoucherInstructionsForm.vue
│   └── index.ts
├── views/                      # View components (display/readonly)
│   ├── VoucherDetailsView.vue
│   ├── VoucherRedemptionView.vue
│   └── index.ts
└── README.md                   # This file
```

---

## 🏗️ Architecture Principles

### 1. **DTO Mapping**
Each component maps to a PHP DTO in `packages/voucher/src/Data/`:

| Component | PHP DTO |
|-----------|---------|
| `CashValidationRulesForm` | `CashValidationRulesData.php` |
| `CashInstructionForm` | `CashInstructionData.php` |
| `InputFieldsForm` | `InputFieldsData.php` |
| `FeedbackInstructionForm` | `FeedbackInstructionData.php` |
| `RiderInstructionForm` | `RiderInstructionData.php` |
| `VoucherInstructionsForm` | `VoucherInstructionsData.php` |

### 2. **Composition Pattern**
Components are built from atomic → composite → page level:

```
Atomic Components → Composite Components → Pages
     ├─────────────────┴──────────────────┘
     (Pure, Single Responsibility)
```

### 3. **Type Safety**
All components use TypeScript interfaces from `@/types/voucher.d.ts` for full type checking.

---

## 📦 Form Components (`forms/`)

Form components are **editable** and support v-model binding.

### Atomic Components

#### **CashValidationRulesForm.vue**
Maps to `CashValidationRulesData.php`

**Purpose**: Configure voucher validation rules (secret, mobile, location).

**Props**:
```typescript
{
  modelValue: CashValidation;
  validationErrors?: Record<string, string>;
  readonly?: boolean;
}
```

**Fields**:
- Secret code
- Mobile number (PH format)
- Country code (ISO 3166-1 alpha-2)
- Location coordinates
- Radius (m/km format)

**Usage**:
```vue
<CashValidationRulesForm
  v-model="validation"
  :validation-errors="errors"
/>
```

---

#### **CashInstructionForm.vue**
Maps to `CashInstructionData.php`

**Purpose**: Configure cash amount, currency, and validation rules.

**Props**:
```typescript
{
  modelValue: CashInstruction;
  validationErrors?: Record<string, string>;
  readonly?: boolean;
  showValidationRules?: boolean;
}
```

**Features**:
- Amount input (numeric, min: 0)
- Currency input (3-letter code)
- **Collapsible** validation rules section
- Nested `CashValidationRulesForm`

**Usage**:
```vue
<CashInstructionForm
  v-model="cashInstruction"
  :validation-errors="errors"
/>
```

---

#### **InputFieldsForm.vue**
Maps to `InputFieldsData.php`

**Purpose**: Multi-select input fields for redemption.

**Props**:
```typescript
{
  modelValue: InputFields;
  inputFieldOptions: VoucherInputFieldOption[];
  validationErrors?: Record<string, string>;
  readonly?: boolean;
}
```

**Features**:
- Checkbox group for input fields
- Field counter
- Options: name, email, address, selfie, signature, location, etc.

**Usage**:
```vue
<InputFieldsForm
  v-model="inputFields"
  :input-field-options="options"
/>
```

---

#### **FeedbackInstructionForm.vue**
Maps to `FeedbackInstructionData.php`

**Purpose**: Configure notification channels (email, SMS, webhook).

**Props**:
```typescript
{
  modelValue: FeedbackInstruction;
  validationErrors?: Record<string, string>;
  readonly?: boolean;
}
```

**Fields**:
- Email address
- Mobile number (PH format)
- Webhook URL

**Usage**:
```vue
<FeedbackInstructionForm
  v-model="feedback"
  :validation-errors="errors"
/>
```

---

#### **RiderInstructionForm.vue**
Maps to `RiderInstructionData.php`

**Purpose**: Add terms, conditions, or additional information.

**Props**:
```typescript
{
  modelValue: RiderInstruction;
  validationErrors?: Record<string, string>;
  readonly?: boolean;
}
```

**Fields**:
- Message textarea (max: 4096 chars)
- URL input (max: 2048 chars)

**Usage**:
```vue
<RiderInstructionForm
  v-model="rider"
  :validation-errors="errors"
/>
```

---

### Composite Component

#### **VoucherInstructionsForm.vue**
Maps to `VoucherInstructionsData.php`

**Purpose**: Complete voucher instructions form using all atomic components.

**Props**:
```typescript
{
  modelValue: {
    amount: number;
    count: number;
    prefix: string;
    mask: string;
    ttlDays: number | null;
    selectedInputFields: string[];
    validationSecret: string;
    validationMobile: string;
    feedbackEmail: string;
    feedbackMobile: string;
    feedbackWebhook: string;
    riderMessage: string;
    riderUrl: string;
  };
  inputFieldOptions: VoucherInputFieldOption[];
  validationErrors?: Record<string, string>;
  showCountField?: boolean;
  showJsonPreview?: boolean;
  readonly?: boolean;
}
```

**Structure**:
```vue
<VoucherInstructionsForm>
  ├── Basic Settings (inline)
  ├── CashInstructionForm
  ├── InputFieldsForm
  ├── FeedbackInstructionForm
  ├── RiderInstructionForm
  └── JSON Preview (optional)
</VoucherInstructionsForm>
```

**Usage**:
```vue
<VoucherInstructionsForm
  v-model="formData"
  :input-field-options="options"
  :validation-errors="errors"
  :readonly="true"
  :show-count-field="false"
  :show-json-preview="true"
/>
```

**Data Transformation**:
The component handles transformation between flat structure (API) and nested structure (atomic components) using computed properties with getters/setters.

---

## 👁️ View Components (`views/`)

View components are **read-only** display components.

#### **VoucherDetailsView.vue**

**Purpose**: Display basic voucher information with copy functionality.

**Props**:
```typescript
{
  voucher: {
    code: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    starts_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
  }
}
```

**Features**:
- Voucher code with copy button
- Redemption link with copy button (conditional)
- Formatted dates and currency
- Visual feedback on copy

**Usage**:
```vue
<VoucherDetailsView :voucher="voucher" />
```

---

#### **VoucherRedemptionView.vue**

**Purpose**: Display redemption-specific information.

**Props**:
```typescript
{
  redemption: {
    name?: string;
    email?: string;
    address?: string;
    selfie?: string;
    signature?: string;
    location?: string;
    [key: string]: any;
  }
}
```

**Features**:
- Selfie image display
- Location map (Mapbox integration)
- Signature image display
- Additional redemption inputs
- Automatic JSON parsing for location data

**Usage**:
```vue
<VoucherRedemptionView
  v-if="voucher.is_redeemed && redemption"
  :redemption="redemption"
/>
```

---

## 🎯 Usage Examples

### Example 1: Generate Vouchers Page
```vue
<script setup lang="ts">
import { VoucherInstructionsForm } from '@/components/voucher/forms';

const formData = ref({
  amount: 100,
  count: 5,
  // ... other fields
});
</script>

<template>
  <VoucherInstructionsForm
    v-model="formData"
    :input-field-options="options"
    :show-json-preview="true"
  />
</template>
```

### Example 2: Create Campaign
```vue
<script setup lang="ts">
import { VoucherInstructionsForm } from '@/components/voucher/forms';

const instructionsFormData = ref({ /* ... */ });
</script>

<template>
  <VoucherInstructionsForm
    v-model="instructionsFormData"
    :input-field-options="options"
    :show-count-field="false"
  />
</template>
```

### Example 3: Voucher Show Page
```vue
<script setup lang="ts">
import { VoucherDetailsView, VoucherRedemptionView } from '@/components/voucher/views';
import { VoucherInstructionsForm } from '@/components/voucher/forms';
</script>

<template>
  <!-- Details Tab -->
  <VoucherDetailsView :voucher="voucher" />

  <!-- Instructions Tab -->
  <VoucherInstructionsForm
    v-model="instructionsFormData"
    :readonly="true"
    :show-count-field="false"
  />

  <!-- Redemption Section -->
  <VoucherRedemptionView
    v-if="voucher.is_redeemed && redemption"
    :redemption="redemption"
  />
</template>
```

### Example 4: Using Individual Atomic Components
```vue
<script setup lang="ts">
import { CashInstructionForm, FeedbackInstructionForm } from '@/components/voucher/forms';

const cash = ref({ amount: 100, currency: 'PHP', validation: {} });
const feedback = ref({ email: '', mobile: '', webhook: '' });
</script>

<template>
  <CashInstructionForm v-model="cash" />
  <FeedbackInstructionForm v-model="feedback" />
</template>
```

---

## 🔄 Component Reusability

### Where Components Can Be Used:

| Component | Use Cases |
|-----------|-----------|
| `VoucherInstructionsForm` | Generate Vouchers, Create Campaign, Edit Campaign, Voucher Show (readonly) |
| `VoucherDetailsView` | Voucher Show, Preview Modals, Voucher List, PDF/Email Receipts |
| `VoucherRedemptionView` | Voucher Show, Redemption Confirmation, Admin Dashboard |
| `CashInstructionForm` | Standalone cash configuration, Quick voucher creation |
| `InputFieldsForm` | Campaign settings, Global input field configuration |
| `FeedbackInstructionForm` | Global notification settings, Per-campaign configuration |

---

## ✅ Benefits

### 1. **Maintainability**
- Single source of truth for each data structure
- Changes in PHP DTOs reflected in components
- Easy to update validation rules

### 2. **Reusability**
- Use atomic components independently
- Compose into different forms
- Consistent UI/UX across app

### 3. **Type Safety**
- Full TypeScript support
- Compile-time error checking
- IntelliSense support

### 4. **Testability**
- Each component isolated
- Easy to unit test
- Mock data simple to create

### 5. **Scalability**
- Easy to add new components
- Follow same pattern
- Predictable structure

---

## 🚀 Adding New Components

To add a new component following this pattern:

1. **Create PHP DTO** in `packages/voucher/src/Data/`
2. **Add TypeScript interface** in `resources/js/types/voucher.d.ts`
3. **Create Vue component** in appropriate directory (`forms/` or `views/`)
4. **Export from index.ts**
5. **Update this README**

### Template for New Form Component:
```vue
<script setup lang="ts">
import { computed } from 'vue';
import type { YourDataType } from '@/types/voucher';

interface Props {
    modelValue: YourDataType;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: YourDataType];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Your Component Title</CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Your fields here -->
        </CardContent>
    </Card>
</template>
```

---

## 📚 Related Documentation

- **PHP DTOs**: `packages/voucher/src/Data/`
- **TypeScript Types**: `resources/js/types/voucher.d.ts`
- **WARP.md**: Project-wide development guide
- **API Documentation**: See `packages/voucher/README.md`

---

## 🤝 Contributing

When modifying these components:

1. ✅ Maintain backward compatibility
2. ✅ Update TypeScript interfaces
3. ✅ Run `npm run build` to verify
4. ✅ Test with existing pages (Show, Create Campaign)
5. ✅ Update this README if adding/removing components

---

**Last Updated**: 2025-11-10
**Maintained By**: Development Team
