/**
 * VoucherRedemptionView - Display component for redemption information
 * 
 * Read-only component that displays redemption-specific data including
 * selfie, signature, location map, and additional collected inputs.
 * 
 * @component
 * @example
 * <VoucherRedemptionView
 *   v-if="voucher.is_redeemed && redemption"
 *   :redemption="redemption"
 * />
 */
<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface RedemptionData {
    name?: string;
    email?: string;
    address?: string;
    selfie?: string;
    signature?: string;
    location?: string;
    [key: string]: any;
}

interface LocationData {
    latitude: number;
    longitude: number;
    address?: {
        formatted?: string;
    };
}

interface Props {
    redemption: RedemptionData;
}

const props = defineProps<Props>();

const locationData = computed<LocationData | null>(() => {
    if (!props.redemption?.location) return null;
    try {
        return JSON.parse(props.redemption.location);
    } catch {
        return null;
    }
});

const staticMapUrl = computed(() => {
    if (!locationData.value) return '';
    
    const { latitude, longitude } = locationData.value;
    const zoom = 16;
    const size = 600;
    const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN || '';
    
    if (mapboxToken && mapboxToken !== 'your_actual_token_here') {
        return `https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+ff0000(${longitude},${latitude})/${longitude},${latitude},${zoom},0/${size}x300@2x?access_token=${mapboxToken}`;
    }
    
    return '';
});

const hasAdditionalInputs = computed(() => {
    return Object.keys(props.redemption).some(key => !['selfie', 'signature', 'location'].includes(key));
});
</script>

<template>
    <div class="space-y-6">
        <!-- Selfie -->
        <Card v-if="redemption.selfie">
            <CardHeader>
                <CardTitle>Redeemer Selfie</CardTitle>
                <CardDescription>Photo captured during redemption</CardDescription>
            </CardHeader>
            <CardContent>
                <img
                    :src="redemption.selfie"
                    alt="Redeemer selfie"
                    class="w-full max-w-md rounded-lg border"
                />
            </CardContent>
        </Card>

        <!-- Location Map -->
        <Card v-if="locationData">
            <CardHeader>
                <CardTitle>Redemption Location</CardTitle>
                <CardDescription>Where the voucher was redeemed</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <img
                    v-if="staticMapUrl"
                    :src="staticMapUrl"
                    alt="Redemption location map"
                    class="w-full rounded-lg border"
                />
                <div class="text-sm">
                    <div class="font-medium">{{ locationData.address?.formatted }}</div>
                    <div class="text-xs text-muted-foreground mt-1">
                        {{ locationData.latitude.toFixed(6) }}, {{ locationData.longitude.toFixed(6) }}
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Signature -->
        <Card v-if="redemption.signature">
            <CardHeader>
                <CardTitle>Signature</CardTitle>
                <CardDescription>Digital signature captured during redemption</CardDescription>
            </CardHeader>
            <CardContent>
                <img
                    :src="redemption.signature"
                    alt="Signature"
                    class="w-full max-w-md rounded-lg border bg-white"
                />
            </CardContent>
        </Card>

        <!-- Other Redemption Inputs -->
        <Card v-if="hasAdditionalInputs">
            <CardHeader>
                <CardTitle>Additional Information</CardTitle>
                <CardDescription>Information collected during redemption</CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div v-for="(value, key) in redemption" :key="key">
                        <template v-if="!['selfie', 'signature', 'location'].includes(key)">
                            <dt class="text-sm font-medium text-muted-foreground capitalize">
                                {{ key.replace(/_/g, ' ') }}
                            </dt>
                            <dd class="mt-1 text-sm">{{ value }}</dd>
                        </template>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
