<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { CountrySelect, SettlementRailSelect, BankEMISelect } from '@/components/financial';
import PhoneInput from '@/components/ui/phone-input/PhoneInput.vue';

interface FieldDefinition {
    name: string;
    type: 'text' | 'email' | 'date' | 'number' | 'textarea' | 'select' | 'checkbox' | 'file' | 'recipient_country' | 'settlement_rail' | 'bank_account';
    label?: string;
    placeholder?: string;
    required?: boolean;
    options?: string[];
    validation?: string[];
    default?: any;
    min?: number;
    max?: number;
    step?: number;
    readonly?: boolean;
    disabled?: boolean;
    // UI Enhancement: Optional field metadata for visual hierarchy
    emphasis?: 'hero' | 'normal';
    group?: string;
    help_text?: string;
    variant?: 'readonly-badge' | 'normal';
}

interface AutoSyncConfig {
    enabled: boolean;
    source_field: string;
    target_field: string;
    condition_field: string;
    condition_values: string[];
    debounce_ms?: number;
}

interface Props {
    flow_id: string;
    step_index: number;
    title?: string;
    description?: string;
    fields: FieldDefinition[];
    auto_sync?: AutoSyncConfig;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Form',
    description: undefined,
    auto_sync: undefined,
});

// Form state
const formData = ref<Record<string, any>>({});
const errors = ref<Record<string, string>>({});
const submitting = ref(false);
const apiError = ref<string | null>(null);
const manualOverrides = ref<Record<string, boolean>>({});

// Initialize form data - must happen synchronously for Vue reactivity
const initializeFormData = () => {
    // Clear existing data to avoid stale values from previous step
    formData.value = {};
    
    props.fields.forEach((field) => {
        // ALWAYS set a value to avoid undefined issues
        if (field.default !== undefined && field.default !== null) {
            // Use explicitly provided default value from backend
            formData.value[field.name] = field.default;
        } else if (field.type === 'checkbox') {
            formData.value[field.name] = false;
        } else if (field.type === 'recipient_country') {
            formData.value[field.name] = 'PH'; // Fallback if backend didn't resolve
        } else if (field.type === 'settlement_rail') {
            formData.value[field.name] = null;
        } else if (field.type === 'bank_account') {
            formData.value[field.name] = 'GXCHPHM2XXX'; // Fallback if backend didn't resolve
        } else {
            formData.value[field.name] = '';
        }
    });
};

// Initialize immediately (props are available in setup)
initializeFormData();

// Re-initialize when fields change (Inertia navigation to next step)
watch(() => props.fields, () => {
    initializeFormData();
}, { deep: true });

// Debounce helper
function debounce<T extends (...args: any[]) => void>(fn: T, delay: number): T {
    let timer: ReturnType<typeof setTimeout>;
    return function (this: any, ...args: any[]) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    } as T;
}

// Auto-sync logic - setup watchers if config exists
if (props.auto_sync?.enabled) {
    const { source_field, target_field, condition_field, condition_values, debounce_ms = 1500 } = props.auto_sync;
    
    // Track if user is currently editing target field
    let isAutoSyncing = false;
    
    // Sync target field from source field
    const syncFields = debounce(() => {
        // Check if condition is met
        const conditionValue = formData.value[condition_field];
        const shouldSync = condition_values.includes(conditionValue);
        
        // Only sync if not manually overridden and condition is met
        if (!manualOverrides.value[target_field] && shouldSync && formData.value[source_field]) {
            isAutoSyncing = true;
            formData.value[target_field] = formData.value[source_field];
            // Reset flag after Vue updates
            setTimeout(() => { isAutoSyncing = false; }, 0);
        }
    }, debounce_ms);
    
    // Watch source field changes
    watch(() => formData.value[source_field], () => {
        syncFields();
    });
    
    // Track manual edits to target field
    watch(() => formData.value[target_field], (newVal, oldVal) => {
        // Don't set override if this change was from auto-sync
        if (isAutoSyncing) {
            return;
        }
        
        // Set override if value was manually changed and differs from source
        if (oldVal !== undefined && newVal !== undefined && newVal !== formData.value[source_field]) {
            manualOverrides.value[target_field] = true;
        }
    });
    
    // Reset target field and override when condition changes
    watch(() => formData.value[condition_field], (newVal, oldVal) => {
        if (newVal !== oldVal && oldVal !== undefined) {
            formData.value[target_field] = '';
            manualOverrides.value[target_field] = false;
        }
    });
}

