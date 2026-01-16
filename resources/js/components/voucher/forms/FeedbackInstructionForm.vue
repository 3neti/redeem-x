<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Send } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/ui/phone-input/PhoneInput.vue';
import type { FeedbackInstruction } from '@/types/voucher';

interface Props {
    modelValue: FeedbackInstruction;
    validationErrors?: Record<string, string>;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: FeedbackInstruction];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const updateField = (field: keyof FeedbackInstruction, value: string | null) => {
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
                <Send class="h-5 w-5" />
                <CardTitle>Feedback Channels</CardTitle>
            </div>
            <CardDescription>
                Configure where to send redemption notifications
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label for="feedback_email">Email Address</Label>
                <Input
                    id="feedback_email"
                    v-model="localValue.email"
                    type="email"
                    placeholder="notifications@example.com"
                    :readonly="readonly"
                    @input="(e) => updateField('email', (e.target as HTMLInputElement).value)"
                />
                <InputError :message="validationErrors['feedback.email']" />
                <p class="text-xs text-muted-foreground">
                    Receive redemption notifications via email
                </p>
            </div>

            <div class="space-y-2">
                <Label for="feedback_mobile">Mobile Number</Label>
                <PhoneInput
                    v-model="localValue.mobile"
                    :error="validationErrors['feedback.mobile']"
                    :readonly="readonly"
                    placeholder="0917 123 4567"
                />
                <p class="text-xs text-muted-foreground">
                    Receive SMS notifications (Philippine mobile number)
                </p>
            </div>

            <div class="space-y-2">
                <Label for="feedback_webhook">Webhook URL</Label>
                <Input
                    id="feedback_webhook"
                    v-model="localValue.webhook"
                    type="url"
                    placeholder="https://api.example.com/webhooks/voucher-redeemed"
                    :readonly="readonly"
                    @input="(e) => updateField('webhook', (e.target as HTMLInputElement).value)"
                />
                <InputError :message="validationErrors['feedback.webhook']" />
                <p class="text-xs text-muted-foreground">
                    Send POST requests to this URL on redemption events
                </p>
            </div>

            <div class="rounded-lg bg-muted p-3 text-sm">
                <p class="font-medium">💡 Tip:</p>
                <p class="text-muted-foreground">
                    You can configure multiple feedback channels. Leave fields empty to disable specific channels.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
