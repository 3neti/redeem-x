<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Settings, User, Building2, ChevronRight, Smartphone, Mail, MapPin, Plus, Trash2 } from 'lucide-vue-next';
import { usePhoneFormat } from '@/composables/usePhoneFormat';
import { useToast } from '@/components/ui/toast/use-toast';

interface LocationPreset {
    id: number;
    name: string;
    coordinates: { lat: number; lng: number }[];
    radius: number;
    is_default: boolean;
    centroid: { lat: number; lng: number };
}

interface Props {
    user: {
        name: string;
        email: string;
        mobile: string | null;
        avatar: string | null;
    };
    merchant: {
        name: string;
        description: string | null;
        display_name: string | null;
    } | null;
    vendorAlias: {
        alias: string;
        status: string;
        assigned_at: string;
    } | null;
    locationPresets?: LocationPreset[];
}

const props = withDefaults(defineProps<Props>(), {
    locationPresets: () => [],
});
const { formatForDisplay } = usePhoneFormat();
const { toast } = useToast();

const formattedMobile = props.user.mobile ? formatForDisplay(props.user.mobile) : 'Not set';

// Location presets state
const presets = ref<LocationPreset[]>([...props.locationPresets]);
const showAddForm = ref(false);
const addingPreset = ref(false);
const newPresetName = ref('');
const newPresetLat = ref<number | null>(null);
const newPresetLng = ref<number | null>(null);
const newPresetRadius = ref<number>(500);

const addPreset = async () => {
    if (!newPresetName.value || newPresetLat.value === null || newPresetLng.value === null) return;
    addingPreset.value = true;
    try {
        const lat = newPresetLat.value;
        const lng = newPresetLng.value;
        const offset = 0.005;
        const coordinates = [
            { lat: lat + offset, lng: lng - offset },
            { lat: lat + offset, lng: lng + offset },
            { lat: lat - offset, lng: lng + offset },
            { lat: lat - offset, lng: lng - offset },
        ];
        const { data } = await axios.post('/api/v1/location-presets', {
            name: newPresetName.value,
            coordinates,
            radius: newPresetRadius.value,
        });
        presets.value.push(data.data.preset);
        newPresetName.value = '';
        newPresetLat.value = null;
        newPresetLng.value = null;
        newPresetRadius.value = 500;
        showAddForm.value = false;
        toast({ title: 'Place saved' });
    } catch {
        toast({ title: 'Failed to save place', variant: 'destructive' });
    } finally {
        addingPreset.value = false;
    }
};

const deletePreset = async (id: number) => {
    try {
        await axios.delete(`/api/v1/location-presets/${id}`);
        presets.value = presets.value.filter(p => p.id !== id);
        toast({ title: 'Place deleted' });
    } catch {
        toast({ title: 'Failed to delete', variant: 'destructive' });
    }
};
</script>

<template>
    <PwaLayout title="Settings">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <Settings class="h-6 w-6 text-primary" />
                    <h1 class="text-lg font-semibold">Settings</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Vendor Alias Card (Prominent) -->
            <Card v-if="vendorAlias" class="bg-gradient-to-br from-primary/5 to-primary/10 border-primary/20">
                <CardContent class="pt-6">
                    <div class="text-center space-y-3">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/20 mb-2">
                            <Building2 class="h-8 w-8 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide mb-1">Your Vendor Alias</p>
                            <p class="text-3xl font-bold text-primary tracking-tight">{{ vendorAlias.alias }}</p>
                            <p class="text-xs text-muted-foreground mt-1">Use this alias for quick payments and voucher generation</p>
                        </div>
                        <div v-if="vendorAlias.status === 'active'" class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-3 py-1 rounded-full">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-600"></div>
                            Active
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Profile Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <User class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Profile</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- User Info -->
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center">
                            <User class="h-6 w-6 text-primary" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-base truncate">{{ user.name }}</div>
                            <div v-if="merchant?.display_name" class="text-xs text-muted-foreground truncate">
                                {{ merchant.display_name }}
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="border-t pt-3 space-y-3">
                        <div class="flex items-start gap-3">
                            <Mail class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-muted-foreground">Email</div>
                                <div class="text-sm font-medium truncate">{{ user.email }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Smartphone class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1">
                                <div class="text-xs text-muted-foreground">Mobile</div>
                                <div class="text-sm font-medium">{{ formattedMobile }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Merchant Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Merchant Information</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="merchant" class="space-y-3">
                        <div>
                            <div class="font-medium text-sm">{{ merchant.name }}</div>
                            <div v-if="merchant.description" class="text-xs text-muted-foreground mt-1">
                                {{ merchant.description }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-4 text-center">
                        <Building2 class="mx-auto h-8 w-8 text-muted-foreground/50" />
                        <p class="mt-2 text-sm text-muted-foreground">No merchant profile yet</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Location Presets Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <MapPin class="h-5 w-5 text-primary" />
                            <CardTitle class="text-base">Saved Places</CardTitle>
                        </div>
                        <Button variant="ghost" size="sm" @click="showAddForm = !showAddForm">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <!-- Add Form -->
                    <div v-if="showAddForm" class="space-y-3 p-3 bg-muted/50 rounded-lg">
                        <div class="space-y-1">
                            <Label class="text-xs">Name</Label>
                            <Input v-model="newPresetName" placeholder="e.g. Office" class="h-9" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-xs">Latitude</Label>
                                <Input v-model.number="newPresetLat" type="number" step="0.0001" placeholder="14.5547" class="h-9" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Longitude</Label>
                                <Input v-model.number="newPresetLng" type="number" step="0.0001" placeholder="121.0444" class="h-9" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Radius (meters)</Label>
                            <Input v-model.number="newPresetRadius" type="number" min="1" class="h-9" />
                        </div>
                        <Button size="sm" class="w-full" :disabled="!newPresetName || newPresetLat === null || newPresetLng === null || addingPreset" @click="addPreset">
                            {{ addingPreset ? 'Saving...' : 'Save Place' }}
                        </Button>
                    </div>

                    <!-- Presets List -->
                    <div v-for="preset in presets" :key="preset.id" class="flex items-center justify-between p-3 rounded-lg border">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-sm truncate">{{ preset.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ preset.coordinates.length }} points · {{ preset.radius }}m radius
                                <span v-if="preset.is_default" class="text-primary"> · System</span>
                            </div>
                        </div>
                        <Button v-if="!preset.is_default" variant="ghost" size="sm" class="shrink-0 text-destructive" @click="deletePreset(preset.id)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Empty State -->
                    <div v-if="presets.length === 0" class="py-4 text-center">
                        <MapPin class="mx-auto h-8 w-8 text-muted-foreground/50" />
                        <p class="mt-2 text-sm text-muted-foreground">No saved places yet</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Additional Settings</CardTitle>
                </CardHeader>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground text-center">
                        For advanced settings, please use the desktop version
                    </p>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
