<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { 
    Link2, 
    Copy, 
    Check, 
    Plus, 
    Trash2, 
    Loader2, 
    ExternalLink,
    Mail,
    MessageSquare,
    Share2,
    Lock,
    Clock,
    User
} from 'lucide-vue-next'
import { useToast } from '@/components/ui/toast/use-toast'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

interface ContributionToken {
    id: number
    uuid: string
    label: string | null
    recipient_name: string | null
    recipient_email: string | null
    recipient_mobile: string | null
    password_protected: boolean
    expires_at: string
    created_at: string
    url: string
}

interface Props {
    voucherCode: string
    tokens?: ContributionToken[]
    canGenerate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    tokens: () => [],
    canGenerate: true,
})

const emit = defineEmits<{
    'generated': [token: ContributionToken]
    'revoked': [tokenId: number]
}>()

const { toast } = useToast()

// Modal state
const showGenerateModal = ref(false)
const generating = ref(false)
const revoking = ref<number | null>(null)
const copiedUrl = ref<string | null>(null)

// Form state
const newLink = ref({
    label: '',
    recipient_name: '',
    recipient_email: '',
    password: '',
    expires_days: 7,
})

const resetForm = () => {
    newLink.value = {
        label: '',
        recipient_name: '',
        recipient_email: '',
        password: '',
        expires_days: 7,
    }
}

const generateLink = async () => {
    generating.value = true
    
    try {
        const response = await fetch(`/api/v1/vouchers/${props.voucherCode}/contribution-links`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                label: newLink.value.label || null,
                recipient_name: newLink.value.recipient_name || null,
                recipient_email: newLink.value.recipient_email || null,
                password: newLink.value.password || null,
                expires_days: newLink.value.expires_days,
            }),
        })
        
        const data = await response.json()
        
        if (!response.ok) {
            // Handle validation errors (422)
            if (data.errors) {
                const errorMessages = Object.values(data.errors).flat().join(', ')
                throw new Error(errorMessages || data.message || 'Validation failed')
            }
            throw new Error(data.error || data.message || 'Failed to generate link')
        }
        
        toast({
            title: 'Link Generated',
            description: 'Contribution link created successfully',
        })
        
        emit('generated', data.token)
        showGenerateModal.value = false
        resetForm()
        
        // Auto-copy the URL
        await copyUrl(data.token.url)
    } catch (err: any) {
        toast({
            title: 'Error',
            description: err.message || 'Failed to generate link',
            variant: 'destructive',
        })
    } finally {
        generating.value = false
    }
}

