<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    FileCode2, FileText, CheckSquare, Signal, Shield, ArrowLeft,
    File, CheckCircle2, XCircle, Bot, User, Code, Pencil, Trash2, AlertTriangle, Download
} from 'lucide-vue-next';

interface DocumentType {
    type: string;
    title: string;
    allowed_mimes: string[];
    max_size_mb: number;
    multiple: boolean;
}

interface ChecklistItem {
    key: string;
    label: string;
    kind: string;
    doc_type?: string;
    payload_pointer?: string;
    signal_key?: string;
    required: boolean;
    review: string;
}

interface SignalDefinition {
    key: string;
    type: string;
    source: string;
    default: any;
    required: boolean;
    signal_category: string;
    system_settable: boolean;
}

interface GateDefinition {
    key: string;
    rule: string;
}

interface Driver {
    id: string;
    version: string;
    title: string;
    description: string | null;
    domain: string | null;
    issuer_type: string | null;
    documents: DocumentType[];
    checklist: ChecklistItem[];
    signals: SignalDefinition[];
    gates: GateDefinition[];
    payload: {
        schema: {
            id: string;
            format: string;
            inline: any;
        };
        storage: {
            mode: string;
            patch_strategy: string;
        } | null;
    };
    permissions: any;
    ui: any;
}

interface Props {
    driver: Driver;
    usage_count: number;
}

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const isDeleting = ref(false);

const executeDelete = () => {
    isDeleting.value = true;
    router.delete(`/settings/envelope-drivers/${props.driver.id}/${props.driver.version}`, {
        onFinish: () => {
            isDeleting.value = false;
            deleteDialogOpen.value = false;
        },
    });
};

const getKindIcon = (kind: string) => {
    switch (kind) {
        case 'document': return FileText;
        case 'payload_field': return Code;
        case 'signal': return Signal;
        default: return CheckSquare;
    }
};

