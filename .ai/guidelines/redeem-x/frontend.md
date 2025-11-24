# Frontend Patterns

## Overview
Redeem-X uses Vue 3 + TypeScript + Inertia.js for the frontend, styled with Tailwind CSS v4. This document covers component patterns, Wayfinder integration, state management, and UI conventions.

## Tech Stack
- **Framework**: Vue 3 with Composition API
- **Type Safety**: TypeScript
- **Server Communication**: Inertia.js v2
- **Routing**: Laravel Wayfinder (type-safe route generation)
- **Styling**: Tailwind CSS v4
- **UI Components**: reka-ui (headless components)
- **Build Tool**: Vite

## Reusable Components

### VoucherInstructionsForm.vue
Shared form component for configuring voucher instructions. Used across multiple features for consistency.

**Usage Locations:**
- Generate Vouchers page
- Create Campaign page
- Edit Campaign page
- View Voucher page (readonly mode)

**Props:**
```typescript
interface Props {
  modelValue: VoucherInstructionsData
  inputFieldOptions: VoucherInputField[]
  validationErrors?: Record<string, string[]>
  showCountField?: boolean
  showJsonPreview?: boolean
  readonly?: boolean
}
```

**Example Usage:**
```vue
<script setup lang="ts">
import VoucherInstructionsForm from '@/components/VoucherInstructionsForm.vue'
import { ref } from 'vue'

const instructions = ref({
  cash: { amount: 1000, currency: 'PHP' },
  inputs: ['MOBILE', 'EMAIL'],
  validations: {},
  feedback: { email: true, sms: false },
})
</script>

<template>
  <VoucherInstructionsForm
    v-model="instructions"
    :input-field-options="['MOBILE', 'EMAIL', 'LOCATION']"
    :show-count-field="true"
    :show-json-preview="true"
  />
</template>
```

**Key Features:**
- **v-model binding** for reactive form data
- **Readonly mode** for displaying (not editing) instructions
- **Basic Settings** section: cash amount, currency, TTL
- **Input Fields** section: select required fields
- **Validation Rules** section: configure field validations
- **Feedback Channels** section: email, SMS, webhook
- **Rider Information** section: additional display text
- **JSON Preview** (optional): live JSON representation

### RedeemWidget.vue
Embeddable voucher redemption widget for iframe integration.

**Configuration:**
- Configurable via `config/redeem.php` or environment variables
- Elements can be shown/hidden: logo, app name, label, title, description
- Customizable text: title, label, placeholder, button text

**Features:**
- Single input field for voucher code
- Submit button
- Error handling and display
- Success/failure states
- Loading indicators

**Iframe Usage:**
```html
<iframe 
  src="https://yourapp.test/widget/redeem" 
  width="400" 
  height="300"
  frameborder="0"
></iframe>
```

### Voucher QR Code Components

**useVoucherQr.ts Composable:**
Client-side QR code generation using `qrcode` npm package.

```typescript
import { useVoucherQr } from '@/composables/useVoucherQr'

const { qrDataUrl, isLoading, error, generateQr } = useVoucherQr()

// Generate QR for voucher
await generateQr(voucher.code)
```

**QrDisplay.vue:**
Displays QR codes with loading and error states.

```vue
<QrDisplay 
  :qr-data-url="qrDataUrl"
  :is-loading="isLoading"
  :error="error"
/>
```

**QrSharePanel.vue:**
Generic sharing panel with multiple options:
- Copy to clipboard
- Download image
- Email
- SMS
- WhatsApp
- Native share (mobile)

**VoucherQrSharePanel.vue:**
Voucher-specific wrapper for QR sharing.

```vue
<VoucherQrSharePanel 
  :voucher="voucher"
  :qr-data-url="qrDataUrl"
/>
```

**Usage Context:**
- Voucher Show page displays QR for unredeemed, non-expired vouchers
- QR encodes redemption URL: `http://domain/redeem?code={CODE}`
- Instant generation (client-side, no API latency)
- Reuses 80% of wallet QR components for consistency

## Wayfinder Integration

### Type-Safe Routes
Laravel Wayfinder generates TypeScript route definitions from Laravel controllers.

**Generated Files Location:**
```
resources/js/actions/
  └── App/Http/Controllers/
      ├── VoucherController.ts
      ├── CampaignController.ts
      ├── Settings/
      │   └── ProfileController.ts
      └── ...
```

**Import Pattern:**
```typescript
// Named imports (tree-shakable)
import { index, store, show, update, destroy } from '@/actions/App/Http/Controllers/VoucherController'
```

### Using Routes with Inertia

**Navigation:**
```typescript
import { router } from '@inertiajs/vue3'
import { show } from '@/actions/App/Http/Controllers/VoucherController'

// Navigate to route
router.visit(show.url(voucher.id))
```

