# Voucher QR Code Component Reuse - Analysis & Plan

## Current State Analysis

### Existing QR Components (Wallet/QR Page)

#### 1. **QrDisplay Component** (`resources/js/components/shared/QrDisplay.vue`)
- ✅ **Already reusable** - in `shared/` directory
- **Props**: `qrCode` (string | null), `loading` (boolean), `error` (string | null)
- **Features**:
  - Loading state with spinner
  - Error state with alert icon
  - Empty state
  - Auto-detects data URL vs base64
  - Responsive image display
- **Status**: **READY TO REUSE** ✅

#### 2. **QrSharePanel Component** (`resources/js/components/QrSharePanel.vue`)
- ⚠️ **Partially reusable** - needs minor adaptation
- **Props**: `qrData` (QrCodeData | null)
- **Features**:
  - Copy QR Link
  - Copy QR Image
  - Download QR
  - Share via Email
  - Share via SMS
  - Share via WhatsApp
  - Native Share (mobile)
- **Dependencies**: `useQrShare` composable
- **Current limitation**: Uses wallet-specific share messages ("load my wallet")
- **Status**: **NEEDS CUSTOMIZATION** ⚠️

#### 3. **QR Generation Composables**

**`useQrGeneration.ts`**:
- Purpose: Fetches payment gateway QR codes from backend
- Not applicable for voucher redemption (generates URLs, not payment QRs)
- **Status**: **NOT NEEDED** ❌

**`useQrCode.ts`**:
- Purpose: Client-side QR code generation
- **Status**: **POTENTIALLY USEFUL** ✓

**`useQrShare.ts`**:
- Purpose: Sharing functionality (copy, download, email, SMS, WhatsApp, native)
- **Status**: **READY TO REUSE** ✅

---

## Voucher QR Requirements

### What We Need

1. **Generate QR code** for redemption link: `http://redeem-x.test/redeem?code={CODE}`
2. **Display QR code** with loading/error states
3. **Share options**:
   - Copy redemption link
   - Copy QR image
   - Download QR image
   - Share via Email/SMS/WhatsApp
   - Native share (mobile)
4. **Custom share messages**: "Redeem your voucher: {link}" (not "load my wallet")

---

## Implementation Plan

### Option A: Client-Side QR Generation (Recommended)

Generate QR codes in the browser using a library like `qrcode` (lighter, no backend needed).

#### Steps:

**1. Install QR Code Library**
```bash
npm install qrcode
npm install -D @types/qrcode
```

**2. Create `useVoucherQr` Composable**
```typescript
// resources/js/composables/useVoucherQr.ts
import { ref } from 'vue';
import QRCode from 'qrcode';

export interface VoucherQrData {
    qr_code: string;       // Base64 data URL
    redemption_url: string; // Full redemption URL
    voucher_code: string;   // Just the code
}

export function useVoucherQr(voucherCode: string) {
    const qrData = ref<VoucherQrData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const generateQr = async () => {
        loading.value = true;
        error.value = null;

        try {
            const redemptionUrl = `${window.location.origin}/redeem?code=${voucherCode}`;
            
            // Generate QR code as data URL
            const qrCode = await QRCode.toDataURL(redemptionUrl, {
                width: 300,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF',
                },
            });

            qrData.value = {
                qr_code: qrCode,
                redemption_url: redemptionUrl,
                voucher_code: voucherCode,
            };
        } catch (err: any) {
            error.value = err.message || 'Failed to generate QR code';
        } finally {
            loading.value = false;
        }
    };

    return {
        qrData,
        loading,
        error,
        generateQr,
    };
}
```

**3. Create `VoucherQrSharePanel` Component**

Wrapper around `QrSharePanel` with voucher-specific messages:

```vue
<!-- resources/js/components/voucher/VoucherQrSharePanel.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import QrSharePanel from '@/components/QrSharePanel.vue';
import type { VoucherQrData } from '@/composables/useVoucherQr';

interface Props {
    qrData: VoucherQrData | null;
    amount?: number;
    currency?: string;
}

const props = defineProps<Props>();

// Transform voucher QR data to QrSharePanel format
const shareData = computed(() => {
    if (!props.qrData) return null;
    
    return {
        qr_code: props.qrData.qr_code,
        qr_url: props.qrData.redemption_url,
        shareable_url: props.qrData.redemption_url,
        amount: props.amount, // Optional: for share message
    };
});
</script>

<template>
    <QrSharePanel :qr-data="shareData" />
</template>
```

**4. Update Voucher Show Page**

Add QR section to `resources/js/pages/vouchers/Show.vue`:

