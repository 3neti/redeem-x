<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import type { RiderInstruction } from '@/types/voucher';

interface Props {
    modelValue: RiderInstruction;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: RiderInstruction];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const updateField = (field: keyof RiderInstruction, value: string | null) => {
    localValue.value = {
        ...localValue.value,
        [field]: value || null,
    };
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <AlertCircle class="h-5 w-5" />
                <CardTitle>Rider Information</CardTitle>
            </div>
            <CardDescription>
                Add additional terms, conditions, or information
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label for="rider_message">Message</Label>
                <Textarea
                    id="rider_message"
                    v-model="localValue.message"
                    placeholder="Enter terms, conditions, or additional information..."
                    rows="5"
                    :readonly="readonly"
                    :maxlength="4096"
                    @input="(e) => updateField('message', (e.target as HTMLTextAreaElement).value)"
                />
                <InputError :message="validationErrors['rider.message']" />
                <p class="text-xs text-muted-foreground">
                    Maximum 4,096 characters
                </p>
            </div>

            <div class="space-y-2">
                <Label for="rider_url">URL</Label>
                <Input
                    id="rider_url"
                    v-model="localValue.url"
                    type="url"
                    placeholder="https://example.com/terms"
                    :readonly="readonly"
                    :maxlength="2048"
                    @input="(e) => updateField('url', (e.target as HTMLInputElement).value)"
                />
                <InputError :message="validationErrors['rider.url']" />
                <p class="text-xs text-muted-foreground">
                    Link to full terms and conditions (maximum 2,048 characters)
                </p>
            </div>

            <div class="space-y-2">
                <Label for="rider_redirect_timeout">Redirect Timeout (seconds)</Label>
                <Input
                    id="rider_redirect_timeout"
                    v-model.number="localValue.redirect_timeout"
                    type="number"
                    placeholder="10"
                    :readonly="readonly"
                    :min="0"
                    :max="300"
                    @input="(e) => updateField('redirect_timeout', (e.target as HTMLInputElement).value ? Number((e.target as HTMLInputElement).value) : null)"
                />
                <InputError :message="validationErrors['rider.redirect_timeout']" />
                <p class="text-xs text-muted-foreground">
                    Time to wait before auto-redirect (0 = manual only, leave empty for default: 10s)
                </p>
            </div>

            <div class="space-y-2">
                <Label for="rider_splash">Splash Page Content</Label>
                <Textarea
                    id="rider_splash"
                    v-model="localValue.splash"
                    placeholder="Enter splash page content (supports markdown, HTML, or plain text)..."
                    rows="8"
                    :readonly="readonly"
                    :maxlength="51200"
                    @input="(e) => updateField('splash', (e.target as HTMLTextAreaElement).value)"
                />
                <InputError :message="validationErrors['rider.splash']" />
                <p class="text-xs text-muted-foreground">
                    Shown as first page before redemption flow (supports markdown, HTML, SVG, or URL). Maximum 50KB.
                </p>
            </div>

            <div class="space-y-2">
                <Label for="rider_splash_timeout">Splash Timeout (seconds)</Label>
                <Input
                    id="rider_splash_timeout"
                    v-model.number="localValue.splash_timeout"
                    type="number"
                    placeholder="5"
                    :readonly="readonly"
                    :min="0"
                    :max="60"
                    @input="(e) => updateField('splash_timeout', (e.target as HTMLInputElement).value ? Number((e.target as HTMLInputElement).value) : null)"
                />
                <InputError :message="validationErrors['rider.splash_timeout']" />
                <p class="text-xs text-muted-foreground">
                    Time to wait before auto-advancing from splash page (0 = manual only, leave empty for default: 5s)
                </p>
            </div>

            <div class="rounded-lg bg-muted p-3 text-sm">
                <p class="font-medium">ℹ️ About Rider Information:</p>
                <p class="text-muted-foreground">
                    Use this section to add disclaimers, usage restrictions, expiration policies, 
                    or any other information that should be communicated to the voucher recipient.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
