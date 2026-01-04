<script setup lang="ts">
/**
 * LocationValidationForm - Form component for location-based validation configuration
 * 
 * Maps to LocationValidationData.php DTO
 * 
 * Allows configuration of geo-fencing with:
 * - Target coordinates (latitude/longitude)
 * - Radius in meters
 * - Validation mode (block/warn)
 * 
 * @component
 */
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MapPin } from 'lucide-vue-next';

interface LocationValidation {
    required: boolean;
    target_lat: number | null;
    target_lng: number | null;
    radius_meters: number | null;
    on_failure: 'block' | 'warn';
}

interface LocationValidationConfig {
    default_radius_km?: number;
    default_radius_step_km?: number;
    default_on_failure?: 'block' | 'warn';
    auto_fill_current_location?: boolean;
}

interface Props {
    modelValue: LocationValidation | null;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
    config?: LocationValidationConfig;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
    config: () => ({
        default_radius_km: 0.5,
        default_radius_step_km: 0.5,
        default_on_failure: 'block',
        auto_fill_current_location: true,
    }),
});

const emit = defineEmits<{
    'update:modelValue': [value: LocationValidation | null];
}>();

const defaultLocationValidation = (): LocationValidation => ({
    required: true,
    target_lat: null,
    target_lng: null,
    radius_meters: (props.config?.default_radius_km ?? 0.5) * 1000, // Convert km to meters
    on_failure: props.config?.default_on_failure ?? 'block',
});

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// Use explicit boolean ref for checkbox state
const enabled = ref(props.modelValue !== null);

// Watch modelValue to sync checkbox state (from parent)
watch(() => props.modelValue, (newVal) => {
    enabled.value = newVal !== null;
}, { immediate: true });

// Watch enabled to update modelValue (from checkbox click)
watch(enabled, (newVal) => {
    if (newVal) {
        const newLocation = defaultLocationValidation();
        emit('update:modelValue', newLocation);
        
        // Auto-fill current location if enabled
        if (props.config?.auto_fill_current_location && !props.readonly) {
            // Use next tick to ensure the model is updated first
            setTimeout(() => useCurrentLocation(), 100);
        }
    } else {
        emit('update:modelValue', null);
    }
});

const updateField = (field: keyof LocationValidation, value: any) => {
    if (localValue.value) {
        localValue.value = {
            ...localValue.value,
            [field]: value,
        };
    }
};

// Computed for radius in kilometers (for better UX)
const radiusKm = computed({
    get: () => localValue.value?.radius_meters ? localValue.value.radius_meters / 1000 : (props.config?.default_radius_km ?? 0.5),
    set: (value: number) => {
        if (localValue.value && value > 0) {
            updateField('radius_meters', Math.round(value * 1000));
        }
    },
});

