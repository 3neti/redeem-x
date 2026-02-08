<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileCode2, FileText, CheckSquare, Signal, Shield, AlertCircle, Plus, MoreVertical, Pencil, Trash2, Upload, ChevronDown, ChevronRight, GitBranch, Layers } from 'lucide-vue-next';

interface Driver {
    id: string;
    version: string;
    title: string;
    description: string | null;
    domain: string | null;
    documents_count: number;
    checklist_count: number;
    signals_count: number;
    gates_count: number;
    extends: string[];
    is_base: boolean;
    family: string | null;
    error?: boolean;
}

interface Props {
    drivers: Driver[];
}

const props = defineProps<Props>();

// Group drivers by family
const groupedDrivers = computed(() => {
    const families = new Map<string, Driver[]>();
    const ungrouped: Driver[] = [];

    for (const driver of props.drivers) {
        if (driver.family) {
            const existing = families.get(driver.family) || [];
            existing.push(driver);
            families.set(driver.family, existing);
        } else {
            ungrouped.push(driver);
        }
    }

    // Sort within each family: base drivers first, then overlays alphabetically
    for (const [family, drivers] of families) {
        drivers.sort((a, b) => {
            if (a.is_base && !b.is_base) return -1;
            if (!a.is_base && b.is_base) return 1;
            return a.id.localeCompare(b.id);
        });
    }

    // Convert to array sorted by family name
    const sortedFamilies = Array.from(families.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([family, drivers]) => ({ family, drivers }));

    return { families: sortedFamilies, ungrouped };
});

// Track which families are expanded
const expandedFamilies = ref<Set<string>>(new Set(groupedDrivers.value.families.map(f => f.family)));

const toggleFamily = (family: string) => {
    if (expandedFamilies.value.has(family)) {
        expandedFamilies.value.delete(family);
    } else {
        expandedFamilies.value.add(family);
    }
};

// Parse driver ref to get id and version
const parseDriverRef = (ref: string): { id: string; version: string } => {
    const [id, version] = ref.split('@');
    return { id, version: version || '1.0.0' };
};

const deleteDialog = ref<{ open: boolean; driver: Driver | null }>({ open: false, driver: null });
const isDeleting = ref(false);
const importDialogOpen = ref(false);
const importForm = useForm({
    file: null as File | null,
});
const fileInputRef = ref<HTMLInputElement | null>(null);

const confirmDelete = (driver: Driver) => {
    deleteDialog.value = { open: true, driver };
};

const executeDelete = () => {
    if (!deleteDialog.value.driver) return;
    isDeleting.value = true;
    
    router.delete(`/settings/envelope-drivers/${deleteDialog.value.driver.id}/${deleteDialog.value.driver.version}`, {
        onFinish: () => {
            isDeleting.value = false;
            deleteDialog.value = { open: false, driver: null };
        },
    });
};

const handleFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        importForm.file = input.files[0];
    }
};

const submitImport = () => {
    if (!importForm.file) return;
    
    importForm.post('/settings/envelope-drivers/import', {
        forceFormData: true,
        onSuccess: () => {
            importDialogOpen.value = false;
            importForm.reset();
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        },
    });
};

