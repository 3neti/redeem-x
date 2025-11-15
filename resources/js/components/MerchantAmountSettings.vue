<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

interface Props {
    isDynamic: boolean;
    defaultAmount: number | null;
    minAmount: number | null;
    maxAmount: number | null;
    allowTip: boolean;
    disabled?: boolean;
}

interface Emits {
    (e: 'update:isDynamic', value: boolean): void;
    (e: 'update:defaultAmount', value: number | null): void;
    (e: 'update:minAmount', value: number | null): void;
    (e: 'update:maxAmount', value: number | null): void;
    (e: 'update:allowTip', value: boolean): void;
}

withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<Emits>();

const handleNumberInput = (e: Event, which: 'default'|'min'|'max') => {
    const target = e.target as HTMLInputElement;
    const val = target.value;
    const parsed = val === '' ? null : Number(val);
    if (which === 'default') emit('update:defaultAmount', parsed);
    if (which === 'min') emit('update:minAmount', parsed);
    if (which === 'max') emit('update:maxAmount', parsed);
};
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-sm font-medium">Amount Settings</h3>
        
        <!-- Dynamic Amount Toggle -->
        <div class="flex items-center space-x-2 rounded-lg border p-4 bg-muted/30">
            <input
                type="checkbox"
                id="merchant_dynamic_amount"
                :checked="isDynamic"
                @change="emit('update:isDynamic', ($event.target as HTMLInputElement).checked)"
                :disabled="disabled"
                class="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-primary disabled:opacity-50"
            />
            <div class="space-y-0.5">
                <Label for="merchant_dynamic_amount" class="cursor-pointer font-medium">Dynamic Amount</Label>
                <p class="text-sm text-muted-foreground">
                    Generate QR codes without a fixed amount (payer chooses amount)
                </p>
            </div>
        </div>
        
        <!-- Default Amount -->
        <div class="grid gap-2">
            <Label 
                for="merchant_default_amount" 
                :class="{ 'text-muted-foreground': isDynamic }"
            >
                Default Amount (₱)
            </Label>
            <Input
                id="merchant_default_amount"
                :value="defaultAmount ?? ''"
                @input="(e) => handleNumberInput(e, 'default')"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                :disabled="isDynamic || disabled"
            />
        </div>

        <!-- Min/Max Amount -->
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label 
                    for="merchant_min_amount" 
                    :class="{ 'text-muted-foreground': isDynamic }"
                >
                    Min Amount (₱)
                </Label>
                <Input
                    id="merchant_min_amount"
                    :value="minAmount ?? ''"
                    @input="(e) => handleNumberInput(e, 'min')"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Optional"
                    :disabled="isDynamic || disabled"
                />
            </div>

            <div class="grid gap-2">
                <Label 
                    for="merchant_max_amount" 
                    :class="{ 'text-muted-foreground': isDynamic }"
                >
                    Max Amount (₱)
                </Label>
                <Input
                    id="merchant_max_amount"
                    :value="maxAmount ?? ''"
                    @input="(e) => handleNumberInput(e, 'max')"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Optional"
                    :disabled="isDynamic || disabled"
                />
            </div>
        </div>

        <!-- Allow Tips -->
        <div class="flex items-center space-x-2 rounded-lg border p-4">
            <input
                type="checkbox"
                id="merchant_allow_tip"
                :checked="allowTip"
                @change="emit('update:allowTip', ($event.target as HTMLInputElement).checked)"
                :disabled="disabled"
                class="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-primary disabled:opacity-50"
            />
            <div class="space-y-0.5">
                <Label for="merchant_allow_tip" class="cursor-pointer">Allow Tips</Label>
                <p class="text-sm text-muted-foreground">
                    Let customers add a tip amount
                </p>
            </div>
        </div>
    </div>
</template>