// Computed properties
const pageTitle = computed(() => props.title || 'Form');

// Field organization by UI metadata
const summaryFields = computed(() => 
    props.fields.filter(f => f.variant === 'readonly-badge')
);

const heroFields = computed(() => 
    props.fields.filter(f => f.emphasis === 'hero' && f.variant !== 'readonly-badge')
);

const groupedFields = computed(() => {
    const groups: Record<string, FieldDefinition[]> = {};
    props.fields
        .filter(f => f.group && f.variant !== 'readonly-badge' && f.emphasis !== 'hero')
        .forEach(field => {
            const groupName = field.group!;
            if (!groups[groupName]) {
                groups[groupName] = [];
            }
            groups[groupName].push(field);
        });
    return groups;
});

const normalFields = computed(() => 
    props.fields.filter(f => 
        !f.group && 
        f.variant !== 'readonly-badge' && 
        f.emphasis !== 'hero'
    )
);

// Auto-focus hero field on mount
onMounted(() => {
    nextTick(() => {
        const heroField = heroFields.value[0];
        if (heroField) {
            const inputElement = document.getElementById(heroField.name) as HTMLInputElement;
            inputElement?.focus();
        }
    });
});

// Helper to format currency/number for badges
function formatBadgeValue(field: FieldDefinition): string {
    const value = formData.value[field.name];
    
    if (value === null || value === undefined || value === '') return '-';
    
    // Format amount as currency (50 = ₱50.00)
    if (field.name === 'amount') {
        const numericValue = typeof value === 'number' ? value : parseFloat(value);
        if (!isNaN(numericValue)) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
            }).format(numericValue);
        }
    }
    
    return String(value);
}

// Form submission
async function handleSubmit() {
    submitting.value = true;
    apiError.value = null;
    errors.value = {};

    console.log('[GenericForm] Form data before submit:', formData.value);

    try {
        router.post(
            `/form-flow/${props.flow_id}/step/${props.step_index}`,
            {
                data: formData.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    // Inertia will automatically handle navigation to next step
                    console.log('[GenericForm] Form submitted successfully');
                },
                onError: (pageErrors) => {
                    console.error('[GenericForm] Validation errors:', pageErrors);
                    
                    // Extract field-level errors from Laravel validation response
                    // Errors come in format: { 'data.field_name': 'Error message' }
                    Object.keys(pageErrors).forEach((key) => {
                        // Remove 'data.' prefix if present
                        const fieldName = key.replace(/^data\./, '');
                        errors.value[fieldName] = pageErrors[key];
                    });
                    
                    apiError.value = 'Please correct the errors below and try again.';
                },
                onFinish: () => {
                    submitting.value = false;
                },
            }
        );
    } catch (error) {
        console.error('[GenericForm] Submission error:', error);
        apiError.value = 'An unexpected error occurred. Please try again.';
        submitting.value = false;
    }
}

function handleCancel() {
    router.post(`/form-flow/${props.flow_id}/cancel`);
}

// Get field label
function getFieldLabel(field: FieldDefinition): string {
    return field.label || field.name.charAt(0).toUpperCase() + field.name.slice(1);
}