// Helper to get user's current location
const useCurrentLocation = () => {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            if (localValue.value) {
                localValue.value = {
                    ...localValue.value,
                    target_lat: position.coords.latitude,
                    target_lng: position.coords.longitude,
                };
            }
        },
        (error) => {
            alert(`Error getting location: ${error.message}`);
        }
    );
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <MapPin class="h-5 w-5" />
                <CardTitle>Location Validation</CardTitle>
            </div>
            <CardDescription>
                Require vouchers to be redeemed at a specific geographic location
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Enable/Disable Toggle -->
            <div class="flex items-center space-x-2">
                <Checkbox
                    id="location_enabled"
                    v-model="enabled"
                    :disabled="readonly"
                />
                <Label
                    for="location_enabled"
                    class="text-sm font-medium cursor-pointer"
                    :class="{ 'cursor-not-allowed opacity-50': readonly }"
                >
                    Enable location validation
                </Label>
            </div>

            <div v-if="enabled" class="space-y-6">
            <div class="rounded-lg bg-muted/50 p-4">
                <p class="text-sm text-muted-foreground">
                    <strong>Geo-Fencing:</strong> Users must be within the specified radius of the target location to redeem the voucher.
                </p>
            </div>

            <!-- Target Coordinates -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <Label>Target Location</Label>
                    <button
                        v-if="!readonly"
                        type="button"
                        class="text-sm text-primary hover:underline"
                        @click="useCurrentLocation"
                    >
                        Use Current Location
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="target_lat">Latitude</Label>
                        <Input
                            id="target_lat"
                            type="number"
                            :model-value="localValue?.target_lat ?? ''"
                            @update:model-value="updateField('target_lat', $event ? parseFloat($event) : null)"
                            placeholder="14.5995"
                            :readonly="readonly"
                            :required="enabled"
                        />
                        <InputError :message="validationErrors['validation.location.target_lat']" />
                        <p class="text-xs text-muted-foreground">
                            -90 to 90
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="target_lng">Longitude</Label>
                        <Input
                            id="target_lng"
                            type="number"
                            :model-value="localValue?.target_lng ?? ''"
                            @update:model-value="updateField('target_lng', $event ? parseFloat($event) : null)"
                            placeholder="120.9842"
                            :readonly="readonly"
                            :required="enabled"
                        />
                        <InputError :message="validationErrors['validation.location.target_lng']" />
                        <p class="text-xs text-muted-foreground">
                            -180 to 180
                        </p>
                    </div>
                </div>
            </div>

            <!-- Radius -->
            <div class="space-y-2">
                <Label for="radius">Allowed Radius (kilometers)</Label>
                <Input
                    id="radius"
                    type="number"
                    :step="config?.default_radius_step_km ?? 0.5"
                    v-model.number="radiusKm"
                    :min="0.1"
                    :max="10"
                    placeholder="0.5"
                    :readonly="readonly"
                    :required="enabled"
                />
                <InputError :message="validationErrors['validation.location.radius_meters']" />
                <p class="text-xs text-muted-foreground">
                    Users must be within {{ radiusKm }} km ({{ localValue?.radius_meters ?? 0 }} meters) of the target location
                </p>
            </div>

            <!-- Validation Mode -->
            <div class="space-y-2">
                <Label for="on_failure">Validation Mode</Label>
                <Select
                    :model-value="localValue?.on_failure ?? 'block'"
                    @update:model-value="updateField('on_failure', $event)"
                    :disabled="readonly"
                >
                    <SelectTrigger id="on_failure">
                        <SelectValue placeholder="Select mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="block">
                            <div>
                                <div class="font-medium">Block</div>
                                <div class="text-xs text-muted-foreground">Prevent redemption if outside radius</div>
                            </div>
                        </SelectItem>
                        <SelectItem value="warn">
                            <div>
                                <div class="font-medium">Warn</div>
                                <div class="text-xs text-muted-foreground">Allow redemption but log the violation</div>
                            </div>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="validationErrors['validation.location.on_failure']" />
            </div>

            <!-- Preview -->
            <div v-if="localValue?.target_lat && localValue?.target_lng" class="rounded-lg border p-4 space-y-2">
                <p class="text-sm font-medium">Configuration Summary</p>
                <ul class="text-sm text-muted-foreground space-y-1">
                    <li>📍 Target: {{ localValue.target_lat.toFixed(6) }}, {{ localValue.target_lng.toFixed(6) }}</li>
                    <li>📏 Radius: {{ radiusKm }} km ({{ localValue.radius_meters }} meters)</li>
                    <li>🚫 Mode: {{ localValue.on_failure === 'block' ? 'Block if outside' : 'Warn if outside' }}</li>
                </ul>
                <p class="text-xs text-muted-foreground pt-2">
                    💡 Tip: You can test this by using the <a href="https://www.google.com/maps" target="_blank" class="text-primary hover:underline">Google Maps</a> to find coordinates.
                </p>
            </div>
            </div>
        </CardContent>
    </Card>
</template>
