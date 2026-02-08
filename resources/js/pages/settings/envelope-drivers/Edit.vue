<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ArrowLeft, FileCode2, ChevronDown, Save, Loader2, FileText, CheckSquare, Signal, Shield, Code, AlertTriangle, GitBranch } from 'lucide-vue-next';
import { DocumentTypeEditor, ChecklistItemEditor, SignalDefinitionEditor, GateDefinitionEditor, PayloadSchemaEditor } from '@/components/envelope-driver';

interface Driver {
    id: string;
    version: string;
    title: string;
    description: string | null;
    domain: string | null;
    issuer_type: string | null;
    documents: any[];
    checklist: any[];
    signals: any[];
    gates: any[];
    payload_schema: any;
}

interface Props {
    driver: Driver;
    usage_count: number;
}

const props = defineProps<Props>();

const form = reactive({
    title: props.driver.title,
    description: props.driver.description || '',
    domain: props.driver.domain || '',
    issuer_type: props.driver.issuer_type || '',
    payload_schema: props.driver.payload_schema || { type: 'object', properties: {}, required: [] },
    documents: props.driver.documents || [],
    checklist: props.driver.checklist || [],
    signals: props.driver.signals || [],
    gates: props.driver.gates || [],
    save_as_new_version: false,
    new_version: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const openSections = reactive({
    basic: true,
    payload: true,
    documents: props.driver.documents.length > 0,
    checklist: props.driver.checklist.length > 0,
    signals: props.driver.signals.length > 0,
    gates: props.driver.gates.length > 0,
});

const submit = () => {
    isSubmitting.value = true;
    errors.value = {};
    
    router.put(`/settings/envelope-drivers/${props.driver.id}/${props.driver.version}`, form, {
        onError: (errs) => {
            errors.value = errs;
            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const hasErrors = computed(() => Object.keys(errors.value).length > 0);
const hasUsage = computed(() => props.usage_count > 0);

// Suggest next version
const suggestedVersion = computed(() => {
    const parts = props.driver.version.split('.');
    parts[2] = String(parseInt(parts[2]) + 1);
    return parts.join('.');
});
</script>

<template>
    <AppLayout>
        <Head :title="`Edit Driver: ${driver.title}`" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6 max-w-4xl">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <HeadingSmall
                        :title="`Edit: ${driver.title}`"
                        :description="`${driver.id}@${driver.version}`"
                    />
                    <Button variant="outline" as-child>
                        <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}`">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </a>
                    </Button>
                </div>

                <!-- Usage Warning -->
                <Alert v-if="hasUsage" variant="warning">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertTitle>Driver in Use</AlertTitle>
                    <AlertDescription>
                        This driver is used by {{ usage_count }} envelope(s). Consider saving changes as a new version to avoid breaking existing envelopes.
                    </AlertDescription>
                </Alert>

                <!-- Error Alert -->
                <Alert v-if="hasErrors" variant="destructive">
                    <AlertDescription>
                        Please correct the errors below before saving.
                    </AlertDescription>
                </Alert>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Driver Identity (read-only) -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Driver Identity</CardTitle>
                            <CardDescription>These values cannot be changed</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-1">
                                    <Label class="text-muted-foreground">Driver ID</Label>
                                    <p class="font-mono text-sm">{{ driver.id }}</p>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-muted-foreground">Version</Label>
                                    <p class="font-mono text-sm">{{ driver.version }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Save as New Version Option -->
                    <Card>
                        <CardContent class="pt-4">
                            <div class="flex items-start space-x-4">
                                <Switch
                                    :checked="form.save_as_new_version"
                                    @update:checked="form.save_as_new_version = $event"
                                />
                                <div class="space-y-1 flex-1">
                                    <Label class="flex items-center gap-2">
                                        <GitBranch class="h-4 w-4" />
                                        Save as New Version
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        Create a new version instead of updating the existing one
                                    </p>
                                    <Input
                                        v-if="form.save_as_new_version"
                                        v-model="form.new_version"
                                        :placeholder="suggestedVersion"
                                        class="mt-2 max-w-xs"
                                        :class="{ 'border-destructive': errors.new_version }"
                                    />
                                    <p v-if="errors.new_version" class="text-xs text-destructive">{{ errors.new_version }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Basic Info Section -->
                    <Collapsible v-model:open="openSections.basic" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <FileCode2 class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Basic Information</h3>
                                    <p class="text-sm text-muted-foreground">Driver metadata</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 space-y-4 border-t">
                                <div class="space-y-2">
                                    <Label for="title">Title <span class="text-destructive">*</span></Label>
                                    <Input id="title" v-model="form.title" :class="{ 'border-destructive': errors.title }" />
                                    <p v-if="errors.title" class="text-xs text-destructive">{{ errors.title }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Textarea id="description" v-model="form.description" rows="2" />
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="domain">Domain</Label>
                                        <Input id="domain" v-model="form.domain" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="issuer_type">Issuer Type</Label>
                                        <Input id="issuer_type" v-model="form.issuer_type" />
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Payload Schema Section -->
                    <Collapsible v-model:open="openSections.payload" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <Code class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Payload Schema</h3>
                                    <p class="text-sm text-muted-foreground">Define payload structure</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <PayloadSchemaEditor v-model="form.payload_schema" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Documents Section -->
                    <Collapsible v-model:open="openSections.documents" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Document Types</h3>
                                    <p class="text-sm text-muted-foreground">{{ form.documents.length }} type(s)</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <DocumentTypeEditor v-model="form.documents" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Checklist Section -->
                    <Collapsible v-model:open="openSections.checklist" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <CheckSquare class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Checklist Template</h3>
                                    <p class="text-sm text-muted-foreground">{{ form.checklist.length }} item(s)</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <ChecklistItemEditor v-model="form.checklist" :document-types="form.documents" :signals="form.signals" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Signals Section -->
                    <Collapsible v-model:open="openSections.signals" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <Signal class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Signal Definitions</h3>
                                    <p class="text-sm text-muted-foreground">{{ form.signals.length }} signal(s)</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <SignalDefinitionEditor v-model="form.signals" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Gates Section -->
                    <Collapsible v-model:open="openSections.gates" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <Shield class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Gate Definitions</h3>
                                    <p class="text-sm text-muted-foreground">{{ form.gates.length }} gate(s)</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <GateDefinitionEditor v-model="form.gates" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Submit Button -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Button variant="outline" type="button" as-child>
                            <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}`">Cancel</a>
                        </Button>
                        <Button type="submit" :disabled="isSubmitting">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            {{ form.save_as_new_version ? 'Save as New Version' : 'Update Driver' }}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