const revokeLink = async (tokenId: number) => {
    if (!confirm('Are you sure you want to revoke this link? It will no longer be usable.')) {
        return
    }
    
    revoking.value = tokenId
    
    try {
        const response = await fetch(`/api/v1/vouchers/${props.voucherCode}/contribution-links/${tokenId}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        
        if (!response.ok) {
            const data = await response.json()
            throw new Error(data.error || 'Failed to revoke link')
        }
        
        toast({
            title: 'Link Revoked',
            description: 'Contribution link has been revoked',
        })
        
        emit('revoked', tokenId)
    } catch (err: any) {
        toast({
            title: 'Error',
            description: err.message || 'Failed to revoke link',
            variant: 'destructive',
        })
    } finally {
        revoking.value = null
    }
}

const copyUrl = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url)
        copiedUrl.value = url
        toast({
            title: 'Copied',
            description: 'Link copied to clipboard',
        })
        setTimeout(() => { copiedUrl.value = null }, 2000)
    } catch {
        toast({
            title: 'Failed',
            description: 'Could not copy to clipboard',
            variant: 'destructive',
        })
    }
}

const openUrl = (url: string) => {
    window.open(url, '_blank')
}

const shareViaEmail = (token: ContributionToken) => {
    const subject = encodeURIComponent(`Document Contribution Request - ${props.voucherCode}`)
    const body = encodeURIComponent(
        `Hello${token.recipient_name ? ' ' + token.recipient_name : ''},\n\n` +
        `Please use the following link to upload documents for voucher ${props.voucherCode}:\n\n` +
        `${token.url}\n\n` +
        (token.password_protected ? 'Note: This link requires a password.\n\n' : '') +
        `This link will expire on ${new Date(token.expires_at).toLocaleDateString()}.\n\n` +
        `Thank you.`
    )
    window.location.href = `mailto:${token.recipient_email || ''}?subject=${subject}&body=${body}`
}

const shareViaSms = (token: ContributionToken) => {
    const message = encodeURIComponent(
        `Document request for voucher ${props.voucherCode}: ${token.url}` +
        (token.password_protected ? ' (password protected)' : '')
    )
    window.location.href = `sms:${token.recipient_mobile || ''}?body=${message}`
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const isExpired = (dateStr: string) => {
    return new Date(dateStr) < new Date()
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Link2 class="h-5 w-5" />
                        Contribution Links
                    </CardTitle>
                    <CardDescription>
                        Share links with external parties to upload documents
                    </CardDescription>
                </div>
                <Button 
                    v-if="canGenerate"
                    variant="outline" 
                    size="sm"
                    @click="showGenerateModal = true"
                >
                    <Plus class="h-4 w-4 mr-1" />
                    Generate Link
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Existing tokens -->
            <div v-if="tokens.length > 0" class="space-y-3">
                <div 
                    v-for="token in tokens" 
                    :key="token.id"
                    class="flex items-start gap-3 p-3 rounded-lg border bg-muted/30"
                >
                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span v-if="token.label" class="font-medium text-sm">{{ token.label }}</span>
                            <span v-else class="text-sm text-muted-foreground">Contribution Link</span>
                            <Badge v-if="token.password_protected" variant="secondary" class="text-xs">
                                <Lock class="h-3 w-3 mr-1" />
                                Protected
                            </Badge>
                            <Badge v-if="isExpired(token.expires_at)" variant="destructive" class="text-xs">
                                Expired
                            </Badge>
                        </div>
                        
                        <div v-if="token.recipient_name" class="flex items-center gap-1 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />
                            {{ token.recipient_name }}
                        </div>
                        
                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                            <Clock class="h-3 w-3" />
                            Expires: {{ formatDate(token.expires_at) }}
                        </div>
                        
                        <div class="flex items-center gap-1 pt-1 overflow-hidden">
                            <code class="text-xs bg-muted px-2 py-1 rounded truncate max-w-[200px] md:max-w-[300px]">
                                {{ token.url }}
                            </code>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-1">
                        <Button 
                            variant="ghost" 
                            size="sm"
                            class="h-8 w-8 p-0"
                            title="Copy link"
                            @click="copyUrl(token.url)"
                            :disabled="isExpired(token.expires_at)"
                        >
                            <Check v-if="copiedUrl === token.url" class="h-4 w-4 text-green-600" />
                            <Copy v-else class="h-4 w-4" />
                        </Button>
                        
                        <Button 
                            variant="ghost" 
                            size="sm"
                            class="h-8 w-8 p-0"
                            title="Open link"
                            @click="openUrl(token.url)"
                            :disabled="isExpired(token.expires_at)"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </Button>
                        
                        <Button 
                            variant="ghost" 
                            size="sm"
                            class="h-8 w-8 p-0"
                            title="Email link"
                            @click="shareViaEmail(token)"
                            :disabled="isExpired(token.expires_at)"
                        >
                            <Mail class="h-4 w-4" />
                        </Button>
                        
                        <Button 
                            variant="ghost" 
                            size="sm"
                            class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                            title="Revoke link"
                            @click="revokeLink(token.id)"
                            :disabled="revoking === token.id"
                        >
                            <Loader2 v-if="revoking === token.id" class="h-4 w-4 animate-spin" />
                            <Trash2 v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
            
            <!-- Empty state -->
            <div v-else class="text-center py-6 text-muted-foreground">
                <Link2 class="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p class="text-sm">No contribution links generated</p>
                <Button 
                    v-if="canGenerate"
                    variant="link" 
                    size="sm"
                    @click="showGenerateModal = true"
                    class="mt-2"
                >
                    Generate your first link
                </Button>
            </div>
        </CardContent>
    </Card>
    
    <!-- Generate Link Modal -->
    <Dialog v-model:open="showGenerateModal">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Generate Contribution Link</DialogTitle>
                <DialogDescription>
                    Create a shareable link for external parties to upload documents.
                </DialogDescription>
            </DialogHeader>
            
            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="link-label">Label (optional)</Label>
                    <Input 
                        id="link-label"
                        v-model="newLink.label"
                        placeholder="e.g., Vendor Invoice"
                    />
                </div>
                
                <div class="space-y-2">
                    <Label for="recipient-name">Recipient Name (optional)</Label>
                    <Input 
                        id="recipient-name"
                        v-model="newLink.recipient_name"
                        placeholder="e.g., John Doe"
                    />
                </div>
                
                <div class="space-y-2">
                    <Label for="recipient-email">Recipient Email (optional)</Label>
                    <Input 
                        id="recipient-email"
                        v-model="newLink.recipient_email"
                        type="email"
                        placeholder="e.g., vendor@example.com"
                    />
                </div>
                
                <div class="space-y-2">
                    <Label for="link-password">Password Protection (optional)</Label>
                    <Input 
                        id="link-password"
                        v-model="newLink.password"
                        type="password"
                        placeholder="Leave empty for no password"
                    />
                    <p class="text-xs text-muted-foreground">
                        If set, the recipient must enter this password to access the upload page.
                    </p>
                </div>
                
                <div class="space-y-2">
                    <Label for="expires-days">Link Validity</Label>
                    <select
                        id="expires-days"
                        v-model="newLink.expires_days"
                        class="w-full h-10 px-3 rounded-md border border-input bg-background text-sm"
                    >
                        <option :value="1">1 day</option>
                        <option :value="3">3 days</option>
                        <option :value="7">7 days</option>
                        <option :value="14">14 days</option>
                        <option :value="30">30 days</option>
                        <option :value="90">90 days</option>
                    </select>
                </div>
            </div>
            
            <DialogFooter>
                <Button variant="outline" @click="showGenerateModal = false" :disabled="generating">
                    Cancel
                </Button>
                <Button @click="generateLink" :disabled="generating">
                    <Loader2 v-if="generating" class="h-4 w-4 mr-2 animate-spin" />
                    Generate Link
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
