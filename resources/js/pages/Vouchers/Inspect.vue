<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Search, AlertCircle, Info } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import VoucherMetadataDisplay from '@/components/voucher/VoucherMetadataDisplay.vue';
import VoucherInstructionsDisplay from '@/components/voucher/VoucherInstructionsDisplay.vue';
import type { InspectResponse } from '@/types/voucher';

const code = ref('');
const loading = ref(false);
const error = ref('');
const voucherData = ref<InspectResponse | null>(null);

const hasSearched = computed(() => voucherData.value !== null || error.value !== '');

const statusBadgeVariant = computed(() => {
    if (!voucherData.value) return 'secondary';
    
    const status = voucherData.value.status;
    switch (status) {
        case 'active':
            return 'default';
        case 'redeemed':
            return 'secondary';
        case 'expired':
            return 'destructive';
        case 'scheduled':
            return 'outline';
        default:
            return 'secondary';
    }
});

const statusText = computed(() => {
    if (!voucherData.value) return '';
    return voucherData.value.status?.charAt(0).toUpperCase() + voucherData.value.status?.slice(1);
});

const inspect = async () => {
    if (!code.value.trim()) {
        error.value = 'Please enter a voucher code';
        return;
    }

    loading.value = true;
    error.value = '';
    voucherData.value = null;

    try {
        const response = await fetch(`/api/v1/vouchers/${code.value.trim()}/inspect`);
        const data = await response.json();

        if (data.success) {
            voucherData.value = data;
        } else {
            error.value = data.message || 'Failed to fetch voucher information';
        }
    } catch (err) {
        error.value = 'Network error. Please try again.';
    } finally {
        loading.value = false;
    }
};

const handleSubmit = (e: Event) => {
    e.preventDefault();
    inspect();
};

const reset = () => {
    code.value = '';
    error.value = '';
    voucherData.value = null;
};
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Inspect Voucher" />
        
        <div class="w-full max-w-2xl space-y-6">
            <!-- Logo and Header -->
            <div class="flex flex-col items-center gap-4">
                <AppLogo />
                <div class="text-center">
                    <h1 class="text-3xl font-bold">Voucher Inspector</h1>
                    <p class="text-sm text-muted-foreground mt-2">
                        View voucher metadata and transparency information
                    </p>
                </div>
            </div>

            <!-- Search Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Enter Voucher Code</CardTitle>
                    <CardDescription>
                        Enter a voucher code to view its metadata and origin information
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit="handleSubmit" class="flex gap-2">
                        <Input
                            v-model="code"
                            placeholder="e.g., INSP-6DSP"
                            class="flex-1"
                            :disabled="loading"
                        />
                        <Button type="submit" :disabled="loading || !code.trim()">
                            <Search class="h-4 w-4 mr-2" />
                            {{ loading ? 'Inspecting...' : 'Inspect' }}
                        </Button>
                    </form>

                    <!-- Error Message -->
                    <div v-if="error" class="mt-4 flex items-center gap-2 text-sm text-destructive">
                        <AlertCircle class="h-4 w-4" />
                        {{ error }}
                    </div>
                </CardContent>
            </Card>

            <!-- Results -->
            <div v-if="hasSearched && !error">
                <!-- Voucher Status -->
                <Card class="mb-6">
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">{{ voucherData.code }}</h3>
                                <p class="text-sm text-muted-foreground">Voucher Code</p>
                            </div>
                            <Badge :variant="statusBadgeVariant">
                                {{ statusText }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tabs: Instructions & System Info -->
                <Tabs default-value="instructions" class="mb-6">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="instructions">Instructions</TabsTrigger>
                        <TabsTrigger value="system-info">System Info</TabsTrigger>
                    </TabsList>
                    
                    <!-- Instructions Tab -->
                    <TabsContent value="instructions" class="mt-6">
                        <VoucherInstructionsDisplay 
                            v-if="voucherData.instructions"
                            :instructions="voucherData.instructions"
                            :voucher-status="voucherData.status"
                        />
                        
                        <!-- Old vouchers without instructions -->
                        <Card v-else>
                            <CardContent class="pt-6">
                                <div class="flex items-start gap-3">
                                    <Info class="h-5 w-5 text-muted-foreground flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p class="text-sm font-medium">No instructions available</p>
                                        <p class="text-sm text-muted-foreground mt-1">
                                            This voucher was created before detailed instructions were tracked.
                                            Please contact the issuer for redemption information.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    
                    <!-- System Info Tab -->
                    <TabsContent value="system-info" class="mt-6">
                        <VoucherMetadataDisplay 
                            :metadata="voucherData.metadata" 
                            :show-all-fields="true"
                        />
                    </TabsContent>
                </Tabs>

                <!-- Actions -->
                <div class="mt-6 flex justify-center">
                    <Button variant="outline" @click="reset">
                        Inspect Another Voucher
                    </Button>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-xs text-muted-foreground">
                <p>This information is publicly available for transparency</p>
            </div>
        </div>
    </div>
</template>