const closeImportDialog = () => {
    importDialogOpen.value = false;
    importForm.reset();
    importForm.clearErrors();
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Envelope Drivers" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Envelope Drivers"
                        description="Manage settlement envelope driver configurations"
                    />
                    <div class="flex items-center gap-2">
                        <Button variant="outline" @click="importDialogOpen = true">
                            <Upload class="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button as-child>
                            <a href="/settings/envelope-drivers/create">
                                <Plus class="mr-2 h-4 w-4" />
                                Create Driver
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        v-if="drivers.length === 0"
                        class="text-center py-12 text-muted-foreground"
                    >
                        <FileCode2 class="mx-auto h-12 w-12 mb-4 opacity-50" />
                        <p>No envelope drivers found.</p>
                        <p class="text-sm mt-2">
                            Drivers are defined in YAML files in the package's drivers directory.
                        </p>
                    </div>

                    <!-- Grouped Families -->
                    <div
                        v-for="{ family, drivers: familyDrivers } in groupedDrivers.families"
                        :key="family"
                        class="space-y-3"
                    >
                        <!-- Family Header -->
                        <button
                            class="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors w-full"
                            @click="toggleFamily(family)"
                        >
                            <component
                                :is="expandedFamilies.has(family) ? ChevronDown : ChevronRight"
                                class="h-4 w-4"
                            />
                            <Layers class="h-4 w-4" />
                            <span>{{ family }}</span>
                            <Badge variant="secondary" class="ml-1">{{ familyDrivers.length }}</Badge>
                        </button>

                        <!-- Family Drivers -->
                        <div v-if="expandedFamilies.has(family)" class="space-y-3 pl-6">
                            <Card
                                v-for="driver in familyDrivers"
                                :key="`${driver.id}-${driver.version}`"
                                :class="[
                                    { 'border-destructive': driver.error },
                                    { 'border-l-4 border-l-primary': driver.is_base },
                                    { 'ml-4': !driver.is_base }
                                ]"
                            >
                                <CardHeader class="pb-3">
                                    <div class="flex items-start justify-between">
                                        <div class="space-y-1">
                                            <CardTitle class="flex items-center gap-2 text-base">
                                                <FileCode2 class="h-4 w-4" />
                                                {{ driver.title }}
                                            </CardTitle>
                                            <CardDescription class="flex flex-wrap items-center gap-2">
                                                <code class="text-xs bg-muted px-1.5 py-0.5 rounded">
                                                    {{ driver.id }}@{{ driver.version }}
                                                </code>
                                                <Badge v-if="driver.is_base" variant="default" class="text-xs">
                                                    Base
                                                </Badge>
                                                <Badge v-if="driver.domain" variant="outline">
                                                    {{ driver.domain }}
                                                </Badge>
                                                <Badge v-if="driver.error" variant="destructive">
                                                    <AlertCircle class="mr-1 h-3 w-3" />
                                                    Error
                                                </Badge>
                                            </CardDescription>
                                            <!-- Extends badges -->
                                            <div v-if="driver.extends.length > 0" class="flex flex-wrap items-center gap-1 mt-1">
                                                <GitBranch class="h-3 w-3 text-muted-foreground" />
                                                <span class="text-xs text-muted-foreground">extends:</span>
                                                <a
                                                    v-for="parentRef in driver.extends"
                                                    :key="parentRef"
                                                    :href="`/settings/envelope-drivers/${parseDriverRef(parentRef).id}/${parseDriverRef(parentRef).version}`"
                                                    class="text-xs bg-muted hover:bg-muted/80 px-1.5 py-0.5 rounded text-primary hover:underline"
                                                >
                                                    {{ parentRef }}
                                                </a>
                                            </div>
                                        </div>
                                        <div v-if="!driver.error" class="flex items-center gap-2">
                                            <Button variant="outline" size="sm" as-child>
                                                <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}`">
                                                    View
                                                </a>
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem as-child>
                                                        <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}/edit`" class="flex items-center">
                                                            <Pencil class="mr-2 h-4 w-4" />
                                                            Edit
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        class="text-destructive focus:text-destructive"
                                                        @click="confirmDelete(driver)"
                                                    >
                                                        <Trash2 class="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p v-if="driver.description" class="text-sm text-muted-foreground mb-3">
                                        {{ driver.description }}
                                    </p>
                                    
                                    <div class="flex flex-wrap gap-4 text-sm">
                                        <div class="flex items-center gap-1.5 text-muted-foreground">
                                            <FileText class="h-4 w-4" />
                                            <span>{{ driver.documents_count }} docs</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-muted-foreground">
                                            <CheckSquare class="h-4 w-4" />
                                            <span>{{ driver.checklist_count }} checklist</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-muted-foreground">
                                            <Signal class="h-4 w-4" />
                                            <span>{{ driver.signals_count }} signals</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-muted-foreground">
                                            <Shield class="h-4 w-4" />
                                            <span>{{ driver.gates_count }} gates</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Ungrouped Drivers -->
                    <div v-if="groupedDrivers.ungrouped.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <FileCode2 class="h-4 w-4" />
                            <span>Other Drivers</span>
                            <Badge variant="secondary" class="ml-1">{{ groupedDrivers.ungrouped.length }}</Badge>
                        </div>
                        <Card
                            v-for="driver in groupedDrivers.ungrouped"
                            :key="`${driver.id}-${driver.version}`"
                            :class="{ 'border-destructive': driver.error }"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <CardTitle class="flex items-center gap-2 text-base">
                                            <FileCode2 class="h-4 w-4" />
                                            {{ driver.title }}
                                        </CardTitle>
                                        <CardDescription class="flex items-center gap-2">
                                            <code class="text-xs bg-muted px-1.5 py-0.5 rounded">
                                                {{ driver.id }}@{{ driver.version }}
                                            </code>
                                            <Badge v-if="driver.domain" variant="outline">
                                                {{ driver.domain }}
                                            </Badge>
                                            <Badge v-if="driver.error" variant="destructive">
                                                <AlertCircle class="mr-1 h-3 w-3" />
                                                Error
                                            </Badge>
                                        </CardDescription>
                                    </div>
                                    <div v-if="!driver.error" class="flex items-center gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}`">
                                                View
                                            </a>
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                                    <MoreVertical class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem as-child>
                                                    <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}/edit`" class="flex items-center">
                                                        <Pencil class="mr-2 h-4 w-4" />
                                                        Edit
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="confirmDelete(driver)"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p v-if="driver.description" class="text-sm text-muted-foreground mb-3">
                                    {{ driver.description }}
                                </p>
                                
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <div class="flex items-center gap-1.5 text-muted-foreground">
                                        <FileText class="h-4 w-4" />
                                        <span>{{ driver.documents_count }} docs</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-muted-foreground">
                                        <CheckSquare class="h-4 w-4" />
                                        <span>{{ driver.checklist_count }} checklist</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-muted-foreground">
                                        <Signal class="h-4 w-4" />
                                        <span>{{ driver.signals_count }} signals</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-muted-foreground">
                                        <Shield class="h-4 w-4" />
                                        <span>{{ driver.gates_count }} gates</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Delete Confirmation Dialog -->
                <AlertDialog :open="deleteDialog.open" @update:open="deleteDialog.open = $event">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete Driver?</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to delete
                                <strong>{{ deleteDialog.driver?.title }}</strong>
                                ({{ deleteDialog.driver?.id }}@{{ deleteDialog.driver?.version }})?
                                This action cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel :disabled="isDeleting">Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                variant="destructive"
                                :disabled="isDeleting"
                                @click="executeDelete"
                            >
                                {{ isDeleting ? 'Deleting...' : 'Delete' }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <!-- Import Dialog -->
                <Dialog :open="importDialogOpen" @update:open="closeImportDialog">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Import Driver</DialogTitle>
                            <DialogDescription>
                                Upload a YAML file to import an envelope driver configuration.
                                The driver ID and version will be read from the file.
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitImport">
                            <div class="space-y-4 py-4">
                                <div class="space-y-2">
                                    <Label for="file">Driver YAML File</Label>
                                    <Input
                                        id="file"
                                        ref="fileInputRef"
                                        type="file"
                                        accept=".yaml,.yml"
                                        @change="handleFileChange"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Accepts .yaml or .yml files up to 1MB
                                    </p>
                                    <p v-if="importForm.errors.file" class="text-sm text-destructive">
                                        {{ importForm.errors.file }}
                                    </p>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="closeImportDialog">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="!importForm.file || importForm.processing">
                                    {{ importForm.processing ? 'Importing...' : 'Import' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