const getKindLabel = (kind: string) => {
    switch (kind) {
        case 'document': return 'Document';
        case 'payload_field': return 'Payload Field';
        case 'signal': return 'Signal';
        case 'attestation': return 'Attestation';
        default: return kind;
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Driver: ${driver.title}`" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <HeadingSmall
                        :title="driver.title"
                        :description="driver.description || 'Envelope driver configuration'"
                    />
                    <div class="flex items-center gap-2">
                        <Button variant="outline" as-child>
                            <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}/export`">
                                <Download class="mr-2 h-4 w-4" />
                                Export
                            </a>
                        </Button>
                        <Button variant="outline" as-child>
                            <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}/edit`">
                                <Pencil class="mr-2 h-4 w-4" />
                                Edit
                            </a>
                        </Button>
                        <Button variant="outline" @click="deleteDialogOpen = true">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                        <Button variant="ghost" as-child>
                            <a href="/settings/envelope-drivers">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Back
                            </a>
                        </Button>
                    </div>
                </div>

                <!-- Usage Warning -->
                <Alert v-if="usage_count > 0" variant="default">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription>
                        This driver is used by <strong>{{ usage_count }}</strong> envelope(s).
                    </AlertDescription>
                </Alert>

                <!-- Driver Info Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileCode2 class="h-5 w-5" />
                            Driver Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="text-sm text-muted-foreground">ID</p>
                                <code class="text-sm font-mono">{{ driver.id }}</code>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Version</p>
                                <Badge variant="outline">{{ driver.version }}</Badge>
                            </div>
                            <div v-if="driver.domain">
                                <p class="text-sm text-muted-foreground">Domain</p>
                                <Badge>{{ driver.domain }}</Badge>
                            </div>
                            <div v-if="driver.issuer_type">
                                <p class="text-sm text-muted-foreground">Issuer Type</p>
                                <span class="text-sm">{{ driver.issuer_type }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Documents Card -->
                <Card v-if="driver.documents.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Document Types
                        </CardTitle>
                        <CardDescription>
                            {{ driver.documents.length }} document type{{ driver.documents.length !== 1 ? 's' : '' }} defined
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div 
                                v-for="doc in driver.documents" 
                                :key="doc.type"
                                class="border rounded-lg p-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-medium">{{ doc.title }}</p>
                                        <code class="text-xs text-muted-foreground">{{ doc.type }}</code>
                                    </div>
                                    <div class="flex gap-2">
                                        <Badge v-if="doc.multiple" variant="secondary">Multiple</Badge>
                                        <Badge variant="outline">{{ doc.max_size_mb }}MB max</Badge>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <Badge 
                                        v-for="mime in doc.allowed_mimes" 
                                        :key="mime"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ mime }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Checklist Card -->
                <Card v-if="driver.checklist.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckSquare class="h-5 w-5" />
                            Checklist Template
                        </CardTitle>
                        <CardDescription>
                            {{ driver.checklist.length }} checklist item{{ driver.checklist.length !== 1 ? 's' : '' }} defined
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div 
                                v-for="item in driver.checklist" 
                                :key="item.key"
                                class="flex items-center justify-between border rounded-lg p-3"
                            >
                                <div class="flex items-center gap-3">
                                    <component :is="getKindIcon(item.kind)" class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm font-medium">{{ item.label }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ item.key }}
                                            <span v-if="item.doc_type"> · doc: {{ item.doc_type }}</span>
                                            <span v-if="item.payload_pointer"> · {{ item.payload_pointer }}</span>
                                            <span v-if="item.signal_key"> · signal: {{ item.signal_key }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Badge :variant="item.required ? 'default' : 'secondary'">
                                        {{ item.required ? 'Required' : 'Optional' }}
                                    </Badge>
                                    <Badge variant="outline">{{ getKindLabel(item.kind) }}</Badge>
                                    <Badge v-if="item.review !== 'none'" variant="outline">
                                        Review: {{ item.review }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Signals Card -->
                <Card v-if="driver.signals.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Signal class="h-5 w-5" />
                            Signal Definitions
                        </CardTitle>
                        <CardDescription>
                            {{ driver.signals.length }} signal{{ driver.signals.length !== 1 ? 's' : '' }} defined
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div 
                                v-for="signal in driver.signals" 
                                :key="signal.key"
                                class="flex items-center justify-between border rounded-lg p-3"
                            >
                                <div class="flex items-center gap-3">
                                    <component 
                                        :is="signal.signal_category === 'integration' ? Bot : User" 
                                        class="h-4 w-4 text-muted-foreground" 
                                    />
                                    <div>
                                        <p class="text-sm font-medium">{{ signal.key }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Type: {{ signal.type }} · Source: {{ signal.source }} · Default: {{ signal.default }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Badge :variant="signal.required ? 'default' : 'secondary'">
                                        {{ signal.required ? 'Required' : 'Optional' }}
                                    </Badge>
                                    <Badge variant="outline">
                                        {{ signal.signal_category === 'integration' ? 'Integration' : 'Decision' }}
                                    </Badge>
                                    <Badge v-if="signal.system_settable" variant="outline">
                                        System Settable
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Gates Card -->
                <Card v-if="driver.gates.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Shield class="h-5 w-5" />
                            Gate Definitions
                        </CardTitle>
                        <CardDescription>
                            {{ driver.gates.length }} gate{{ driver.gates.length !== 1 ? 's' : '' }} defined
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div 
                                v-for="gate in driver.gates" 
                                :key="gate.key"
                                class="border rounded-lg p-3"
                            >
                                <div class="flex items-start justify-between">
                                    <p class="text-sm font-medium">{{ gate.key }}</p>
                                </div>
                                <code class="text-xs text-muted-foreground block mt-1 bg-muted p-2 rounded">
                                    {{ gate.rule }}
                                </code>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payload Schema Card -->
                <Card v-if="driver.payload.schema.inline">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Code class="h-5 w-5" />
                            Payload Schema
                        </CardTitle>
                        <CardDescription>
                            {{ driver.payload.schema.id }} ({{ driver.payload.schema.format }})
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre class="text-xs bg-muted p-4 rounded overflow-auto max-h-96">{{ JSON.stringify(driver.payload.schema.inline, null, 2) }}</pre>
                    </CardContent>
                </Card>

                <!-- Delete Confirmation Dialog -->
                <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete Driver?</AlertDialogTitle>
                            <AlertDialogDescription>
                                <template v-if="usage_count > 0">
                                    <span class="text-destructive font-medium">Warning:</span>
                                    This driver is used by {{ usage_count }} envelope(s). Deleting it may cause issues.
                                </template>
                                <template v-else>
                                    Are you sure you want to delete
                                    <strong>{{ driver.title }}</strong>
                                    ({{ driver.id }}@{{ driver.version }})?
                                    This action cannot be undone.
                                </template>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel :disabled="isDeleting">Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                variant="destructive"
                                :disabled="isDeleting || usage_count > 0"
                                @click="executeDelete"
                            >
                                {{ isDeleting ? 'Deleting...' : 'Delete' }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
