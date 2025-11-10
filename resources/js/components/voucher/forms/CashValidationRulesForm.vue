<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import type { CashValidation } from '@/types/voucher';

interface Props {
    modelValue: CashValidation;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: CashValidation];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const updateField = (field: keyof CashValidation, value: string | null) => {
    localValue.value = {
        ...localValue.value,
        [field]: value || null,
    };
};
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-2">
            <Label for="validation_secret">Validation Secret</Label>
            <Input
                id="validation_secret"
                v-model="localValue.secret"
                type="text"
                placeholder="Optional secret code for redemption"
                :readonly="readonly"
                @input="(e) => updateField('secret', (e.target as HTMLInputElement).value)"
            />
            <InputError :message="validationErrors['cash.validation.secret']" />
            <p class="text-xs text-muted-foreground">
                Require a secret code to be entered during redemption
            </p>
        </div>

        <div class="space-y-2">
            <Label for="validation_mobile">Validation Mobile Number</Label>
            <Input
                id="validation_mobile"
                v-model="localValue.mobile"
                type="tel"
                placeholder="+639171234567"
                :readonly="readonly"
                @input="(e) => updateField('mobile', (e.target as HTMLInputElement).value)"
            />
            <InputError :message="validationErrors['cash.validation.mobile']" />
            <p class="text-xs text-muted-foreground">
                Philippine mobile number format required
            </p>
        </div>

        <div class="space-y-2">
            <Label for="validation_country">Country Code</Label>
            <Input
                id="validation_country"
                v-model="localValue.country"
                type="text"
                placeholder="PH"
                maxlength="2"
                :readonly="readonly"
                @input="(e) => updateField('country', (e.target as HTMLInputElement).value)"
            />
            <InputError :message="validationErrors['cash.validation.country']" />
            <p class="text-xs text-muted-foreground">
                ISO 3166-1 alpha-2 country code (e.g., PH, US)
            </p>
        </div>

        <div class="space-y-2">
            <Label for="validation_location">Location</Label>
            <Input
                id="validation_location"
                v-model="localValue.location"
                type="text"
                placeholder="14.5995,120.9842"
                :readonly="readonly"
                @input="(e) => updateField('location', (e.target as HTMLInputElement).value)"
            />
            <InputError :message="validationErrors['cash.validation.location']" />
            <p class="text-xs text-muted-foreground">
                Latitude,Longitude coordinates for location-based validation
            </p>
        </div>

        <div class="space-y-2">
            <Label for="validation_radius">Radius</Label>
            <Input
                id="validation_radius"
                v-model="localValue.radius"
                type="text"
                placeholder="1000m or 2km"
                :readonly="readonly"
                @input="(e) => updateField('radius', (e.target as HTMLInputElement).value)"
            />
            <InputError :message="validationErrors['cash.validation.radius']" />
            <p class="text-xs text-muted-foreground">
                Distance format: number + unit (m or km), e.g., "1000m", "2km"
            </p>
        </div>
    </div>
</template>