```vue
<script setup lang="ts">
import { onMounted } from 'vue';
import QrDisplay from '@/components/shared/QrDisplay.vue';
import VoucherQrSharePanel from '@/components/voucher/VoucherQrSharePanel.vue';
import { useVoucherQr } from '@/composables/useVoucherQr';

const props = defineProps<Props>();

// Generate QR code for voucher
const { qrData, loading, error, generateQr } = useVoucherQr(props.voucher.code);

onMounted(() => {
    generateQr();
});
</script>

<template>
    <!-- ... existing content ... -->
    
    <!-- NEW: QR Code Section (only show for unredeemed, non-expired vouchers) -->
    <div v-if="!voucher.is_redeemed && !voucher.is_expired" class="grid gap-6 md:grid-cols-2">
        <!-- QR Display -->
        <Card>
            <CardHeader>
                <CardTitle>Redemption QR Code</CardTitle>
                <CardDescription>
                    Scan this QR code to redeem the voucher
                </CardDescription>
            </CardHeader>
            <CardContent class="flex justify-center">
                <div class="w-full max-w-sm">
                    <QrDisplay
                        :qr-code="qrData?.qr_code ?? null"
                        :loading="loading"
                        :error="error"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Share Panel -->
        <VoucherQrSharePanel
            :qr-data="qrData"
            :amount="voucher.amount"
            :currency="voucher.currency"
        />
    </div>
</template>
```

---

### Option B: Backend QR Generation

Generate QR codes server-side (more backend load, but centralized).

#### Steps:

**1. Install Server-Side QR Library**
```bash
composer require simplesoftwareio/simple-qrcode
```

**2. Add QR Endpoint**
```php
// routes/api.php or routes/web.php
Route::get('/vouchers/{voucher:code}/qr', [VoucherController::class, 'qr'])
    ->name('vouchers.qr');
```

**3. Controller Method**
```php
public function qr(Voucher $voucher): JsonResponse
{
    $redemptionUrl = route('redeem.start', ['code' => $voucher->code]);
    
    $qrCode = QrCode::format('png')
        ->size(300)
        ->margin(2)
        ->generate($redemptionUrl);
    
    return response()->json([
        'qr_code' => base64_encode($qrCode),
        'redemption_url' => $redemptionUrl,
        'voucher_code' => $voucher->code,
    ]);
}
```

**4. Frontend Composable**
```typescript
// Fetch QR from backend
const fetchQr = async () => {
    const response = await axios.get(`/api/v1/vouchers/${voucherCode}/qr`);
    qrData.value = response.data;
};
```

---

## Recommendation

**Use Option A (Client-Side Generation)** because:

1. ✅ **No backend changes** - faster implementation
2. ✅ **Lower server load** - QR generation happens in browser
3. ✅ **Instant generation** - no API latency
4. ✅ **Offline capable** - works even if backend is slow
5. ✅ **Simpler architecture** - one less API endpoint to maintain

The `qrcode` npm package is lightweight (20KB gzipped) and battle-tested.

---

## Components Summary

### Ready to Reuse (No Changes)
- ✅ `QrDisplay.vue` - Drop-in replacement
- ✅ `useQrShare.ts` - All sharing functions work as-is

### Need Minor Wrapper
- ⚠️ `QrSharePanel.vue` - Create `VoucherQrSharePanel.vue` wrapper for custom messages

### Need to Create
- ❌ `useVoucherQr.ts` - New composable for client-side QR generation
- ❌ `VoucherQrSharePanel.vue` - Wrapper component

---

## Share Message Customization

The share messages should be voucher-specific:

```typescript
const shareMessage = computed(() => {
    const amount = props.amount;
    const code = props.qrData?.voucher_code;
    
    if (amount) {
        return `Redeem your ₱${amount} voucher (${code}): ${shareUrl.value}`;
    }
    return `Redeem your voucher (${code}): ${shareUrl.value}`;
});
```

---

## Implementation Checklist

- [ ] Install `qrcode` npm package
- [ ] Create `useVoucherQr.ts` composable
- [ ] Create `VoucherQrSharePanel.vue` wrapper component
- [ ] Update `vouchers/Show.vue` to include QR section
- [ ] Test QR generation
- [ ] Test sharing functionality
- [ ] Test on mobile devices (native share)
- [ ] Add loading/error states
- [ ] Style QR section to match existing design

---

## Benefits of This Approach

1. **Code Reuse**: 80% of QR functionality is already built
2. **Consistent UX**: Same sharing experience across wallet and vouchers
3. **Maintainability**: Changes to sharing logic benefit both features
4. **Performance**: Client-side generation is instant
5. **Mobile-Friendly**: Native share API works on both features