**HTTP Methods:**
```typescript
import { update } from '@/actions/App/Http/Controllers/VoucherController'

// Specific HTTP method
update.patch(voucher.id, { data })
```

**Form Integration:**
```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3'
import { store } from '@/actions/App/Http/Controllers/VoucherController'
</script>

<template>
  <Form v-bind="store.form()">
    <input name="amount" type="number" />
    <button type="submit">Generate</button>
  </Form>
</template>
```

**Query Parameters:**
```typescript
import { index } from '@/actions/App/Http/Controllers/VoucherController'

// With query params
router.visit(index.url(), {
  data: { page: 2, filter: 'active' }
})
```

## Inertia.js Patterns

### Page Components
Page components live in `resources/js/pages/` and correspond to Inertia responses from controllers.

**Structure:**
```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

interface Props {
  vouchers: Voucher[]
  pagination: PaginationData
}

defineProps<Props>()
</script>

<template>
  <Head title="Vouchers" />
  
  <div>
    <!-- Page content -->
  </div>
</template>
```

### Form Handling

**Using Form Component:**
```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3'
import { store } from '@/actions/App/Http/Controllers/VoucherController'
</script>

<template>
  <Form 
    v-bind="store.form()"
    #default="{ 
      errors, 
      hasErrors, 
      processing, 
      wasSuccessful,
      reset 
    }"
  >
    <input name="amount" type="number" />
    
    <div v-if="errors.amount" class="text-red-500">
      {{ errors.amount }}
    </div>
    
    <button type="submit" :disabled="processing">
      {{ processing ? 'Generating...' : 'Generate Voucher' }}
    </button>
    
    <div v-if="wasSuccessful" class="text-green-500">
      Voucher created successfully!
    </div>
  </Form>
</template>
```

**Using useForm Helper:**
```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { store } from '@/actions/App/Http/Controllers/VoucherController'

const form = useForm({
  amount: 1000,
  currency: 'PHP',
  count: 1,
})

const submit = () => {
  form.post(store.url(), {
    onSuccess: () => {
      form.reset()
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
    },
  })
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.amount" type="number" />
    
    <div v-if="form.errors.amount">
      {{ form.errors.amount }}
    </div>
    
    <button type="submit" :disabled="form.processing">
      Submit
    </button>
  </form>
</template>
```

### Deferred Props & Loading States
Inertia v2 supports deferred props for improved performance.

**Backend:**
```php
return Inertia::render('Vouchers/Show', [
    'voucher' => $voucher,
    'relatedVouchers' => Inertia::defer(fn () => 
        $voucher->related()->limit(5)->get()
    ),
]);
```

**Frontend:**
```vue
<script setup lang="ts">
interface Props {
  voucher: Voucher
  relatedVouchers?: Voucher[]
}

const props = defineProps<Props>()
</script>

<template>
  <div>
    <!-- Always available -->
    <h1>{{ voucher.code }}</h1>
    
    <!-- May be undefined initially -->
    <div v-if="relatedVouchers">
      <h2>Related Vouchers</h2>
      <div v-for="v in relatedVouchers" :key="v.id">
        {{ v.code }}
      </div>
    </div>
    <div v-else>
      <!-- Loading skeleton -->
      <div class="animate-pulse bg-gray-200 h-20"></div>
    </div>
  </div>
</template>
```

## State Management

### Composition API Patterns
Use composables for reusable stateful logic.

**Example Composable:**
```typescript
// composables/useVoucher.ts
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

export function useVoucher(voucher: Ref<Voucher>) {
  const isRedeemed = computed(() => voucher.value.redeemed_at !== null)
  const isExpired = computed(() => {
    if (!voucher.value.expires_at) return false
    return new Date(voucher.value.expires_at) < new Date()
  })
  const canRedeem = computed(() => !isRedeemed.value && !isExpired.value)
  
  const redeem = async (inputs: Record<string, any>) => {
    router.post('/redeem', {
      code: voucher.value.code,
      ...inputs,
    })
  }
  
  return {
    isRedeemed,
    isExpired,
    canRedeem,
    redeem,
  }
}
```

**Using Composable:**
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useVoucher } from '@/composables/useVoucher'

const props = defineProps<{ voucher: Voucher }>()
const voucher = ref(props.voucher)

const { isRedeemed, isExpired, canRedeem, redeem } = useVoucher(voucher)
</script>

<template>
  <div>
    <span v-if="isRedeemed" class="badge-success">Redeemed</span>
    <span v-else-if="isExpired" class="badge-danger">Expired</span>
    <button v-else-if="canRedeem" @click="redeem({})">
      Redeem
    </button>
  </div>
</template>
```

## Real-Time Updates

### Wallet Balance Updates
Use Laravel Echo for real-time wallet balance updates.

**Backend Event:**
```php
broadcast(new WalletBalanceUpdated($user, $newBalance));
```

**Frontend Listener:**
```vue
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import Echo from 'laravel-echo'

