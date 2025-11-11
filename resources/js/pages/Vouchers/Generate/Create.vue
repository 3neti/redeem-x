<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { VoucherInputFieldOption } from '@/types/voucher';
import { Head, router } from '@inertiajs/vue3';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AlertCircle, Banknote, Code, FileText, Send, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useVoucherApi } from '@/composables/useVoucherApi';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import axios from 'axios';

interface Props {
    wallet_balance: number;
    input_field_options: VoucherInputFieldOption[];
}

const props = defineProps<Props>();

const { loading, error, generateVouchers } = useVoucherApi();
const validationErrors = ref<Record<string, string>>({});

// Campaign selection
interface Campaign {
    id: number;
    name: string;
    slug: string;
    instructions: any;
}

const campaigns = ref<Campaign[]>([]);
const selectedCampaignId = ref<string>('');
const selectedCampaign = ref<Campaign | null>(null);

// Load campaigns on mount
axios.get('/api/v1/campaigns').then(response => {
    campaigns.value = response.data;
}).catch(err => console.error('Failed to load campaigns:', err));

// Watch campaign selection and populate form
watch(selectedCampaignId, async (campaignId) => {
    if (!campaignId) {
        selectedCampaign.value = null;
        return;
    }

    try {
        const response = await axios.get(`/api/v1/campaigns/${campaignId}`);
        const campaign = response.data;
        selectedCampaign.value = campaign;

        // Populate form from campaign instructions
        const inst = campaign.instructions;
        
        if (inst.cash) {
            amount.value = inst.cash.amount || 50;
        }
        
        if (inst.inputs?.fields) {
            selectedInputFields.value = [...inst.inputs.fields];
        }
        
        if (inst.cash?.validation) {
            validationSecret.value = inst.cash.validation.secret || '';
            validationMobile.value = inst.cash.validation.mobile || '';
        }
        
        if (inst.feedback) {
            feedbackEmail.value = inst.feedback.email || '';
            feedbackMobile.value = inst.feedback.mobile || '';
            feedbackWebhook.value = inst.feedback.webhook || '';
        }
        
        if (inst.rider) {
            riderMessage.value = inst.rider.message || '';
            riderUrl.value = inst.rider.url || window.location.origin;
        }
        
        count.value = inst.count || 1;
        prefix.value = inst.prefix || '';
        mask.value = inst.mask || '';
        
        // Parse TTL if present (format: P30D)
        if (inst.ttl) {
            const match = inst.ttl.match(/P(\d+)D/);
            ttlDays.value = match ? parseInt(match[1]) : 30;
        }
    } catch (err) {
        console.error('Failed to load campaign:', err);
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '#' },
    { title: 'Generate', href: '#' },
];

// Form state
const amount = ref(50);
const count = ref(1);
const prefix = ref('');
const mask = ref('');
const ttlDays = ref<number | null>(30);

const selectedInputFields = ref<string[]>([]);

const validationSecret = ref('');
const validationMobile = ref('');

const feedbackEmail = ref('');
const feedbackMobile = ref('');
const feedbackWebhook = ref('');

const riderMessage = ref('');
const riderUrl = ref(window.location.origin);

// Build instructions payload for pricing API
const instructionsForPricing = computed(() => {
    return {
        cash: {
            amount: amount.value,
            currency: 'PHP',
        },
        inputs: {
            fields: selectedInputFields.value,
        },
        feedback: {
            email: feedbackEmail.value || null,
            mobile: feedbackMobile.value || null,
            webhook: feedbackWebhook.value || null,
        },
        rider: {
            message: riderMessage.value || null,
            url: riderUrl.value || null,
        },
        count: count.value,
        prefix: prefix.value || null,
        mask: mask.value || null,
        ttl: ttlDays.value ? `P${ttlDays.value}D` : null,
    };
});

// Use live pricing API
const { breakdown: apiBreakdown, loading: pricingLoading, error: pricingError } = useChargeBreakdown(
    instructionsForPricing,
    { debounce: 500, autoCalculate: true }
);

// Cost breakdown computed from API response with fallback during loading
const costBreakdown = computed(() => {
    // If API has returned a breakdown, use it
    if (apiBreakdown.value) {
        return {
            breakdown: apiBreakdown.value.breakdown,
            total: apiBreakdown.value.total / 100, // Convert centavos to pesos
            total_formatted: apiBreakdown.value.total_formatted,
        };
    }
    
    // Fallback: simple calculation while loading or if API fails
    const baseCharge = amount.value * count.value;
    return {
        breakdown: [],
        total: baseCharge,
        total_formatted: `₱${baseCharge.toFixed(2)}`,
    };
});

const insufficientFunds = computed(
    () => costBreakdown.value.total > props.wallet_balance,
);

// Form submission data
const formData = computed(() => ({
    amount: amount.value,
    count: count.value,
    prefix: prefix.value || undefined,
    mask: mask.value || undefined,
    ttl_days: ttlDays.value,
    input_fields: selectedInputFields.value,
    validation_secret: validationSecret.value || undefined,
    validation_mobile: validationMobile.value || undefined,
    feedback_email: feedbackEmail.value || undefined,
    feedback_mobile: feedbackMobile.value || undefined,
    feedback_webhook: feedbackWebhook.value || undefined,
    rider_message: riderMessage.value || undefined,
    rider_url: riderUrl.value || undefined,
}));

const toggleInputField = (fieldValue: string) => {
    const index = selectedInputFields.value.indexOf(fieldValue);
    if (index > -1) {
        selectedInputFields.value.splice(index, 1);
    } else {
        selectedInputFields.value.push(fieldValue);
    }
};

// Live JSON preview
const jsonPreview = computed(() => {
    const data = {
        cash: {
            amount: amount.value,
            currency: 'PHP',
            validation: {
                secret: validationSecret.value || null,
                mobile: validationMobile.value || null,
                country: 'PH',
                location: null,
                radius: null,
            },
        },
        inputs: {
            fields: selectedInputFields.value,
        },
        feedback: {
            email: feedbackEmail.value || null,
            mobile: feedbackMobile.value || null,
            webhook: feedbackWebhook.value || null,
        },
        rider: {
            message: riderMessage.value || null,
            url: riderUrl.value || null,
        },
        count: count.value,
        prefix: prefix.value || null,
        mask: mask.value || null,
        ttl: ttlDays.value ? `P${ttlDays.value}D` : null,
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

const showJsonPreview = ref(false);

// Form submission
const handleSubmit = async () => {
    if (insufficientFunds.value) return;

    validationErrors.value = {};

    const result = await generateVouchers({
        amount: amount.value,
        count: count.value,
        prefix: prefix.value || undefined,
        mask: mask.value || undefined,
        ttl_days: ttlDays.value || undefined,
        input_fields: selectedInputFields.value.length > 0 ? selectedInputFields.value : undefined,
        validation_secret: validationSecret.value || undefined,
        validation_mobile: validationMobile.value || undefined,
        feedback_email: feedbackEmail.value || undefined,
        feedback_mobile: feedbackMobile.value || undefined,
        feedback_webhook: feedbackWebhook.value || undefined,
        rider_message: riderMessage.value || undefined,
        rider_url: riderUrl.value || undefined,
        campaign_id: selectedCampaignId.value || undefined,
    });

    if (result) {
        // Success - redirect to success page
        router.visit(`/vouchers/generate/success/${result.count}`);
    } else if (error.value) {
        // Handle validation errors
        // The error.value contains the error message
        console.error('Generation failed:', error.value);
    }
};
</script>

<template>
    <Head title="Generate Vouchers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Generate Vouchers"
                description="Create vouchers with custom instructions and validation rules"
            />

            <form
                @submit.prevent="handleSubmit"
                class="grid gap-6 lg:grid-cols-3"
            >
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
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
                            <!-- Campaign Template Selector -->
                            <div class="space-y-2 pb-4 border-b">
                                <Label for="campaign">Campaign Template (Optional)</Label>
                                <select
                                    id="campaign"
                                    v-model="selectedCampaignId"
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">None (manual entry)</option>
                                    <option
                                        v-for="campaign in campaigns"
                                        :key="campaign.id"
                                        :value="campaign.id.toString()"
                                    >
                                        {{ campaign.name }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Select a campaign to auto-fill the form with saved settings. You can still modify any field after selection.
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="amount">Amount (PHP)</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        name="amount"
                                        v-model.number="amount"
                                        :min="0"
                                        step="0.01"
                                        required
                                    />
                                    <InputError :message="validationErrors.amount" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="count">Quantity</Label>
                                    <Input
                                        id="count"
                                        type="number"
                                        name="count"
                                        v-model.number="count"
                                        :min="1"
                                        required
                                    />
                                    <InputError :message="validationErrors.count" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="prefix">Code Prefix (Optional)</Label>
                                    <Input
                                        id="prefix"
                                        name="prefix"
                                        v-model="prefix"
                                        placeholder="e.g., PROMO"
                                    />
                                    <InputError :message="validationErrors.prefix" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="mask">Code Mask (Optional)</Label>
                                    <Input
                                        id="mask"
                                        name="mask"
                                        v-model="mask"
                                        placeholder="e.g., ****-****"
                                    />
                                    <InputError :message="validationErrors.mask" />
                                    <p class="text-xs text-muted-foreground">
                                        Use * for random chars, - for separators (4-6
                                        asterisks)
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="ttl_days">Expiry (Days)</Label>
                                <Input
                                    id="ttl_days"
                                    type="number"
                                    name="ttl_days"
                                    v-model.number="ttlDays"
                                    :min="1"
                                    placeholder="30"
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
                                    v-for="option in input_field_options"
                                    :key="option.value"
                                    class="flex items-center space-x-2 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :id="option.value"
                                        :value="option.value"
                                        v-model="selectedInputFields"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm">
                                        {{ option.label }}
                                    </span>
                                </label>
                            </div>
                            <input
                                v-for="(field, index) in selectedInputFields"
                                :key="index"
                                type="hidden"
                                :name="`input_fields[${index}]`"
                                :value="field"
                            />
                            <InputError
                                :message="validationErrors.input_fields"
                                class="mt-2"
                            />
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
                                    name="validation_secret"
                                    v-model="validationSecret"
                                    placeholder="e.g., SECRET2025"
                                />
                                <InputError :message="validationErrors.validation_secret" />
                            </div>

                            <div class="space-y-2">
                                <Label for="validation_mobile"
                                    >Restrict to Mobile Number</Label
                                >
                                <Input
                                    id="validation_mobile"
                                    name="validation_mobile"
                                    v-model="validationMobile"
                                    placeholder="e.g., +639171234567"
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
                                    name="feedback_email"
                                    type="email"
                                    v-model="feedbackEmail"
                                    placeholder="notifications@example.com"
                                />
                                <InputError :message="validationErrors.feedback_email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="feedback_mobile">Mobile</Label>
                                <Input
                                    id="feedback_mobile"
                                    name="feedback_mobile"
                                    v-model="feedbackMobile"
                                    placeholder="+639171234567"
                                />
                                <InputError :message="validationErrors.feedback_mobile" />
                            </div>

                            <div class="space-y-2">
                                <Label for="feedback_webhook">Webhook URL</Label>
                                <Input
                                    id="feedback_webhook"
                                    name="feedback_webhook"
                                    type="url"
                                    v-model="feedbackWebhook"
                                    placeholder="https://example.com/webhook"
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
                                    name="rider_message"
                                    v-model="riderMessage"
                                    placeholder="Thank you for redeeming!"
                                />
                                <InputError :message="validationErrors.rider_message" />
                            </div>

                            <div class="space-y-2">
                                <Label for="rider_url">Redirect URL</Label>
                                <Input
                                    id="rider_url"
                                    name="rider_url"
                                    type="url"
                                    v-model="riderUrl"
                                    placeholder="https://example.com/thank-you"
                                />
                                <InputError :message="validationErrors.rider_url" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- JSON Preview -->
                    <Collapsible v-model:open="showJsonPreview">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Code class="h-5 w-5" />
                                            <CardTitle>Live JSON Preview</CardTitle>
                                        </div>
                                        <span class="text-sm text-muted-foreground">
                                            {{ showJsonPreview ? '▼' : '▶' }}
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

                <!-- Cost Preview Sidebar -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Banknote class="h-5 w-5" />
                                <CardTitle>Cost Breakdown</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Loading state -->
                            <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-4">
                                Calculating charges...
                            </div>
                            
                            <!-- Error state -->
                            <div v-else-if="pricingError" class="text-sm text-destructive text-center py-4">
                                Error calculating charges. Using fallback pricing.
                            </div>
                            
                            <!-- Breakdown from API -->
                            <div v-else class="space-y-2 text-sm">
                                <div
                                    v-for="item in costBreakdown.breakdown"
                                    :key="item.index"
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{ item.label }}</span>
                                    <span class="font-medium">{{ item.price_formatted }}</span>
                                </div>
                                
                                <!-- Fallback message if no breakdown items -->
                                <div v-if="costBreakdown.breakdown.length === 0" class="flex justify-between">
                                    <span class="text-muted-foreground">Base Charge</span>
                                    <span class="font-medium">₱{{ costBreakdown.total.toLocaleString() }}</span>
                                </div>
                            </div>

                            <Separator />

                            <div class="flex justify-between text-base font-semibold">
                                <span>Total Cost</span>
                                <span>{{ costBreakdown.total_formatted || `₱${costBreakdown.total.toLocaleString()}` }}</span>
                            </div>

                            <Separator />

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Wallet Balance</span
                                    >
                                    <span
                                        :class="
                                            insufficientFunds
                                                ? 'text-destructive'
                                                : 'text-green-600 dark:text-green-400'
                                        "
                                        >₱{{ wallet_balance.toLocaleString() }}</span
                                    >
                                </div>
                                <div class="flex justify-between font-medium">
                                    <span>After Generation</span>
                                    <span
                                        :class="
                                            insufficientFunds
                                                ? 'text-destructive'
                                                : ''
                                        "
                                        >₱{{
                                            (
                                                wallet_balance -
                                                costBreakdown.total
                                            ).toLocaleString()
                                        }}</span
                                    >
                                </div>
                            </div>

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="loading || insufficientFunds"
                            >
                                {{
                                    insufficientFunds
                                        ? 'Insufficient Funds'
                                        : loading
                                          ? 'Generating...'
                                          : 'Generate Vouchers'
                                }}
                            </Button>

                            <p
                                v-if="insufficientFunds"
                                class="text-center text-xs text-destructive"
                            >
                                Please fund your wallet before generating vouchers
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
