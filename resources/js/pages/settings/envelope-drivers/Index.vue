<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FileCode2, FileText, CheckSquare, Signal, Shield, AlertCircle, ExternalLink } from 'lucide-vue-next';

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
    error?: boolean;
}

interface Props {
    drivers: Driver[];
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head title="Envelope Drivers" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Envelope Drivers"
                    description="Settlement envelope driver configurations (read-only)"
                />

                <div class="space-y-4">
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

                    <Card
                        v-for="driver in drivers"
                        :key="`${driver.id}-${driver.version}`"
                        :class="{ 'border-destructive': driver.error }"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <CardTitle class="flex items-center gap-2">
                                        <FileCode2 class="h-5 w-5" />
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
                                <Button 
                                    v-if="!driver.error"
                                    variant="outline" 
                                    size="sm" 
                                    as-child
                                >
                                    <a :href="`/settings/envelope-drivers/${driver.id}/${driver.version}`">
                                        View Details
                                        <ExternalLink class="ml-2 h-3 w-3" />
                                    </a>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p v-if="driver.description" class="text-sm text-muted-foreground mb-4">
                                {{ driver.description }}
                            </p>
                            
                            <div class="flex flex-wrap gap-4 text-sm">
                                <div class="flex items-center gap-1.5 text-muted-foreground">
                                    <FileText class="h-4 w-4" />
                                    <span>{{ driver.documents_count }} documents</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-muted-foreground">
                                    <CheckSquare class="h-4 w-4" />
                                    <span>{{ driver.checklist_count }} checklist items</span>
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
        </SettingsLayout>
    </AppLayout>
</template>
