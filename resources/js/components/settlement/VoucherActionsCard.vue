<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Lock, Unlock, XCircle, StopCircle, Loader2 } from 'lucide-vue-next'
import axios from 'axios'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    voucherCode: string
    voucherState: string
    voucherType: string
    isOwner: boolean
    isExpired: boolean
}

const props = defineProps<Props>()
const { toast } = useToast()
const page = usePage()

const processing = ref(false)
const showLockDialog = ref(false)
const showUnlockDialog = ref(false)
const showForceCloseDialog = ref(false)
const showCancelDialog = ref(false)
const reason = ref('')

const canLock = computed(() => {
    return props.isOwner && props.voucherState === 'active'
})

const canUnlock = computed(() => {
    return props.isOwner && props.voucherState === 'locked' && !props.isExpired
})

const canForceClose = computed(() => {
    return props.isOwner &&
           ['payable', 'settlement'].includes(props.voucherType) &&
           ['active', 'locked'].includes(props.voucherState)
})

const canCancel = computed(() => {
    return props.isOwner && ['active', 'locked'].includes(props.voucherState)
})

const lockVoucher = async () => {
    processing.value = true
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await axios.post('/api/v1/vouchers/lock', {
            code: props.voucherCode,
            reason: reason.value || undefined,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        })
        
        toast({
            title: 'Voucher Locked',
            description: 'Voucher has been successfully locked',
        })
        
        reason.value = ''
        showLockDialog.value = false
        window.location.reload()
        
    } catch (err: any) {
        toast({
            title: 'Lock Failed',
            description: err.response?.data?.message || 'Failed to lock voucher',
            variant: 'destructive',
        })
    } finally {
        processing.value = false
    }
}

const unlockVoucher = async () => {
    processing.value = true
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await axios.post('/api/v1/vouchers/unlock', {
            code: props.voucherCode,
            reason: reason.value || undefined,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        })
        
        toast({
            title: 'Voucher Unlocked',
            description: 'Voucher has been successfully unlocked',
        })
        
        reason.value = ''
        showUnlockDialog.value = false
        window.location.reload()
        
    } catch (err: any) {
        toast({
            title: 'Unlock Failed',
            description: err.response?.data?.message || 'Failed to unlock voucher',
            variant: 'destructive',
        })
    } finally {
        processing.value = false
    }
}

const forceCloseVoucher = async () => {
    processing.value = true
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await axios.post('/api/v1/vouchers/force-close', {
            code: props.voucherCode,
            reason: reason.value || undefined,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        })
        
        toast({
            title: 'Voucher Closed',
            description: 'Voucher has been successfully closed',
        })
        
        reason.value = ''
        showForceCloseDialog.value = false
        window.location.reload()
        
    } catch (err: any) {
        toast({
            title: 'Close Failed',
            description: err.response?.data?.message || 'Failed to close voucher',
            variant: 'destructive',
        })
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <Card v-if="canLock || canUnlock || canForceClose || canCancel">
        <CardHeader>
            <CardTitle>Actions</CardTitle>
            <CardDescription>
                Manage voucher state and lifecycle
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="flex flex-wrap gap-2">
                <!-- Lock Button -->
                <Button
                    v-if="canLock"
                    @click="showLockDialog = true"
                    variant="outline"
                    size="sm"
                >
                    <Lock class="mr-2 h-4 w-4" />
                    Lock Voucher
                </Button>
                
                <!-- Unlock Button -->
                <Button
                    v-if="canUnlock"
                    @click="showUnlockDialog = true"
                    variant="outline"
                    size="sm"
                >
                    <Unlock class="mr-2 h-4 w-4" />
                    Unlock Voucher
                </Button>
                
                <!-- Force Close Button -->
                <Button
                    v-if="canForceClose"
                    @click="showForceCloseDialog = true"
                    variant="outline"
                    size="sm"
                >
                    <StopCircle class="mr-2 h-4 w-4" />
                    Force Close
                </Button>
            </div>
        </CardContent>
    </Card>

    <!-- Lock Dialog -->
    <AlertDialog v-model:open="showLockDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Lock Voucher</AlertDialogTitle>
                <AlertDialogDescription>
                    This will temporarily suspend the voucher. It cannot accept payments or be redeemed while locked.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-2 py-4">
                <Label for="lock-reason">Reason (optional)</Label>
                <Textarea
                    id="lock-reason"
                    v-model="reason"
                    placeholder="e.g., Fraud investigation, dispute resolution..."
                    :disabled="processing"
                />
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="lockVoucher"
                    :disabled="processing"
                >
                    <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ processing ? 'Locking...' : 'Lock Voucher' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Unlock Dialog -->
    <AlertDialog v-model:open="showUnlockDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Unlock Voucher</AlertDialogTitle>
                <AlertDialogDescription>
                    This will restore the voucher to ACTIVE state. It will be able to accept payments and be redeemed again.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-2 py-4">
                <Label for="unlock-reason">Reason (optional)</Label>
                <Textarea
                    id="unlock-reason"
                    v-model="reason"
                    placeholder="e.g., Investigation completed, issue resolved..."
                    :disabled="processing"
                />
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="unlockVoucher"
                    :disabled="processing"
                >
                    <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ processing ? 'Unlocking...' : 'Unlock Voucher' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Force Close Dialog -->
    <AlertDialog v-model:open="showForceCloseDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Force Close Voucher</AlertDialogTitle>
                <AlertDialogDescription class="space-y-2">
                    <p>This will permanently close the voucher before full payment is reached.</p>
                    <p class="font-medium text-destructive">This action cannot be undone.</p>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-2 py-4">
                <Label for="close-reason">Reason (optional)</Label>
                <Textarea
                    id="close-reason"
                    v-model="reason"
                    placeholder="e.g., Partial fulfillment accepted, ending collection early..."
                    :disabled="processing"
                />
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="forceCloseVoucher"
                    :disabled="processing"
                    class="bg-destructive hover:bg-destructive/90"
                >
                    <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ processing ? 'Closing...' : 'Force Close' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
