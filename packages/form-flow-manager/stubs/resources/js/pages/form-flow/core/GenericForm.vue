<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Loader2 } from 'lucide-vue-next';

interface FieldDefinition {
    name: string;
    type: 'text' | 'email' | 'date' | 'number' | 'textarea' | 'select' | 'checkbox' | 'file';
    label?: string;
    placeholder?: string;
    required?: boolean;
    options?: string[];
    validation?: string[];
}

interface Props {
    flow_id: string;
    step_index: number;
    title?: string;
    description?: string;
    fields: FieldDefinition[];
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Form',
    description: undefined,
});

// Form state
const formData = ref<Record<string, any>>({});
const errors = ref<Record<string, string>>({});
const submitting = ref(false);
const apiError = ref<string | null>(null);

// Initialize form data with default values
props.fields.forEach((field) => {
    formData.value[field.name] = field.type === 'checkbox' ? false : '';
});

// Computed properties
const pageTitle = computed(() => props.title || 'Form');

// Form submission
async function handleSubmit() {
    submitting.value = true;
    apiError.value = null;
    errors.value = {};

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
                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Dynamic Fields -->
                    <div v-for="field in fields" :key="field.name" class="space-y-2">
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
                                :class="{ 'border-destructive': errors[field.name] }"
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
                            <Select v-model="formData[field.name]" :required="field.required">
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
                                v-model:checked="formData[field.name]"
                                :required="field.required"
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
