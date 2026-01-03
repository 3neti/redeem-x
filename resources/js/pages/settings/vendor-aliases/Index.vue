<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { store, update, destroy, searchUsers } from '@/actions/App/Http/Controllers/Settings/VendorAliasController';
import { MoreHorizontal, UserPlus } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface VendorAlias {
    id: number;
    alias: string;
    status: 'active' | 'revoked' | 'reserved';
    assigned_at: string;
    notes: string | null;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    assignedBy: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    aliases: {
        data: VendorAlias[];
        links: any[];
        meta: any;
    };
    filters?: {
        search?: string;
        status?: string;
    };
    config: {
        min_length: number;
        max_length: number;
    };
}

const props = defineProps<Props>();

const validationHint = computed(() => 
    `${props.config.min_length}-${props.config.max_length} characters, uppercase letters and digits only`
);

const isOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

const form = useForm({
    user_id: null as number | null,
    alias: '',
    notes: '',
});

const selectedUser = ref<any>(null);

// Debounced user search
let searchTimeout: NodeJS.Timeout;
const handleUserSearch = async (query: string) => {
    searchQuery.value = query;
    
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            const response = await fetch(`${searchUsers.url()}?query=${encodeURIComponent(query)}`);
            searchResults.value = await response.json();
        } catch (error) {
            console.error('User search failed:', error);
        } finally {
            isSearching.value = false;
        }
    }, 300);
};

const selectUser = (user: any) => {
    selectedUser.value = user;
    form.user_id = user.id;
    searchQuery.value = '';
    searchResults.value = [];
};

const submitForm = () => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedUser.value = null;
            isOpen.value = false;
        },
    });
};

const revokeAlias = (alias: VendorAlias) => {
    if (confirm(`Are you sure you want to revoke the alias "${alias.alias}"? The user will no longer be able to redeem vouchers with this alias.`)) {
        router.patch(update.url({ vendor_alias: alias.id }), {
            status: 'revoked',
        }, {
            preserveScroll: true,
        });
    }
};

const reactivateAlias = (alias: VendorAlias) => {
    if (confirm(`Reactivate the alias "${alias.alias}"? The user will be able to redeem vouchers again.`)) {
        router.patch(update.url({ vendor_alias: alias.id }), {
            status: 'active',
        }, {
            preserveScroll: true,
        });
    }
};

const deleteAlias = (alias: VendorAlias) => {
    if (confirm(`Are you sure you want to delete the alias "${alias.alias}"? This action cannot be undone.`)) {
        router.delete(destroy.url({ vendor_alias: alias.id }), {
            preserveScroll: true,
        });
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'revoked':
            return 'destructive';
        case 'reserved':
            return 'secondary';
        default:
            return 'outline';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Vendor Aliases" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Vendor Aliases"
                        description="Manage merchant vendor aliases for B2B voucher redemption"
                    />
                    
                    <Dialog v-model:open="isOpen">
                        <DialogTrigger as-child>
                            <Button>
                                <UserPlus class="mr-2 h-4 w-4" />
                                Assign Alias
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Assign Vendor Alias</DialogTitle>
                                <DialogDescription>
                                    Assign a unique vendor alias to a user for B2B voucher redemption.
                                </DialogDescription>
                            </DialogHeader>
                            
                            <div class="space-y-4 py-4">
                                <!-- User Search -->
                                <div class="space-y-2">
                                    <Label for="user">User</Label>
                                    <div v-if="selectedUser" class="flex items-center justify-between border rounded-md p-2">
                                        <div>
                                            <p class="font-medium">{{ selectedUser.name }}</p>
                                            <p class="text-sm text-muted-foreground">{{ selectedUser.email }}</p>
                                        </div>
                                        <Button variant="ghost" size="sm" @click="selectedUser = null; form.user_id = null">
                                            Clear
                                        </Button>
                                    </div>
                                    <div v-else class="relative">
                                        <Input
                                            id="user"
                                            v-model="searchQuery"
                                            placeholder="Search users by name or email..."
                                            @input="handleUserSearch(searchQuery)"
                                        />
                                        <div
                                            v-if="searchResults.length > 0"
                                            class="absolute z-10 w-full mt-1 bg-background border rounded-md shadow-lg max-h-60 overflow-auto"
                                        >
                                            <button
                                                v-for="user in searchResults"
                                                :key="user.id"
                                                @click="selectUser(user)"
                                                class="w-full text-left px-3 py-2 hover:bg-accent"
                                            >
                                                <p class="font-medium">{{ user.name }}</p>
                                                <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.user_id" class="text-sm text-destructive">
                                        {{ form.errors.user_id }}
                                    </p>
                                </div>

                                <!-- Alias Input -->
                                <div class="space-y-2">
                                    <Label for="alias">Alias</Label>
                                    <Input
                                        id="alias"
                                        v-model="form.alias"
                                        placeholder="e.g., VNDR1"
                                        :maxlength="config.max_length"
                                        class="uppercase"
                                        @input="form.alias = form.alias.toUpperCase()"
                                    />
                                    <p class="text-sm text-muted-foreground">
                                        {{ validationHint }}
                                    </p>
                                    <p v-if="form.errors.alias" class="text-sm text-destructive">
                                        {{ form.errors.alias }}
                                    </p>
                                </div>

                                <!-- Notes -->
                                <div class="space-y-2">
                                    <Label for="notes">Notes (Optional)</Label>
                                    <Textarea
                                        id="notes"
                                        v-model="form.notes"
                                        placeholder="Add notes about this vendor..."
                                        rows="3"
                                    />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button variant="outline" @click="isOpen = false">Cancel</Button>
                                <Button @click="submitForm" :disabled="form.processing || !form.user_id || !form.alias">
                                    {{ form.processing ? 'Assigning...' : 'Assign Alias' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Aliases Table -->
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Alias</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Assigned Date</TableHead>
                                <TableHead class="w-[70px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="aliases.data.length === 0">
                                <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
                                    No vendor aliases found. Assign your first alias to get started.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="alias in aliases.data" :key="alias.id">
                                <TableCell class="font-mono font-bold">{{ alias.alias }}</TableCell>
                                <TableCell>
                                    <div>
                                        <p class="font-medium">{{ alias.owner.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ alias.owner.email }}</p>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusVariant(alias.status)">
                                        {{ alias.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ formatDate(alias.assigned_at) }}</TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                v-if="alias.status === 'active'"
                                                @click="revokeAlias(alias)"
                                            >
                                                Revoke
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="alias.status === 'revoked'"
                                                @click="reactivateAlias(alias)"
                                            >
                                                Reactivate
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="deleteAlias(alias)"
                                                class="text-destructive"
                                            >
                                                Delete Permanently
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div v-if="aliases.links.length > 3" class="flex items-center justify-center gap-2">
                    <Button
                        v-for="link in aliases.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="link.url && router.visit(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
