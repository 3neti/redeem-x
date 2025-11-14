import { useToast } from '@/components/ui/toast/use-toast';

export interface UseQrShareReturn {
    copyQrLink: (url: string) => Promise<boolean>;
    copyQrImage: (base64: string) => Promise<boolean>;
    downloadQr: (base64: string, filename?: string) => void;
    getEmailLink: (url: string, subject?: string, body?: string) => string;
    getSmsLink: (text: string) => string;
    getWhatsAppLink: (text: string) => string;
    getTelegramLink: (url: string, text?: string) => string;
    shareNative: (data: ShareData) => Promise<boolean>;
    isShareSupported: () => boolean;
}

/**
 * Composable for sharing QR codes via multiple channels
 */
export function useQrShare(): UseQrShareReturn {
    const { toast } = useToast();

    /**
     * Copy QR link to clipboard
     */
    const copyQrLink = async (url: string): Promise<boolean> => {
        try {
            await navigator.clipboard.writeText(url);
            toast({
                title: 'Link Copied!',
                description: 'QR code link copied to clipboard',
                variant: 'default',
            });
            return true;
        } catch (err) {
            console.error('[useQrShare] Failed to copy link:', err);
            toast({
                title: 'Copy Failed',
                description: 'Could not copy link to clipboard',
                variant: 'destructive',
            });
            return false;
        }
    };

    /**
     * Copy QR image to clipboard
     */
    const copyQrImage = async (base64: string): Promise<boolean> => {
        try {
            // Convert base64 to blob
            const response = await fetch(base64);
            const blob = await response.blob();

            // Use Clipboard API to copy image
            await navigator.clipboard.write([
                new ClipboardItem({
                    'image/png': blob,
                }),
            ]);

            toast({
                title: 'Image Copied!',
                description: 'QR code image copied to clipboard',
                variant: 'default',
            });
            return true;
        } catch (err) {
            console.error('[useQrShare] Failed to copy image:', err);
            toast({
                title: 'Copy Failed',
                description: 'Could not copy image to clipboard',
                variant: 'destructive',
            });
            return false;
        }
    };

    /**
     * Download QR code as PNG file
     */
    const downloadQr = (base64: string, filename?: string): void => {
        try {
            const timestamp = new Date().getTime();
            const defaultFilename = `wallet-qr-${timestamp}.png`;
            const finalFilename = filename || defaultFilename;

            // Create download link
            const link = document.createElement('a');
            link.href = base64;
            link.download = finalFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast({
                title: 'QR Code Downloaded',
                description: `Saved as ${finalFilename}`,
                variant: 'default',
            });
        } catch (err) {
            console.error('[useQrShare] Failed to download:', err);
            toast({
                title: 'Download Failed',
                description: 'Could not download QR code',
                variant: 'destructive',
            });
        }
    };

    /**
     * Generate mailto link for email
     */
    const getEmailLink = (
        url: string,
        subject: string = 'Scan to send me money',
        body?: string
    ): string => {
        const defaultBody = `Scan this QR code to load my wallet:\n\n${url}`;
        const emailBody = body || defaultBody;
        return `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(emailBody)}`;
    };

    /**
     * Generate SMS link
     */
    const getSmsLink = (text: string): string => {
        return `sms:?body=${encodeURIComponent(text)}`;
    };

    /**
     * Generate WhatsApp share link
     */
    const getWhatsAppLink = (text: string): string => {
        return `https://wa.me/?text=${encodeURIComponent(text)}`;
    };

    /**
     * Generate Telegram share link
     */
    const getTelegramLink = (url: string, text?: string): string => {
        const params = new URLSearchParams({
            url: url,
            ...(text && { text }),
        });
        return `https://t.me/share/url?${params.toString()}`;
    };

    /**
     * Use native Web Share API (mobile)
     */
    const shareNative = async (data: ShareData): Promise<boolean> => {
        if (!navigator.share) {
            toast({
                title: 'Share Not Supported',
                description: 'Native sharing is not available on this device',
                variant: 'destructive',
            });
            return false;
        }

        try {
            await navigator.share(data);
            return true;
        } catch (err: any) {
            // User cancelled share, don't show error
            if (err.name === 'AbortError') {
                return false;
            }

            console.error('[useQrShare] Native share failed:', err);
            toast({
                title: 'Share Failed',
                description: 'Could not share QR code',
                variant: 'destructive',
            });
            return false;
        }
    };

    /**
     * Check if native share API is supported
     */
    const isShareSupported = (): boolean => {
        return typeof navigator.share !== 'undefined';
    };

    return {
        copyQrLink,
        copyQrImage,
        downloadQr,
        getEmailLink,
        getSmsLink,
        getWhatsAppLink,
        getTelegramLink,
        shareNative,
        isShareSupported,
    };
}
