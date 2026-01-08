<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useBrowserLocation } from '../composables/useBrowserLocation';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle, MapPin } from 'lucide-vue-next';
import GeoPermissionAlert from './GeoPermissionAlert.vue';

export interface LocationCaptureConfig {
    opencage_api_key?: string;
    map_provider?: 'mapbox' | 'google';
    mapbox_token?: string;
    google_maps_api_key?: string;
    capture_snapshot?: boolean;
    require_address?: boolean;
}

export interface LocationData {
    latitude: number;
    longitude: number;
    timestamp: string;
    accuracy?: number;
    address?: {
        formatted?: string | null;
        city?: string | null;
        state?: string | null;
        country?: string | null;
    } | null;
    map?: string;
}

interface Props {
    config?: LocationCaptureConfig;
    modelValue?: LocationData | null;
}

interface Emits {
    (e: 'update:modelValue', value: LocationData | null): void;
    (e: 'submit', value: LocationData): void;
    (e: 'cancel'): void;
}

const props = withDefaults(defineProps<Props>(), {
    config: () => ({}),
    modelValue: null,
});

const emit = defineEmits<Emits>();

// Reactive config values
const opencageKey = computed(() => props.config?.opencage_api_key || '');
const mapProvider = computed(() => props.config?.map_provider || 'mapbox');
const mapboxToken = computed(() => props.config?.mapbox_token || '');
const googleMapsKey = computed(() => props.config?.google_maps_api_key || '');
const captureSnapshot = computed(() => props.config?.capture_snapshot ?? true);
const requireAddress = computed(() => props.config?.require_address ?? false);

const { location, loading: geoLoading, error: geoError, getLocation } = useBrowserLocation(opencageKey.value, 3 * 60 * 1000);

const geoAlertRef = ref<InstanceType<typeof GeoPermissionAlert> | null>(null);
const apiError = ref<string | null>(null);
const coordinatesCopied = ref(false);
const mapSnapshot = ref<string | null>(null);

const parsedLocation = computed(() => {
    return location.value;
});

const formattedAddress = computed(() => {
    return parsedLocation.value?.address?.formatted || '';
});

const staticMapUrl = computed(() => {
    if (!location.value) return '';
    
    const { latitude, longitude } = location.value;
    const zoom = 16;
    const size = 600;
    
    if (mapProvider.value === 'mapbox' && mapboxToken.value && mapboxToken.value !== 'your_actual_token_here') {
        return `https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+ff0000(${longitude},${latitude})/${longitude},${latitude},${zoom},0/${size}x300@2x?access_token=${mapboxToken.value}`;
    }
    
    // Fallback: Google Maps static
    const url = `https://maps.googleapis.com/maps/api/staticmap?center=${latitude},${longitude}&zoom=${zoom}&size=${size}x300&markers=color:red%7C${latitude},${longitude}`;
    if (googleMapsKey.value) {
        return `${url}&key=${googleMapsKey.value}`;
    }
    return url;
});

async function fetchLocation() {
    const data = await getLocation(false);

    if (geoError.value === 'PERMISSION_DENIED') {
        geoAlertRef.value?.open();
        return;
    }

    if (!data) {
        apiError.value = 'Failed to get location. Please try again.';
    } else if (requireAddress.value && !data.address?.formatted) {
        apiError.value = 'Could not determine your address. Please try again or enable location services.';
    } else {
        // Emit the location data
        emit('update:modelValue', data);
    }
}

function copyCoordinates() {
    if (!location.value) return;
    
    const coords = `${location.value.latitude.toFixed(6)}, ${location.value.longitude.toFixed(6)}`;
    navigator.clipboard.writeText(coords).then(() => {
        coordinatesCopied.value = true;
        setTimeout(() => {
            coordinatesCopied.value = false;
        }, 2000);
    });
}

async function captureMapSnapshot(): Promise<string | null> {
    if (!staticMapUrl.value || !captureSnapshot.value) return null;
    
    try {
        const response = await fetch(staticMapUrl.value);
        const blob = await response.blob();
        
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result as string);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
        });
    } catch (err) {
        console.error('[LocationCapture] Failed to capture map snapshot:', err);
        return null;
    }
}

async function handleMapImageLoad() {
    if (!captureSnapshot.value) return;
    
    mapSnapshot.value = await captureMapSnapshot();
    
    // Update the location with map snapshot
    if (mapSnapshot.value && location.value) {
        const locationWithMap = {
            ...location.value,
            map: mapSnapshot.value,
        };
        emit('update:modelValue', locationWithMap);
    }
}

function handleSubmit() {
    if (!location.value) {
        apiError.value = 'Please capture your location first.';
        return;
    }

    if (requireAddress.value && !location.value.address?.formatted) {
        apiError.value = 'Address information is required but could not be determined.';
        return;
    }

    apiError.value = null;

    // Prepare location data with map (if available)
    const locationWithMap: LocationData = {
        ...location.value,
        ...(mapSnapshot.value && { map: mapSnapshot.value }),
    };

    emit('submit', locationWithMap);
}

function handleCancel() {
    emit('cancel');
}

// Initialize from modelValue if provided
onMounted(() => {
    if (props.modelValue) {
        location.value = props.modelValue;
    }
});
</script>

<template>
    <div class="container mx-auto max-w-2xl px-4 py-8">
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
                    Please share your current location to continue
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Get Location Button (shown when no location captured) -->
                    <div v-if="!parsedLocation" class="flex flex-col items-center justify-center py-8 space-y-4">
                        <MapPin class="h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground text-center">
                            We need to capture your current location to continue
                        </p>
                        <Button
                            type="button"
                            @click="fetchLocation"
                            :disabled="geoLoading"
                            size="lg"
                        >
                            <Loader2 v-if="geoLoading" class="h-4 w-4 animate-spin mr-2" />
                            {{ geoLoading ? 'Getting Location...' : 'Capture My Location' }}
                        </Button>
                    </div>

                    <!-- Static Map & Location Info (when location captured) -->
                    <div v-if="parsedLocation" class="space-y-4">
                        <!-- Map Image -->
                        <div v-if="staticMapUrl" class="rounded-lg border overflow-hidden">
                            <img
                                :src="staticMapUrl"
                                alt="Map showing your location"
                                class="w-full h-64 object-cover"
                                loading="lazy"
                                @load="handleMapImageLoad"
                            />
                        </div>

                        <!-- Location Address & Coordinates -->
                        <div class="space-y-3">
                            <div v-if="formattedAddress" class="text-sm font-medium">
                                {{ formattedAddress }}
                            </div>
                            
                            <!-- Copyable Coordinates -->
                            <button
                                type="button"
                                @click="copyCoordinates"
                                class="flex items-center gap-2 text-xs text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <span>{{ parsedLocation.latitude.toFixed(6) }}, {{ parsedLocation.longitude.toFixed(6) }}</span>
                                <svg v-if="!coordinatesCopied" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="h-3 w-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="handleCancel"
                            :disabled="geoLoading"
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="!location || geoLoading"
                        >
                            Continue
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Geo Permission Alert Modal -->
        <GeoPermissionAlert ref="geoAlertRef" />
    </div>
</template>
