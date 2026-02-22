<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useQrShare } from '@/composables/useQrShare';
import { useToast } from '@/components/ui/toast/use-toast';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Copy, MessageCircle, Mail, Phone, Send } from 'lucide-vue-next';

interface Props {
    open: boolean;
    voucherCode: string;
    redeemUrl: string;
    voucherAmount?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'copied'): void;
}>();

const page = usePage();
const { toast } = useToast();
const { getTelegramBotDeepLink, getWhatsAppLink, getSmsLink, getEmailLink, copyQrLink } = useQrShare();

// Get bot username from Inertia shared props
const botUsername = computed(() => (page.props as any).telegram_bot_username || null);

// Share message for non-Telegram platforms
const shareMessage = computed(() => 
    `Redeem this voucher: ${props.voucherCode}${props.voucherAmount ? ` (${props.voucherAmount})` : ''}\n${props.redeemUrl}`
);

// Email subject
const emailSubject = computed(() => `Voucher ${props.voucherCode}`);

// Platform-specific links
const telegramBotLink = computed(() => {
    if (!botUsername.value) return null;
    return getTelegramBotDeepLink(botUsername.value, props.voucherCode);
});

const whatsappLink = computed(() => getWhatsAppLink(shareMessage.value));
const smsLink = computed(() => getSmsLink(shareMessage.value));
const emailLink = computed(() => getEmailLink(props.redeemUrl, emailSubject.value, shareMessage.value));

const closeDialog = () => {
    emit('update:open', false);
};

const handleCopyLink = async () => {
    const success = await copyQrLink(props.redeemUrl);
    if (success) {
        toast({
            title: 'Link copied',
            description: 'Redemption link copied to clipboard',
        });
        emit('copied');
    }
    closeDialog();
};

const openLink = (url: string) => {
    window.open(url, '_blank');
    closeDialog();
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Share Voucher</DialogTitle>
                <DialogDescription>
                    Choose how you'd like to share this voucher
                </DialogDescription>
            </DialogHeader>
            
            <div class="grid gap-3 py-4">
                <!-- Telegram Bot (Featured - Auto-redeem) -->
                <Button
                    v-if="telegramBotLink"
                    @click="openLink(telegramBotLink)"
                    variant="default"
                    class="w-full justify-start gap-3"
                >
                    <Send class="h-5 w-5" />
                    <div class="text-left">
                        <div class="font-medium">Telegram Bot</div>
                        <div class="text-xs opacity-80">Opens bot with auto-redeem</div>
                    </div>
                </Button>

                <!-- WhatsApp -->
                <Button
                    @click="openLink(whatsappLink)"
                    variant="outline"
                    class="w-full justify-start gap-3"
                >
                    <MessageCircle class="h-5 w-5 text-green-600" />
                    <div class="text-left">
                        <div class="font-medium">WhatsApp</div>
                        <div class="text-xs text-muted-foreground">Share via WhatsApp message</div>
                    </div>
                </Button>

                <!-- SMS -->
                <Button
                    @click="openLink(smsLink)"
                    variant="outline"
                    class="w-full justify-start gap-3"
                >
                    <Phone class="h-5 w-5 text-blue-600" />
                    <div class="text-left">
                        <div class="font-medium">SMS</div>
                        <div class="text-xs text-muted-foreground">Send as text message</div>
                    </div>
                </Button>

                <!-- Email -->
                <Button
                    @click="openLink(emailLink)"
                    variant="outline"
                    class="w-full justify-start gap-3"
                >
                    <Mail class="h-5 w-5 text-orange-600" />
                    <div class="text-left">
                        <div class="font-medium">Email</div>
                        <div class="text-xs text-muted-foreground">Send via email</div>
                    </div>
                </Button>

                <!-- Copy Link -->
                <Button
                    @click="handleCopyLink"
                    variant="outline"
                    class="w-full justify-start gap-3"
                >
                    <Copy class="h-5 w-5 text-gray-600" />
                    <div class="text-left">
                        <div class="font-medium">Copy Link</div>
                        <div class="text-xs text-muted-foreground">Copy redemption URL</div>
                    </div>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
