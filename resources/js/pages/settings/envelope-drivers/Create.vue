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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ArrowLeft, FileCode2, ChevronDown, Save, Loader2, Sparkles, FileText, CheckSquare, Signal, Shield, Code } from 'lucide-vue-next';
import { DocumentTypeEditor, ChecklistItemEditor, SignalDefinitionEditor, GateDefinitionEditor, PayloadSchemaEditor } from '@/components/envelope-driver';

interface Template {
    id: string;
    title: string;
    description: string;
    data: any;
}

interface Props {
    templates: Template[];
}

const props = defineProps<Props>();

const form = reactive({
    id: '',
    version: '1.0.0',
    title: '',
    description: '',
    domain: '',
    issuer_type: '',
    payload_schema: { type: 'object', properties: {}, required: [] as string[] },
    documents: [] as any[],
    checklist: [] as any[],
    signals: [] as any[],
    gates: [] as any[],
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const openSections = reactive({
    basic: true,
    payload: false,
    documents: false,
    checklist: false,
    signals: false,
    gates: false,
});

const applyTemplate = (templateId: string) => {
    const template = props.templates.find(t => t.id === templateId);
    if (template) {
        form.payload_schema = template.data.payload_schema || form.payload_schema;
        form.documents = template.data.documents || [];
        form.checklist = template.data.checklist || [];
        form.signals = template.data.signals || [];
        form.gates = template.data.gates || [];
        
        // Open all sections to show applied content
        openSections.payload = true;
        openSections.documents = form.documents.length > 0;
        openSections.checklist = form.checklist.length > 0;
        openSections.signals = form.signals.length > 0;
        openSections.gates = form.gates.length > 0;
    }
};

const submit = () => {
    isSubmitting.value = true;
    errors.value = {};
    
    router.post('/settings/envelope-drivers', form, {
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
</script>

<template>
    <AppLayout>
        <Head title="Create Envelope Driver" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6 max-w-4xl">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <HeadingSmall
                        title="Create Envelope Driver"
                        description="Define a new settlement envelope driver configuration"
                    />
                    <Button variant="outline" as-child>
                        <a href="/settings/envelope-drivers">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </a>
                    </Button>
                </div>

                <!-- Template Selector -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Sparkles class="h-4 w-4" />
                            Start from Template
                        </CardTitle>
                        <CardDescription>
                            Choose a template to pre-populate the form with common configurations
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 md:grid-cols-3">
                            <button
                                v-for="template in templates"
                                :key="template.id"
                                @click="applyTemplate(template.id)"
                                class="text-left p-3 border rounded-lg hover:bg-muted transition-colors"
                            >
                                <p class="font-medium text-sm">{{ template.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ template.description }}</p>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Error Alert -->
                <Alert v-if="hasErrors" variant="destructive">
                    <AlertDescription>
                        Please correct the errors below before saving.
                    </AlertDescription>
                </Alert>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Basic Info Section -->
                    <Collapsible v-model:open="openSections.basic" class="border rounded-lg">
                        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50">
                            <div class="flex items-center gap-3">
                                <FileCode2 class="h-5 w-5 text-muted-foreground" />
                                <div class="text-left">
                                    <h3 class="font-medium">Basic Information</h3>
                                    <p class="text-sm text-muted-foreground">Driver identity and metadata</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 space-y-4 border-t">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="id">
                                            Driver ID
                                            <span class="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="id"
                                            v-model="form.id"
                                            placeholder="e.g., vendor.payment"
                                            :class="{ 'border-destructive': errors.id }"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            Unique identifier. Use dots for namespacing (e.g., vendor.payment)
                                        </p>
                                        <p v-if="errors.id" class="text-xs text-destructive">{{ errors.id }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="version">
                                            Version
                                            <span class="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="version"
                                            v-model="form.version"
                                            placeholder="e.g., 1.0.0"
                                            :class="{ 'border-destructive': errors.version }"
                                        />
                                        <p class="text-xs text-muted-foreground">Semantic version (major.minor.patch)</p>
                                        <p v-if="errors.version" class="text-xs text-destructive">{{ errors.version }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="title">
                                        Title
                                        <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="e.g., Vendor Payment Verification"
                                        :class="{ 'border-destructive': errors.title }"
                                    />
                                    <p v-if="errors.title" class="text-xs text-destructive">{{ errors.title }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe the purpose of this driver..."
                                        rows="2"
                                    />
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="domain">Domain</Label>
                                        <Input
                                            id="domain"
                                            v-model="form.domain"
                                            placeholder="e.g., payment, verification"
                                        />
                                        <p class="text-xs text-muted-foreground">Business domain category</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="issuer_type">Issuer Type</Label>
                                        <Input
                                            id="issuer_type"
                                            v-model="form.issuer_type"
                                            placeholder="e.g., vendor, internal"
                                        />
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
                                    <p class="text-sm text-muted-foreground">Define the data structure for envelope payloads</p>
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
                                    <p class="text-sm text-muted-foreground">{{ form.documents.length }} document type(s) defined</p>
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
                                    <p class="text-sm text-muted-foreground">{{ form.checklist.length }} item(s) defined</p>
                                </div>
                            </div>
                            <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]_&]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="px-4 pb-4 pt-2 border-t">
                                <ChecklistItemEditor
                                    v-model="form.checklist"
                                    :document-types="form.documents"
                                    :signals="form.signals"
                                />
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
                                    <p class="text-sm text-muted-foreground">{{ form.signals.length }} signal(s) defined</p>
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
                                    <p class="text-sm text-muted-foreground">{{ form.gates.length }} gate(s) defined</p>
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
                            <a href="/settings/envelope-drivers">Cancel</a>
                        </Button>
                        <Button type="submit" :disabled="isSubmitting">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            Create Driver
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
