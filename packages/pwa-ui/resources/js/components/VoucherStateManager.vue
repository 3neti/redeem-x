<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from './ui/button';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogFooter, 
    DialogHeader, 
    DialogTitle 
} from './ui/dialog';
import { 
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuLabel,
} from './ui/dropdown-menu';
import { useToast } from '@/components/ui/toast/use-toast';
import { MoreVertical, Lock, DoorClosed, Ban, Unlock } from 'lucide-vue-next';

interface Props {
    voucherCode: string;
    currentState?: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired';
    canManage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    currentState: 'active',
    canManage: true,
});

const { toast } = useToast();

// Dialog states
const showLockDialog = ref(false);
const showCloseDialog = ref(false);
const showCancelDialog = ref(false);
const showUnlockDialog = ref(false);

// Loading states
const loading = ref(false);

const handleStateChange = (action: 'lock' | 'close' | 'cancel' | 'unlock', title: string) => {
    loading.value = true;
    
    router.post(
        `/pwa/vouchers/${props.voucherCode}/${action}`,
        {},
        {
            onSuccess: () => {
                toast({
                    title,
                    description: `${props.voucherCode} has been ${action}ed`,
                });
                // Close all dialogs
                showLockDialog.value = false;
                showCloseDialog.value = false;
                showCancelDialog.value = false;
                showUnlockDialog.value = false;
            },
            onError: (errors) => {
                toast({
                    title: `Failed to ${action}`,
                    description: Object.values(errors).flat().join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                loading.value = false;
            },
        }
    );
};

const getStateActions = () => {
    const actions = [];
    
    switch (props.currentState) {
        case 'active':
            actions.push(
                { label: 'Lock Voucher', icon: Lock, action: () => showLockDialog.value = true, variant: 'default' },
                { label: 'Close Voucher', icon: DoorClosed, action: () => showCloseDialog.value = true, variant: 'default' },
                { label: 'Cancel Voucher', icon: Ban, action: () => showCancelDialog.value = true, variant: 'destructive' }
            );
            break;
        case 'locked':
            actions.push(
                { label: 'Unlock Voucher', icon: Unlock, action: () => showUnlockDialog.value = true, variant: 'default' },
                { label: 'Cancel Voucher', icon: Ban, action: () => showCancelDialog.value = true, variant: 'destructive' }
            );
            break;
        case 'closed':
        case 'cancelled':
        case 'expired':
            // No actions available for these states
            break;
    }
    
    return actions;
};
</script>

<template>
    <div v-if="canManage && getStateActions().length > 0" class="w-full">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" class="w-full">
                    <MoreVertical class="h-4 w-4" />
                    <span class="ml-2">Manage State</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuLabel>State Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-for="action in getStateActions()"
                    :key="action.label"
                    @click="action.action"
                    :class="{ 'text-destructive': action.variant === 'destructive' }"
                >
                    <component :is="action.icon" class="mr-2 h-4 w-4" />
                    {{ action.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Lock Dialog -->
        <Dialog v-model:open="showLockDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Lock Voucher?</DialogTitle>
                    <DialogDescription>
                        This will temporarily lock the voucher, preventing it from being redeemed. 
                        You can unlock it later.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showLockDialog = false"
                        :disabled="loading"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleStateChange('lock', 'Voucher locked')"
                        :disabled="loading"
                    >
                        {{ loading ? 'Locking...' : 'Lock' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Unlock Dialog -->
        <Dialog v-model:open="showUnlockDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Unlock Voucher?</DialogTitle>
                    <DialogDescription>
                        This will unlock the voucher, making it available for redemption again.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showUnlockDialog = false"
                        :disabled="loading"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleStateChange('unlock', 'Voucher unlocked')"
                        :disabled="loading"
                    >
                        {{ loading ? 'Unlocking...' : 'Unlock' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Close Dialog -->
        <Dialog v-model:open="showCloseDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Close Voucher?</DialogTitle>
                    <DialogDescription>
                        This will permanently close the voucher. This action cannot be undone.
                        Use this for completed transactions.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showCloseDialog = false"
                        :disabled="loading"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleStateChange('close', 'Voucher closed')"
                        :disabled="loading"
                    >
                        {{ loading ? 'Closing...' : 'Close' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Voucher?</DialogTitle>
                    <DialogDescription>
                        This will permanently cancel the voucher and mark it as expired. 
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showCancelDialog = false"
                        :disabled="loading"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleStateChange('cancel', 'Voucher cancelled')"
                        :disabled="loading"
                    >
                        {{ loading ? 'Cancelling...' : 'Cancel Voucher' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
