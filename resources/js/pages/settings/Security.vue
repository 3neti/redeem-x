<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Copy, Plus, Trash2, Shield, Key, Zap, AlertCircle, CheckCircle2 } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useToast } from '@/components/ui/toast/use-toast';
import { type BreadcrumbItem } from '@/types';

interface Props {
    security: {
        ip_whitelist_enabled: boolean;
        ip_whitelist: string[];
        signature_enabled: boolean;
        signature_secret: string | null;
        rate_limit_tier: string;
    };
    status?: string;
    secret?: string;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Security',
        href: '/settings/security',
    },
];

const page = usePage();
const { toast } = useToast();

// IP Whitelist
const ipWhitelistEnabled = ref(props.security.ip_whitelist_enabled);
const ipWhitelist = ref<string[]>([...props.security.ip_whitelist]);

const addIpAddress = () => {
    ipWhitelist.value.push('');
};

const removeIpAddress = (index: number) => {
    ipWhitelist.value.splice(index, 1);
};

const saveIpWhitelist = () => {
    router.post('/settings/security/ip-whitelist', {
        enabled: ipWhitelistEnabled.value,
        whitelist: ipWhitelist.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Saved',
                description: 'IP whitelist settings updated successfully',
            });
        },
    });
};

// Request Signing
const signatureEnabled = ref(props.security.signature_enabled);
const showGeneratedSecret = ref(!!props.secret);
const generatedSecret = ref(props.secret || '');

const generateSecret = () => {
    router.post('/settings/security/signature/generate', {}, {
        preserveScroll: true,
        onSuccess: (page: any) => {
            showGeneratedSecret.value = true;
            generatedSecret.value = page.props.secret;
            toast({
                title: 'Generated',
                description: 'New signing secret generated. Save it securely!',
                variant: 'default',
            });
        },
    });
};

const copySecret = async () => {
    await navigator.clipboard.writeText(generatedSecret.value);
    toast({
        title: 'Copied',
        description: 'Secret copied to clipboard',
    });
};

const saveSignature = () => {
    router.post('/settings/security/signature', {
        enabled: signatureEnabled.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Saved',
                description: 'Request signing settings updated successfully',
            });
        },
    });
};