const props = defineProps<{ user: User }>()
const balance = ref(props.user.wallet.balance)

onMounted(() => {
  window.Echo.private(`user.${props.user.id}`)
    .listen('WalletBalanceUpdated', (e) => {
      balance.value = e.newBalance
    })
})

onUnmounted(() => {
  window.Echo.leave(`user.${props.user.id}`)
})
</script>

<template>
  <div>Balance: ₱{{ balance.toLocaleString() }}</div>
</template>
```

### Voucher Status Updates
```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'

const props = defineProps<{ voucher: Voucher }>()
const status = ref(props.voucher.status)

onMounted(() => {
  window.Echo.private(`voucher.${props.voucher.id}`)
    .listen('VoucherStatusChanged', (e) => {
      status.value = e.status
    })
})
</script>
```

## Component Conventions

### Props and v-model
Use `v-model` for two-way binding with proper TypeScript types.

```vue
<script setup lang="ts">
interface Props {
  modelValue: VoucherInstructions
}

interface Emits {
  (e: 'update:modelValue', value: VoucherInstructions): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const updateValue = (newValue: VoucherInstructions) => {
  emit('update:modelValue', newValue)
}
</script>

<template>
  <div>
    <input 
      :value="modelValue.cash.amount"
      @input="updateValue({ ...modelValue, cash: { ...modelValue.cash, amount: $event.target.value } })"
    />
  </div>
</template>
```

### Slot Patterns
Use named slots for flexible component composition.

```vue
<script setup lang="ts">
// ParentComponent.vue
</script>

<template>
  <Card>
    <template #header>
      <h2>Voucher Details</h2>
    </template>
    
    <template #content>
      <p>{{ voucher.code }}</p>
    </template>
    
    <template #footer>
      <button>Redeem</button>
    </template>
  </Card>
</template>
```

## Tailwind CSS Patterns

### Consistent Spacing
Use gap utilities instead of margins for spacing.

```vue
<!-- Good -->
<div class="flex gap-4">
  <div>Item 1</div>
  <div>Item 2</div>
</div>

<!-- Bad -->
<div class="flex">
  <div class="mr-4">Item 1</div>
  <div>Item 2</div>
</div>
```

### Dark Mode Support
Use `dark:` prefix for dark mode styles.

```vue
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
  Content
</div>
```

### Responsive Design
Use responsive prefixes for breakpoints.

```vue
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <!-- Responsive grid -->
</div>
```

## Performance Best Practices

### Lazy Loading Components
```typescript
import { defineAsyncComponent } from 'vue'

const HeavyComponent = defineAsyncComponent(() =>
  import('./components/HeavyComponent.vue')
)
```

### Memoization
```typescript
import { computed } from 'vue'

const expensiveComputation = computed(() => {
  // Expensive calculation
  return voucherList.value.reduce((sum, v) => sum + v.amount, 0)
})
```

### Virtual Scrolling
For large lists, use virtual scrolling libraries.

## File Organization

### Component Structure
```
resources/js/
├── components/
│   ├── ui/              # Reusable UI components
│   │   ├── Button.vue
│   │   ├── Card.vue
│   │   └── Input.vue
│   ├── voucher/         # Voucher-specific components
│   │   ├── VoucherCard.vue
│   │   ├── VoucherInstructionsForm.vue
│   │   └── VoucherQrSharePanel.vue
│   └── wallet/          # Wallet-specific components
│       └── WalletBalance.vue
├── composables/         # Reusable composition functions
│   ├── useVoucher.ts
│   ├── useVoucherQr.ts
│   └── useWallet.ts
├── layouts/             # Layout components
│   ├── app/
│   │   ├── AppHeaderLayout.vue
│   │   └── AppSidebarLayout.vue
│   └── settings/
│       └── SettingsLayout.vue
├── pages/               # Inertia page components
│   ├── Vouchers/
│   │   ├── Index.vue
│   │   ├── Show.vue
│   │   └── Generate.vue
│   └── Settings/
│       └── Campaigns.vue
└── types/               # TypeScript type definitions
    ├── models.d.ts
    └── inertia.d.ts
```

## TypeScript Best Practices

### Define Interfaces
```typescript
// types/models.d.ts
export interface Voucher {
  id: number
  code: string
  amount: number
  currency: string
  redeemed_at: string | null
  expires_at: string | null
  instructions: VoucherInstructions
}

export interface VoucherInstructions {
  cash: CashInstructions
  inputs: VoucherInputField[]
  validations: Record<string, ValidationRule[]>
  feedback: FeedbackChannels
}
```

### Use Type Guards
```typescript
function isExpired(voucher: Voucher): boolean {
  if (!voucher.expires_at) return false
  return new Date(voucher.expires_at) < new Date()
}
```
