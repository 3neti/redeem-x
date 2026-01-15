<script setup lang="ts">
/**
 * TimeValidationForm - Form component for time-based validation configuration
 * 
 * Maps to TimeValidationData.php DTO
 * 
 * Allows configuration of:
 * - Time windows (e.g., business hours only)
 * - Duration limits (max completion time)
 * - Timezone
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
import { NumberInput } from '@/components/ui/number-input';
import { Clock } from 'lucide-vue-next';

interface TimeWindow {
    start_time: string;
    end_time: string;
    timezone: string;
}

interface TimeValidation {
    window: TimeWindow | null;
    limit_minutes: number | null;
    track_duration: boolean;
}

interface TimeValidationConfig {
    default_window_enabled?: boolean;
    default_timezone?: string;
    default_start_time?: string;
    default_end_time?: string;
    default_duration_enabled?: boolean;
    default_limit_minutes?: number;
}

interface Props {
    modelValue: TimeValidation | null;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
    config?: TimeValidationConfig;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
    config: () => ({
        default_window_enabled: false,
        default_timezone: 'Asia/Manila',
        default_start_time: '09:00',
        default_end_time: '17:00',
        default_duration_enabled: false,
        default_limit_minutes: 10,
    }),
});

const emit = defineEmits<{
    'update:modelValue': [value: TimeValidation | null];
}>();

const defaultTimeValidation = (): TimeValidation => {
    const windowEnabled = props.config?.default_window_enabled ?? false;
    const durationEnabled = props.config?.default_duration_enabled ?? false;
    
    return {
        window: windowEnabled ? {
            start_time: props.config?.default_start_time ?? '09:00',
            end_time: props.config?.default_end_time ?? '17:00',
            timezone: props.config?.default_timezone ?? 'Asia/Manila',
        } : null,
        limit_minutes: durationEnabled ? (props.config?.default_limit_minutes ?? 10) : null,
        track_duration: true,
    };
};

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
        emit('update:modelValue', defaultTimeValidation());
    } else {
        emit('update:modelValue', null);
    }
});

const windowEnabled = computed({
    get: () => localValue.value?.window !== null,
    set: (value) => {
        if (localValue.value) {
            if (value) {
                localValue.value = {
                    ...localValue.value,
                    window: {
                        start_time: props.config?.default_start_time ?? '09:00',
                        end_time: props.config?.default_end_time ?? '17:00',
                        timezone: props.config?.default_timezone ?? 'Asia/Manila',
                    },
                };
            } else {
                localValue.value = {
                    ...localValue.value,
                    window: null,
                };
            }
        }
    },
});

const durationLimitEnabled = computed({
    get: () => localValue.value?.limit_minutes !== null,
    set: (value) => {
        if (localValue.value) {
            if (value) {
                localValue.value = {
                    ...localValue.value,
                    limit_minutes: props.config?.default_limit_minutes ?? 10,
                };
            } else {
                localValue.value = {
                    ...localValue.value,
                    limit_minutes: null,
                };
            }
        }
    },
});

const updateWindowField = (field: keyof TimeWindow, value: any) => {
    if (localValue.value?.window) {
        localValue.value = {
            ...localValue.value,
            window: {
                ...localValue.value.window,
                [field]: value,
            },
        };
    }
};

const updateField = (field: keyof TimeValidation, value: any) => {
    if (localValue.value) {
        localValue.value = {
            ...localValue.value,
            [field]: value,
        };
    }
};

// Check if time window spans midnight
const spansMidnight = computed(() => {
    if (!localValue.value?.window) return false;
    const { start_time, end_time } = localValue.value.window;
    return start_time > end_time;
});

// Available timezones (common ones in Asia)
const timezones = [
    { value: 'Asia/Manila', label: 'Asia/Manila (PHT, UTC+8)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (SGT, UTC+8)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (JST, UTC+9)' },
    { value: 'Asia/Hong_Kong', label: 'Asia/Hong_Kong (HKT, UTC+8)' },
    { value: 'Asia/Bangkok', label: 'Asia/Bangkok (ICT, UTC+7)' },
    { value: 'Asia/Jakarta', label: 'Asia/Jakarta (WIB, UTC+7)' },
    { value: 'UTC', label: 'UTC (UTC+0)' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <Clock class="h-5 w-5" />
                <CardTitle>Time Validation</CardTitle>
            </div>
            <CardDescription>
                Restrict voucher redemption to specific time windows and/or duration limits
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Enable/Disable Toggle -->
            <div class="flex items-center space-x-2">
                <Checkbox
                    id="time_enabled"
                    v-model="enabled"
                    :disabled="readonly"
                />
                <Label
                    for="time_enabled"
                    class="text-sm font-medium cursor-pointer"
                    :class="{ 'cursor-not-allowed opacity-50': readonly }"
                >
                    Enable time validation
                </Label>
            </div>

            <div v-if="enabled" class="space-y-6">
            <div class="rounded-lg bg-muted/50 p-4">
                <p class="text-sm text-muted-foreground">
                    <strong>Time Controls:</strong> Enforce when vouchers can be redeemed and how long the redemption process can take.
                </p>
            </div>

            <!-- Time Window Section -->
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="time_window_enabled"
                        v-model="windowEnabled"
                        :disabled="readonly"
                    />
                    <div>
                        <Label for="time_window_enabled" class="text-base cursor-pointer" :class="{ 'cursor-not-allowed opacity-50': readonly }">
                            Time Window
                        </Label>
                        <p class="text-sm text-muted-foreground">Restrict to specific hours of the day</p>
                    </div>
                </div>

                <div v-if="windowEnabled" class="space-y-4 pl-4 border-l-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="start_time">Start Time</Label>
                            <Input
                                id="start_time"
                                type="time"
                                :model-value="localValue?.window?.start_time ?? '09:00'"
                                @update:model-value="updateWindowField('start_time', $event)"
                                :readonly="readonly"
                                :required="windowEnabled"
                            />
                            <InputError :message="validationErrors['validation.time.window.start_time']" />
                        </div>

                        <div class="space-y-2">
                            <Label for="end_time">End Time</Label>
                            <Input
                                id="end_time"
                                type="time"
                                :model-value="localValue?.window?.end_time ?? '17:00'"
                                @update:model-value="updateWindowField('end_time', $event)"
                                :readonly="readonly"
                                :required="windowEnabled"
                            />
                            <InputError :message="validationErrors['validation.time.window.end_time']" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="timezone">Timezone</Label>
                        <Select
                            :model-value="localValue?.window?.timezone ?? 'Asia/Manila'"
                            @update:model-value="updateWindowField('timezone', $event)"
                            :disabled="readonly"
                        >
                            <SelectTrigger id="timezone">
                                <SelectValue placeholder="Select timezone" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem 
                                    v-for="tz in timezones" 
                                    :key="tz.value" 
                                    :value="tz.value"
                                >
                                    {{ tz.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="validationErrors['validation.time.window.timezone']" />
                    </div>

                    <div v-if="spansMidnight" class="rounded-lg bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-900/50 p-3">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            ⚠️ <strong>Cross-Midnight Window:</strong> This time window spans midnight 
                            (e.g., {{ localValue?.window?.start_time }} to {{ localValue?.window?.end_time }}). 
                            Redemptions will be allowed from {{ localValue?.window?.start_time }} until 23:59, 
                            and from 00:00 to {{ localValue?.window?.end_time }}.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Duration Limit Section -->
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="duration_limit_enabled"
                        v-model="durationLimitEnabled"
                        :disabled="readonly"
                    />
                    <div>
                        <Label for="duration_limit_enabled" class="text-base cursor-pointer" :class="{ 'cursor-not-allowed opacity-50': readonly }">
                            Duration Limit
                        </Label>
                        <p class="text-sm text-muted-foreground">Maximum time to complete redemption</p>
                    </div>
                </div>

                <div v-if="durationLimitEnabled" class="space-y-4 pl-4 border-l-2">
                    <div class="space-y-2">
                        <Label for="limit_minutes">Maximum Duration</Label>
                        <NumberInput
                            id="limit_minutes"
                            :model-value="localValue?.limit_minutes ?? 10"
                            @update:model-value="updateField('limit_minutes', $event ? parseInt($event) : null)"
                            suffix="minutes"
                            :min="1"
                            :max="1440"
                            placeholder="10"
                            :readonly="readonly"
                            :required="durationLimitEnabled"
                        />
                        <InputError :message="validationErrors['validation.time.limit_minutes']" />
                        <p class="text-xs text-muted-foreground">
                            Users must complete redemption within {{ localValue?.limit_minutes ?? 0 }} minutes (max: 1440 = 24 hours)
                        </p>
                    </div>

                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-900/50 p-3">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            💡 <strong>Use Case:</strong> Duration limits help prevent fraud and ensure quick redemptions. 
                            Useful for time-sensitive promotions or in-person events.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Configuration Preview -->
            <div v-if="windowEnabled || durationLimitEnabled" class="rounded-lg border p-4 space-y-2">
                <p class="text-sm font-medium">Configuration Summary</p>
                <ul class="text-sm text-muted-foreground space-y-1">
                    <li v-if="windowEnabled">
                        ⏰ Allowed Hours: {{ localValue?.window?.start_time }} - {{ localValue?.window?.end_time }} 
                        ({{ localValue?.window?.timezone }})
                        <span v-if="spansMidnight" class="text-yellow-600 dark:text-yellow-400"> • Spans Midnight</span>
                    </li>
                    <li v-if="durationLimitEnabled">
                        ⏱️ Max Duration: {{ localValue?.limit_minutes }} minutes
                    </li>
                    <li v-if="!windowEnabled && !durationLimitEnabled">
                        No time restrictions configured
                    </li>
                </ul>
            </div>

            <!-- Example Scenarios -->
            <div class="rounded-lg bg-muted/30 p-4 space-y-2">
                <p class="text-sm font-medium">💡 Common Scenarios:</p>
                <ul class="text-xs text-muted-foreground space-y-1 list-disc list-inside">
                    <li><strong>Business Hours:</strong> 09:00 - 17:00, no duration limit</li>
                    <li><strong>Happy Hour:</strong> 17:00 - 19:00, 5 minute limit</li>
                    <li><strong>Night Shift:</strong> 22:00 - 06:00 (cross-midnight)</li>
                    <li><strong>Quick Checkout:</strong> Any time, 2 minute limit</li>
                </ul>
            </div>
            </div>
        </CardContent>
    </Card>
</template>