// Rate Limit Tier
const tierInfo = computed(() => {
    const tiers: Record<string, { label: string; color: string; requests: string; burst: string }> = {
        basic: {
            label: 'Basic',
            color: 'bg-gray-500',
            requests: '60 requests/min',
            burst: '10 burst',
        },
        premium: {
            label: 'Premium',
            color: 'bg-blue-500',
            requests: '300 requests/min',
            burst: '50 burst',
        },
        enterprise: {
            label: 'Enterprise',
            color: 'bg-purple-500',
            requests: '1000 requests/min',
            burst: '200 burst',
        },
    };
    return tiers[props.security.rate_limit_tier] || tiers.basic;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Security" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Security Settings"
                    description="Manage API security features including IP whitelisting, request signing, and rate limiting"
                />

                <!-- IP Whitelisting -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Shield class="h-5 w-5 text-muted-foreground" />
                            <CardTitle>IP Whitelisting</CardTitle>
                        </div>
                        <CardDescription>
                            Restrict API access to specific IP addresses or CIDR ranges
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                id="ip-whitelist-enabled"
                                v-model="ipWhitelistEnabled"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            <Label for="ip-whitelist-enabled" class="text-sm font-medium">
                                Enable IP Whitelisting
                            </Label>
                        </div>

                        <Alert v-if="ipWhitelistEnabled && ipWhitelist.length === 0">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                Add at least one IP address before enabling. Empty whitelist allows all IPs.
                            </AlertDescription>
                        </Alert>

                        <div v-if="ipWhitelistEnabled" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label class="text-sm font-medium">Allowed IP Addresses</Label>
                                <Button
                                    @click="addIpAddress"
                                    size="sm"
                                    variant="outline"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add IP
                                </Button>
                            </div>

                            <div v-for="(ip, index) in ipWhitelist" :key="index" class="flex items-center gap-2">
                                <Input
                                    v-model="ipWhitelist[index]"
                                    placeholder="203.0.113.50 or 192.168.0.0/24"
                                    class="flex-1"
                                />
                                <Button
                                    @click="removeIpAddress(index)"
                                    size="icon"
                                    variant="ghost"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                Supports IPv4 (203.0.113.50), IPv4 CIDR (192.168.0.0/24), IPv6, and IPv6 CIDR.
                            </p>
                        </div>

                        <div class="flex justify-end">
                            <Button @click="saveIpWhitelist">
                                Save IP Whitelist
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Request Signing -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Key class="h-5 w-5 text-muted-foreground" />
                            <CardTitle>Request Signing (HMAC-SHA256)</CardTitle>
                        </div>
                        <CardDescription>
                            Require cryptographic signatures on all API requests for enhanced security
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                id="signature-enabled"
                                v-model="signatureEnabled"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            <Label for="signature-enabled" class="text-sm font-medium">
                                Enable Request Signing
                            </Label>
                        </div>

                        <div v-if="signatureEnabled" class="space-y-4">
                            <Alert v-if="!security.signature_secret && !generatedSecret">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    Generate a signing secret before enabling request signing.
                                </AlertDescription>
                            </Alert>

                            <div class="space-y-2">
                                <Label>Signing Secret</Label>
                                <div class="flex items-center gap-2">
                                    <Input
                                        :value="security.signature_secret || 'No secret generated'"
                                        readonly
                                        class="flex-1 font-mono text-sm"
                                    />
                                    <Button
                                        @click="generateSecret"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Generate New
                                    </Button>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Your current secret (masked). Generate a new one to rotate.
                                </p>
                            </div>

                            <Alert v-if="showGeneratedSecret" variant="default" class="border-green-500">
                                <CheckCircle2 class="h-4 w-4 text-green-600" />
                                <AlertDescription class="space-y-2">
                                    <p class="font-semibold text-green-600">
                                        ⚠️ Save this secret now! It won't be shown again.
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 rounded bg-muted p-2 text-xs font-mono">
                                            {{ generatedSecret }}
                                        </code>
                                        <Button
                                            @click="copySecret"
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Copy class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </AlertDescription>
                            </Alert>
                        </div>

                        <div class="flex justify-end">
                            <Button @click="saveSignature">
                                Save Signature Settings
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Rate Limiting -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Zap class="h-5 w-5 text-muted-foreground" />
                            <CardTitle>Rate Limiting</CardTitle>
                        </div>
                        <CardDescription>
                            View your current API rate limit tier (contact support to upgrade)
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between rounded-lg border p-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Current Tier:</span>
                                    <Badge :class="tierInfo.color" variant="secondary">
                                        {{ tierInfo.label }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ tierInfo.requests }} • {{ tierInfo.burst }}
                                </p>
                            </div>
                            <Button variant="outline" size="sm" disabled>
                                Upgrade Tier
                            </Button>
                        </div>

                        <Alert>
                            <AlertDescription>
                                To upgrade your rate limit tier, contact support at support@redeem-x.com with your use case and expected traffic volume.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>

                <Separator />

                <!-- Security Best Practices -->
                <Card>
                    <CardHeader>
                        <CardTitle>Security Best Practices</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                            <li>
                                <strong>IP Whitelisting:</strong> Use CIDR ranges (e.g., 192.168.0.0/24) for offices with dynamic IPs
                            </li>
                            <li>
                                <strong>Request Signing:</strong> Rotate secrets every 90 days and ensure clocks are synchronized
                            </li>
                            <li>
                                <strong>Layered Security:</strong> Enable multiple security features for defense in depth
                            </li>
                            <li>
                                <strong>Testing:</strong> Test security changes in a sandbox environment before production
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
