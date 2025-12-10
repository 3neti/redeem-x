<script setup lang="ts">
import { computed, ref } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, DollarSign } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import CashValidationRulesForm from './CashValidationRulesForm.vue';
import type { CashInstruction } from '@/types/voucher';

interface Props {
    modelValue: CashInstruction;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
    showValidationRules?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
    showValidationRules: true,
});

const emit = defineEmits<{
    'update:modelValue': [value: CashInstruction];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const showValidationCollapsible = ref(false);

const updateAmount = (value: string) => {
    localValue.value = {
        ...localValue.value,
        amount: parseFloat(value) || 0,
    };
};

const updateCurrency = (value: string) => {
    localValue.value = {
        ...localValue.value,
        currency: value,
    };
};

const updateValidation = (validation: CashInstruction['validation']) => {
    localValue.value = {
        ...localValue.value,
        validation,
    };
};

const updateSettlementRail = (value: string | null) => {
    localValue.value = {
        ...localValue.value,
        settlement_rail: value === 'auto' ? null : value,
    };
};

const updateFeeStrategy = (value: string) => {
    localValue.value = {
        ...localValue.value,
        fee_strategy: value,
    };
};

// Fee preview calculation
const railFees = { INSTAPAY: 10, PESONET: 25 }; // In pesos
const selectedRailValue = computed(() => {
    if (localValue.value.settlement_rail) return localValue.value.settlement_rail;
    return localValue.value.amount < 50000 ? 'INSTAPAY' : 'PESONET';
});
const estimatedFee = computed(() => railFees[selectedRailValue.value as keyof typeof railFees] || 10);
const adjustedAmount = computed(() => {
    const strategy = localValue.value.fee_strategy || 'absorb';
    if (strategy === 'include') return Math.max(0, localValue.value.amount - estimatedFee.value);
    return localValue.value.amount;
});
const totalCost = computed(() => {
    const strategy = localValue.value.fee_strategy || 'absorb';
    if (strategy === 'add') return localValue.value.amount + estimatedFee.value;
    return localValue.value.amount;
});
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <DollarSign class="h-5 w-5" />
                <CardTitle>Cash Settings</CardTitle>
            </div>
            <CardDescription>
                Configure the voucher amount and currency
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="cash_amount">Amount</Label>
                    <Input
                        id="cash_amount"
                        type="number"
                        :model-value="localValue.amount"
                        :min="0"
                        step="0.01"
                        required
                        :readonly="readonly"
                        @input="(e) => updateAmount((e.target as HTMLInputElement).value)"
                    />
                    <InputError :message="validationErrors['cash.amount']" />
                </div>

                <div class="space-y-2">
                    <Label for="cash_currency">Currency</Label>
                    <Input
                        id="cash_currency"
                        type="text"
                        :model-value="localValue.currency"
                        placeholder="PHP"
                        maxlength="3"
                        required
                        :readonly="readonly"
                        @input="(e) => updateCurrency((e.target as HTMLInputElement).value)"
                    />
                    <InputError :message="validationErrors['cash.currency']" />
                    <p class="text-xs text-muted-foreground">
                        3-letter currency code (e.g., PHP, USD)
                    </p>
                </div>
            </div>

            <!-- Settlement Rail & Fee Strategy Section -->
            <div class="space-y-4 rounded-lg border p-4 bg-muted/30">
                <div class="space-y-2">
                    <Label for="settlement_rail">Settlement Rail</Label>
                    <Select
                        :model-value="localValue.settlement_rail || 'auto'"
                        :disabled="readonly"
                        @update:model-value="updateSettlementRail"
                    >
                        <SelectTrigger id="settlement_rail">
                            <SelectValue placeholder="Select settlement rail" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="auto">Auto (based on amount)</SelectItem>
                            <SelectItem value="INSTAPAY">INSTAPAY (Real-time, ₱10 fee, &lt;₱50k)</SelectItem>
                            <SelectItem value="PESONET">PESONET (Next-day, ₱25 fee, up to ₱1M)</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Selected: <strong>{{ selectedRailValue }}</strong> · Est. fee: ₱{{ estimatedFee }}
                    </p>
                    <InputError :message="validationErrors['cash.settlement_rail']" />
                </div>

                <div class="space-y-3">
                    <Label>Fee Strategy</Label>
                    <RadioGroup
                        :model-value="localValue.fee_strategy || 'absorb'"
                        :disabled="readonly"
                        @update:model-value="updateFeeStrategy"
                    >
                        <div class="flex items-start space-x-2">
                            <RadioGroupItem value="absorb" id="absorb" />
                            <div class="space-y-1">
                                <Label for="absorb" class="font-normal cursor-pointer">
                                    <strong>Absorb</strong> - Issuer pays fee (Redeemer gets ₱{{ localValue.amount }})
                                </Label>
                            </div>
                        </div>
                        <div class="flex items-start space-x-2">
                            <RadioGroupItem value="include" id="include" />
                            <div class="space-y-1">
                                <Label for="include" class="font-normal cursor-pointer">
                                    <strong>Include</strong> - Deduct fee from voucher (Redeemer gets ₱{{ adjustedAmount }})
                                </Label>
                            </div>
                        </div>
                        <div class="flex items-start space-x-2">
                            <RadioGroupItem value="add" id="add" />
                            <div class="space-y-1">
                                <Label for="add" class="font-normal cursor-pointer">
                                    <strong>Add</strong> - Redeemer pays fee (Total cost: ₱{{ totalCost }})
                                </Label>
                            </div>
                        </div>
                    </RadioGroup>
                    <InputError :message="validationErrors['cash.fee_strategy']" />
                </div>
            </div>

            <!-- Validation Rules Section -->
            <Collapsible v-if="showValidationRules" v-model:open="showValidationCollapsible">
                <CollapsibleTrigger
                    class="flex w-full items-center justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors"
                    :disabled="readonly"
                >
                    <div class="text-left">
                        <div class="font-medium">Validation Rules (Optional)</div>
                        <div class="text-sm text-muted-foreground">
                            Add secret codes, mobile verification, or location-based validation
                        </div>
                    </div>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200"
                        :class="{ 'transform rotate-180': showValidationCollapsible }"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent class="pt-4">
                    <CashValidationRulesForm
                        :model-value="localValue.validation"
                        :validation-errors="validationErrors"
                        :readonly="readonly"
                        @update:model-value="updateValidation"
                    />
                </CollapsibleContent>
            </Collapsible>
        </CardContent>
    </Card>
</template>
