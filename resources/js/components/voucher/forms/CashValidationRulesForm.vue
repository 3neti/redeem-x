<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/ui/phone-input/PhoneInput.vue';
import type { CashValidation } from '@/types/voucher';
import { index as vendorAliasesIndex } from '@/actions/App/Http/Controllers/Settings/VendorAliasController';

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

const updateField = (field: keyof CashValidation, value: string | number | null) => {
    localValue.value = {
        ...localValue.value,
        [field]: value || null,
    };
};

// Fetch vendor aliases for payable field
interface VendorAlias {
    id: number;
    alias: string;
    status: string;
}

const vendorAliases = ref<VendorAlias[]>([]);
const isLoadingAliases = ref(false);

onMounted(async () => {
    if (!props.readonly) {
        isLoadingAliases.value = true;
        try {
            const response = await fetch(vendorAliasesIndex.url());
            const data = await response.json();
            // Filter to only active aliases
            vendorAliases.value = (data.aliases?.data || [])
                .filter((alias: VendorAlias) => alias.status === 'active');
        } catch (error) {
            console.error('Failed to load vendor aliases:', error);
        } finally {
            isLoadingAliases.value = false;
        }
    }
});
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
            <PhoneInput
                v-model="localValue.mobile"
                :error="validationErrors['cash.validation.mobile']"
                :readonly="readonly"
                placeholder="0917 123 4567"
            />
            <p class="text-xs text-muted-foreground">
                Philippine mobile number format required
            </p>
        </div>

        <div class="space-y-2">
            <Label for="validation_payable">Payable To (Vendor Alias)</Label>
            <Select
                v-if="!readonly"
                :model-value="localValue.payable?.toString() || ''"
                @update:model-value="(value) => updateField('payable', value ? parseInt(value) : null)"
            >
                <SelectTrigger id="validation_payable">
                    <SelectValue placeholder="Optional - Select vendor alias" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">None (Anyone can redeem)</SelectItem>
                    <SelectItem
                        v-for="alias in vendorAliases"
                        :key="alias.id"
                        :value="alias.id.toString()"
                    >
                        {{ alias.alias }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Input
                v-else
                :value="localValue.payable ? vendorAliases.find(a => a.id === localValue.payable)?.alias || localValue.payable : 'None'"
                readonly
            />
            <InputError :message="validationErrors['cash.validation.payable']" />
            <p class="text-xs text-muted-foreground">
                Restrict redemption to a specific merchant (B2B vouchers)
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
