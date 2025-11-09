<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AlertCircle, Code, FileText, Send, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { VoucherInputFieldOption } from '@/types/voucher';

interface Props {
    modelValue: {
        amount: number;
        count: number;
        prefix: string;
        mask: string;
        ttlDays: number | null;
        selectedInputFields: string[];
        validationSecret: string;
        validationMobile: string;
        feedbackEmail: string;
        feedbackMobile: string;
        feedbackWebhook: string;
        riderMessage: string;
        riderUrl: string;
    };
    inputFieldOptions: VoucherInputFieldOption[];
    validationErrors?: Record<string, string>;
    showCountField?: boolean;
    showJsonPreview?: boolean;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    showCountField: true,
    showJsonPreview: true,
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: Props['modelValue']];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const showJsonPreviewCollapsible = ref(false);

// Live JSON preview
const jsonPreview = computed(() => {
    const data = {
        cash: {
            amount: localValue.value.amount,
            currency: 'PHP',
            validation: {
                secret: localValue.value.validationSecret || null,
                mobile: localValue.value.validationMobile || null,
                country: 'PH',
                location: null,
                radius: null,
            },
        },
        inputs: {
            fields: localValue.value.selectedInputFields,
        },
        feedback: {
            email: localValue.value.feedbackEmail || null,
            mobile: localValue.value.feedbackMobile || null,
            webhook: localValue.value.feedbackWebhook || null,
        },
        rider: {
            message: localValue.value.riderMessage || null,
            url: localValue.value.riderUrl || null,
        },
        count: localValue.value.count,
        prefix: localValue.value.prefix || null,
        mask: localValue.value.mask || null,
        ttl: localValue.value.ttlDays ? `P${localValue.value.ttlDays}D` : null,
    };
    
    // Recursively remove null values
    const removeNulls = (obj: any): any => {
        if (Array.isArray(obj)) {
            return obj.map(removeNulls).filter(v => v !== null);
        }
        if (obj !== null && typeof obj === 'object') {
            return Object.entries(obj)
                .filter(([_, v]) => v !== null)
                .reduce((acc, [k, v]) => {
                    const cleaned = removeNulls(v);
                    // Only add if it's not an empty object or array
                    if (cleaned !== null && 
                        !(typeof cleaned === 'object' && Object.keys(cleaned).length === 0) &&
                        !(Array.isArray(cleaned) && cleaned.length === 0)) {
                        acc[k] = cleaned;
                    }
                    return acc;
                }, {} as any);
        }
        return obj;
    };
    
    return removeNulls(data);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Basic Settings -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <Settings class="h-5 w-5" />
                    <CardTitle>Basic Settings</CardTitle>
                </div>
                <CardDescription>
                    Configure voucher amount, quantity, and expiry
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <slot name="before-basic-fields" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="amount">Amount (PHP)</Label>
                        <Input
                            id="amount"
                            type="number"
                            v-model.number="localValue.amount"
                            :min="0"
                            step="0.01"
                            required
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.amount" />
                    </div>

                    <div v-if="showCountField" class="space-y-2">
                        <Label for="count">Quantity</Label>
                        <Input
                            id="count"
                            type="number"
                            v-model.number="localValue.count"
                            :min="1"
                            required
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.count" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="prefix">Code Prefix (Optional)</Label>
                        <Input
                            id="prefix"
                            v-model="localValue.prefix"
                            placeholder="e.g., PROMO"
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.prefix" />
                    </div>

                    <div class="space-y-2">
                        <Label for="mask">Code Mask (Optional)</Label>
                        <Input
                            id="mask"
                            v-model="localValue.mask"
                            placeholder="e.g., ****-****"
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.mask" />
                        <p class="text-xs text-muted-foreground">
                            Use * for random chars, - for separators (4-6 asterisks)
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="ttl_days">Expiry (Days)</Label>
                    <Input
                        id="ttl_days"
                        type="number"
                        v-model.number="localValue.ttlDays"
                        :min="1"
                        placeholder="30"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.ttl_days" />
                    <p class="text-xs text-muted-foreground">
                        Leave empty for non-expiring vouchers
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Input Fields -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    <CardTitle>Required Input Fields</CardTitle>
                </div>
                <CardDescription>
                    Select fields users must provide during redemption
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="option in inputFieldOptions"
                        :key="option.value"
                        class="flex items-center space-x-2 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            :id="option.value"
                            :value="option.value"
                            v-model="localValue.selectedInputFields"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            :disabled="readonly"
                        />
                        <span class="text-sm">
                            {{ option.label }}
                        </span>
                    </label>
                </div>
                <InputError :message="validationErrors.input_fields" class="mt-2" />
            </CardContent>
        </Card>

        <!-- Validation Rules -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <AlertCircle class="h-5 w-5" />
                    <CardTitle>Validation Rules (Optional)</CardTitle>
                </div>
                <CardDescription>
                    Add secret codes or location-based restrictions
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="validation_secret">Secret Code</Label>
                    <Input
                        id="validation_secret"
                        v-model="localValue.validationSecret"
                        placeholder="e.g., SECRET2025"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.validation_secret" />
                </div>

                <div class="space-y-2">
                    <Label for="validation_mobile">Restrict to Mobile Number</Label>
                    <Input
                        id="validation_mobile"
                        v-model="localValue.validationMobile"
                        placeholder="e.g., +639171234567"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.validation_mobile" />
                </div>
            </CardContent>
        </Card>

        <!-- Feedback Channels -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <Send class="h-5 w-5" />
                    <CardTitle>Feedback Channels (Optional)</CardTitle>
                </div>
                <CardDescription>
                    Receive notifications when vouchers are redeemed
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="feedback_email">Email</Label>
                    <Input
                        id="feedback_email"
                        type="email"
                        v-model="localValue.feedbackEmail"
                        placeholder="notifications@example.com"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.feedback_email" />
                </div>

                <div class="space-y-2">
                    <Label for="feedback_mobile">Mobile</Label>
                    <Input
                        id="feedback_mobile"
                        v-model="localValue.feedbackMobile"
                        placeholder="+639171234567"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.feedback_mobile" />
                </div>

                <div class="space-y-2">
                    <Label for="feedback_webhook">Webhook URL</Label>
                    <Input
                        id="feedback_webhook"
                        type="url"
                        v-model="localValue.feedbackWebhook"
                        placeholder="https://example.com/webhook"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.feedback_webhook" />
                </div>
            </CardContent>
        </Card>

        <!-- Rider -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    <CardTitle>Rider (Optional)</CardTitle>
                </div>
                <CardDescription>
                    Add custom message or redirect URL after redemption
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="rider_message">Message</Label>
                    <Input
                        id="rider_message"
                        v-model="localValue.riderMessage"
                        placeholder="Thank you for redeeming!"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.rider_message" />
                </div>

                <div class="space-y-2">
                    <Label for="rider_url">Redirect URL</Label>
                    <Input
                        id="rider_url"
                        type="url"
                        v-model="localValue.riderUrl"
                        placeholder="https://example.com/thank-you"
                        :readonly="readonly"
                    />
                    <InputError :message="validationErrors.rider_url" />
                </div>
            </CardContent>
        </Card>

        <!-- JSON Preview -->
        <Collapsible v-if="showJsonPreview" v-model:open="showJsonPreviewCollapsible">
            <Card>
                <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Code class="h-5 w-5" />
                                <CardTitle>Live JSON Preview</CardTitle>
                            </div>
                            <span class="text-sm text-muted-foreground">
                                {{ showJsonPreviewCollapsible ? '▼' : '▶' }}
                            </span>
                        </div>
                        <CardDescription>
                            Real-time voucher instructions JSON
                        </CardDescription>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent>
                        <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(jsonPreview, null, 2) }}</code></pre>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    </div>
</template>