// Get field placeholder
function getFieldPlaceholder(field: FieldDefinition): string {
    return field.placeholder || `Enter ${getFieldLabel(field).toLowerCase()}`;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="container mx-auto max-w-2xl px-4 py-8">
        <!-- Error Alert -->
        <Alert v-if="apiError" variant="destructive" class="mb-6">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                {{ apiError }}
            </AlertDescription>
        </Alert>

        <!-- Form Card -->
        <Card>
            <CardHeader>
                <CardTitle>{{ title }}</CardTitle>
                <CardDescription v-if="description" class="text-base" v-html="description.replace(/voucher (\S+)/i, 'voucher <strong>$1</strong>').replace(/₱[\d,]+\.\d{2}/, '<strong>$&</strong>').replace(/from (.+)$/, 'from <strong>$1</strong>')">
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Summary Badges Section -->
                    <div v-if="summaryFields.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                        <div v-for="field in summaryFields" :key="field.name" class="flex flex-col">
                            <span class="text-xs text-muted-foreground mb-1">{{ getFieldLabel(field) }}</span>
                            <Badge variant="secondary" class="text-base py-2 px-4 justify-start w-full">
                                <span class="font-bold">{{ formatBadgeValue(field) }}</span>
                            </Badge>
                        </div>
                    </div>

                    <!-- Hero Fields Section -->
                    <div v-if="heroFields.length > 0" class="space-y-6 mb-8">
                        <div v-for="field in heroFields" :key="field.name" class="space-y-3">
                            <Label 
                                :for="field.name" 
                                :class="[
                                    'text-2xl font-bold',
                                    { 'text-destructive': errors[field.name] }
                                ]"
                            >
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            
                            <!-- Hero field input with larger styling -->
                            <Input
                                v-if="field.type === 'text' || field.type === 'email'"
                                :id="field.name"
                                v-model="formData[field.name]"
                                :type="field.type"
                                :placeholder="getFieldPlaceholder(field)"
                                :required="field.required"
                                :readonly="field.readonly"
                                :disabled="field.disabled"
                                :class="[
                                    'py-4 text-lg ring-2 ring-primary/20 focus-visible:ring-4 focus-visible:ring-primary/30 transition-all',
                                    { 'border-destructive ring-destructive/20': errors[field.name] }
                                ]"
                                autofocus
                            />
                            
                            <!-- Hero phone input with larger styling -->
                            <PhoneInput
                                v-else-if="field.type === 'tel'"
                                v-model="formData[field.name]"
                                :error="errors[field.name]"
                                :placeholder="getFieldPlaceholder(field)"
                                :required="field.required"
                                :readonly="field.readonly"
                                :disabled="field.disabled"
                                :class="'ring-2 ring-primary/20 focus-within:ring-4 focus-within:ring-primary/30 transition-all'"
                                autofocus
                            />
                            
                            <!-- Help text -->
                            <p v-if="field.help_text" class="text-sm text-muted-foreground">
                                {{ field.help_text }}
                            </p>
                            
                            <!-- Error message -->
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>
                    </div>

                    <!-- Separator before grouped/normal fields -->
                    <div v-if="(heroFields.length > 0) && (Object.keys(groupedFields).length > 0 || normalFields.length > 0)" class="relative my-8">
                        <Separator />
                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-background px-4">
                            <span class="text-sm text-muted-foreground font-medium">Bank Account Details</span>
                        </div>
                    </div>

                    <!-- Grouped Fields Sections -->
                    <div v-for="(groupFields, groupName) in groupedFields" :key="groupName" class="space-y-4">
                        <fieldset class="border rounded-lg p-4 bg-muted/5">
                            <legend class="text-sm font-medium text-muted-foreground px-2">{{ groupName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</legend>
                            
                            <div class="space-y-4 mt-2">
                                <div v-for="field in groupFields" :key="field.name" class="space-y-2">
                                    <!-- Render field with standard template below -->
                                    <template v-if="field.type === 'text' || field.type === 'email' || field.type === 'date' || field.type === 'number'">
                                        <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                            {{ getFieldLabel(field) }}
                                            <span v-if="field.required" class="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            :id="field.name"
                                            v-model="formData[field.name]"
                                            :type="field.type"
                                            :placeholder="getFieldPlaceholder(field)"
                                            :required="field.required"
                                            :min="field.min"
                                            :max="field.max"
                                            :step="field.step"
                                            :readonly="field.readonly"
                                            :disabled="field.disabled"
                                            :class="{ 'border-destructive': errors[field.name] }"
                                        />
                                        <p v-if="field.help_text" class="text-xs text-muted-foreground">
                                            {{ field.help_text }}
                                        </p>
                                        <p v-if="errors[field.name]" class="text-sm text-destructive">
                                            {{ errors[field.name] }}
                                        </p>
                                    </template>
                                    
                                    <!-- Phone Input -->
                                    <template v-else-if="field.type === 'tel'">
                                        <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                            {{ getFieldLabel(field) }}
                                            <span v-if="field.required" class="text-destructive">*</span>
                                        </Label>
                                        <PhoneInput
                                            v-model="formData[field.name]"
                                            :error="errors[field.name]"
                                            :placeholder="getFieldPlaceholder(field)"
                                            :required="field.required"
                                            :readonly="field.readonly"
                                            :disabled="field.disabled"
                                        />
                                        <p v-if="field.help_text" class="text-xs text-muted-foreground">
                                            {{ field.help_text }}
                                        </p>
                                        <p v-if="errors[field.name]" class="text-sm text-destructive">
                                            {{ errors[field.name] }}
                                        </p>
                                    </template>

                                    <!-- Bank Account (BankEMISelect) -->
                                    <template v-else-if="field.type === 'bank_account'">
                                        <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                            {{ getFieldLabel(field) }}
                                            <span v-if="field.required" class="text-destructive">*</span>
                                        </Label>
                                        <BankEMISelect
                                            v-model="formData[field.name]"
                                            :settlement-rail="formData.settlement_rail || null"
                                            :disabled="field.disabled || field.readonly"
                                        />
                                        <p v-if="field.help_text" class="text-xs text-muted-foreground">
                                            {{ field.help_text }}
                                        </p>
                                        <p v-if="errors[field.name]" class="text-sm text-destructive">
                                            {{ errors[field.name] }}
                                        </p>
                                    </template>

                                    <!-- Settlement Rail -->
                                    <template v-else-if="field.type === 'settlement_rail'">
                                        <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                            {{ getFieldLabel(field) }}
                                            <span v-if="field.required" class="text-destructive">*</span>
                                        </Label>
                                        <SettlementRailSelect
                                            v-model="formData[field.name]"
                                            :amount="formData.amount || 0"
                                            :bank-code="formData.bank_account || null"
                                            :disabled="field.disabled || field.readonly"
                                        />
                                        <p v-if="field.help_text" class="text-xs text-muted-foreground">
                                            {{ field.help_text }}
                                        </p>
                                        <p v-if="errors[field.name]" class="text-sm text-destructive">
                                            {{ errors[field.name] }}
                                        </p>
                                    </template>

                                    <!-- Recipient Country -->
                                    <template v-else-if="field.type === 'recipient_country'">
                                        <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                            {{ getFieldLabel(field) }}
                                            <span v-if="field.required" class="text-destructive">*</span>
                                        </Label>
                                        <CountrySelect
                                            v-model="formData[field.name]"
                                            :disabled="field.disabled || field.readonly"
                                        />
                                        <p v-if="field.help_text" class="text-xs text-muted-foreground">
                                            {{ field.help_text }}
                                        </p>
                                        <p v-if="errors[field.name]" class="text-sm text-destructive">
                                            {{ errors[field.name] }}
                                        </p>
                                    </template>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Normal Fields (non-grouped, non-hero, non-badge) -->
                    <div v-if="normalFields.length > 0" class="space-y-4">
                        <div v-for="field in normalFields" :key="field.name" class="space-y-2">
                        <!-- Text Input -->
                        <div v-if="field.type === 'text' || field.type === 'email' || field.type === 'date' || field.type === 'number'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <Input
                                :id="field.name"
                                v-model="formData[field.name]"
                                :type="field.type"
                                :placeholder="getFieldPlaceholder(field)"
                                :required="field.required"
                                :min="field.min"
                                :max="field.max"
                                :step="field.step"
                                :readonly="field.readonly"
                                :disabled="field.disabled"
                                :class="{ 'border-destructive': errors[field.name] }"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>
                        
                        <!-- Phone Input -->
                        <div v-else-if="field.type === 'tel'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <PhoneInput
                                v-model="formData[field.name]"
                                :error="errors[field.name]"
                                :placeholder="getFieldPlaceholder(field)"
                                :required="field.required"
                                :readonly="field.readonly"
                                :disabled="field.disabled"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Textarea -->
                        <div v-else-if="field.type === 'textarea'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <Textarea
                                :id="field.name"
                                v-model="formData[field.name]"
                                :placeholder="getFieldPlaceholder(field)"
                                :required="field.required"
                                :readonly="field.readonly"
                                :disabled="field.disabled"
                                :class="{ 'border-destructive': errors[field.name] }"
                                rows="4"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Select -->
                        <div v-else-if="field.type === 'select'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <Select v-model="formData[field.name]" :required="field.required" :disabled="field.disabled">
                                <SelectTrigger :class="{ 'border-destructive': errors[field.name] }">
                                    <SelectValue :placeholder="`Select ${getFieldLabel(field).toLowerCase()}`" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in field.options || []"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Checkbox -->
                        <div v-else-if="field.type === 'checkbox'" class="flex items-center space-x-2">
                            <Checkbox
                                :id="field.name"
                                :checked="formData[field.name]"
                                @update:modelValue="(value) => {
                                    console.log(`[GenericForm] Checkbox '${field.name}' changed:`, value, 'type:', typeof value);
                                    formData[field.name] = value;
                                    console.log(`[GenericForm] formData['${field.name}'] is now:`, formData[field.name]);
                                }"
                                :required="field.required"
                                :disabled="field.disabled"
                                :class="{ 'border-destructive': errors[field.name] }"
                            />
                            <Label
                                :for="field.name"
                                :class="{ 'text-destructive': errors[field.name] }"
                                class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                            >
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <p v-if="errors[field.name]" class="text-sm text-destructive ml-6">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- File Input -->
                        <div v-else-if="field.type === 'file'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <Input
                                :id="field.name"
                                type="file"
                                :required="field.required"
                                :class="{ 'border-destructive': errors[field.name] }"
                                @change="(e) => {
                                    const target = e.target as HTMLInputElement;
                                    formData[field.name] = target.files?.[0] || null;
                                }"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Recipient Country -->
                        <div v-else-if="field.type === 'recipient_country'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <CountrySelect
                                v-model="formData[field.name]"
                                :disabled="field.disabled || field.readonly"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Settlement Rail -->
                        <div v-else-if="field.type === 'settlement_rail'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <SettlementRailSelect
                                v-model="formData[field.name]"
                                :amount="formData.amount || 0"
                                :bank-code="formData.bank_account || null"
                                :disabled="field.disabled || field.readonly"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>

                        <!-- Bank/EMI Account -->
                        <div v-else-if="field.type === 'bank_account'">
                            <Label :for="field.name" :class="{ 'text-destructive': errors[field.name] }">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-destructive">*</span>
                            </Label>
                            <BankEMISelect
                                v-model="formData[field.name]"
                                :settlement-rail="formData.settlement_rail || null"
                                :disabled="field.disabled || field.readonly"
                            />
                            <p v-if="errors[field.name]" class="text-sm text-destructive">
                                {{ errors[field.name] }}
                            </p>
                        </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="handleCancel"
                            :disabled="submitting"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="submitting"
                        >
                            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin mr-2" />
                            {{ submitting ? 'Submitting...' : 'Continue' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
