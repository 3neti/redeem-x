<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useBrowserLocation } from '@/composables/useBrowserLocation';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle, MapPin } from 'lucide-vue-next';
import GeoPermissionAlert from '@/components/GeoPermissionAlert.vue';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const { redeemVoucher } = useRedemptionApi();
const apiKey = import.meta.env.VITE_OPENCAGE_KEY || '';
const { location, loading: geoLoading, error: geoError, getLocation } = useBrowserLocation(apiKey, 3 * 60 * 1000);

const storedData = ref<any>(null);
const geoAlertRef = ref<InstanceType<typeof GeoPermissionAlert> | null>(null);
const submitting = ref(false);
const apiError = ref<string | null>(null);

const parsedLocation = computed(() => {
    return location.value;
});

const formattedAddress = computed(() => {
    return parsedLocation.value?.address?.formatted || '';
});

const requiresSignature = computed(() => {
    return (storedData.value?.required_inputs || []).includes('signature');
});

async function fetchLocation() {
    const data = await getLocation(false);

    console.log('[Location] Fetched location data:', data);
    console.log('[Location] Formatted address:', data?.address?.formatted);

    if (geoError.value === 'PERMISSION_DENIED') {
        geoAlertRef.value?.open();
        return;
    }

    if (!data) {
        apiError.value = 'Failed to get location. Please try again.';
    } else if (!data.address?.formatted) {
        console.warn('[Location] No formatted address in response');
    }
}

const handleSubmit = async () => {
    if (!location.value) {
        apiError.value = 'Please capture your location first.';
        return;
    }

    apiError.value = null;

    // If signature is required, navigate to signature page
    if (requiresSignature.value) {
        // Update stored data with location
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                location: JSON.stringify(location.value),
            },
        };
        
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));
        
        // Navigate to signature page
        router.visit(`/redeem/${props.voucher_code}/signature`);
        return;
    }

    // Otherwise, proceed with redemption directly
    try {
        submitting.value = true;

        // Combine all inputs including location
        const inputs = {
            ...storedData.value.inputs,
            location: JSON.stringify(location.value),
        };

        const result = await redeemVoucher({
            code: props.voucher_code,
            mobile: storedData.value.mobile,
            country: storedData.value.country,
            secret: storedData.value.secret,
            bank_code: storedData.value.bank_code,
            account_number: storedData.value.account_number,
            inputs: Object.keys(inputs).length > 0 ? inputs : undefined,
        });

        // Clear stored data
        sessionStorage.removeItem(`redeem_${props.voucher_code}`);

        // Navigate to success page with result
        router.visit(`/redeem/${result.voucher.code}/success`, {
            method: 'get',
            data: {
                amount: result.voucher.amount,
                currency: result.voucher.currency,
                mobile: storedData.value.mobile,
                message: result.message,
                rider: result.rider,
            },
        });
    } catch (err: any) {
        submitting.value = false;
        apiError.value = err.response?.data?.message || 'Failed to redeem voucher. Please try again.';
        console.error('Redemption failed:', err);
    }
};

// Load stored data and initialize when component mounts
onMounted(() => {
    // Load stored wallet data from sessionStorage
    const stored = sessionStorage.getItem(`redeem_${props.voucher_code}`);
    if (!stored) {
        // No stored data, redirect back to wallet
        router.visit(`/redeem/${props.voucher_code}/wallet`);
        return;
    }

    storedData.value = JSON.parse(stored);
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Loading State -->
            <div v-if="!storedData" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <template v-else>
                <!-- Error Alert -->
                <Alert v-if="apiError" variant="destructive" class="mb-6">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ apiError }}
                    </AlertDescription>
                </Alert>

                <!-- Location Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MapPin class="h-5 w-5" />
                            Location Required
                        </CardTitle>
                        <CardDescription>
                            Please share your current location to continue with redemption
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Location Input -->
                            <div class="space-y-2">
                                <Label for="location">Current Location</Label>
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="location"
                                        :value="formattedAddress"
                                        class="flex-1"
                                        required
                                        readonly
                                        placeholder="Click 'Get Location' to capture"
                                    />
                                    <Button
                                        type="button"
                                        @click="fetchLocation"
                                        :disabled="geoLoading"
                                        variant="outline"
                                    >
                                        <Loader2 v-if="geoLoading" class="h-4 w-4 animate-spin mr-2" />
                                        {{ geoLoading ? 'Getting...' : 'Get Location' }}
                                    </Button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Your browser will request permission to access your location
                                </p>
                            </div>

                            <!-- Location Details (when captured) -->
                            <div v-if="parsedLocation" class="rounded-md border p-4 space-y-2">
                                <div class="text-sm font-medium">Location Details:</div>
                                <div class="text-sm text-muted-foreground space-y-1">
                                    <div v-if="parsedLocation.address?.city">
                                        <strong>City:</strong> {{ parsedLocation.address.city }}
                                    </div>
                                    <div v-if="parsedLocation.address?.state">
                                        <strong>State:</strong> {{ parsedLocation.address.state }}
                                    </div>
                                    <div v-if="parsedLocation.address?.country">
                                        <strong>Country:</strong> {{ parsedLocation.address.country }}
                                    </div>
                                    <div class="pt-2 text-xs">
                                        <strong>Coordinates:</strong> {{ parsedLocation.latitude.toFixed(6) }}, {{ parsedLocation.longitude.toFixed(6) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="flex gap-3 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex-1"
                                    @click="router.visit(`/redeem/${props.voucher_code}/wallet`)"
                                    :disabled="submitting || geoLoading"
                                >
                                    Back
                                </Button>
                                <Button
                                    type="submit"
                                    class="flex-1"
                                    :disabled="!location || submitting || geoLoading"
                                >
                                    <Loader2 v-if="submitting" class="h-4 w-4 animate-spin mr-2" />
                                    {{ submitting ? 'Processing...' : 'Continue' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Geo Permission Alert Modal -->
                <GeoPermissionAlert ref="geoAlertRef" />
            </template>
        </div>
    </PublicLayout>
</template>
