<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Coins, 
    Mail, 
    Phone, 
    Camera, 
    MapPin, 
    FileSignature, 
    Shield, 
    User, 
    Calendar,
    Home,
    Wallet,
    CheckCircle,
    Clock,
    Timer,
    AlertCircle,
    Info,
    Lock
} from 'lucide-vue-next';
import type { InspectInstructions } from '@/types/voucher';

interface Props {
    instructions: InspectInstructions;
    voucherStatus: 'active' | 'redeemed' | 'expired' | 'scheduled';
    compact?: boolean;
}

const props = defineProps<Props>();

const statusBadgeVariant = computed(() => {
    switch (props.voucherStatus) {
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
    return props.voucherStatus.charAt(0).toUpperCase() + props.voucherStatus.slice(1);
});

const formattedExpiresAt = computed(() => {
    if (!props.instructions.expires_at) return null;
    
    const date = new Date(props.instructions.expires_at);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const formattedStartsAt = computed(() => {
    if (!props.instructions.starts_at) return null;
    
    const date = new Date(props.instructions.starts_at);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const timeUntilExpiry = computed(() => {
    if (!props.instructions.expires_at || props.voucherStatus !== 'active') return null;
    
    const now = new Date();
    const expiry = new Date(props.instructions.expires_at);
    const diff = expiry.getTime() - now.getTime();
    
    if (diff < 0) return null;
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    
    if (days > 0) {
        return `${days} day${days > 1 ? 's' : ''} ${hours} hour${hours !== 1 ? 's' : ''}`;
    }
    
    return `${hours} hour${hours !== 1 ? 's' : ''}`;
});

const getInputIcon = (value: string) => {
    switch (value) {
        case 'email':
            return Mail;
        case 'mobile':
            return Phone;
        case 'selfie':
            return Camera;
        case 'location':
            return MapPin;
        case 'signature':
            return FileSignature;
        case 'kyc':
            return Shield;
        case 'name':
            return User;
        case 'address':
            return Home;
        case 'birth_date':
            return Calendar;
        case 'gross_monthly_income':
            return Wallet;
        default:
            return CheckCircle;
    }
};

const hasAssignmentOrSecurity = computed(() => {
    return props.instructions.validation.has_secret || props.instructions.validation.is_assigned;
});

const hasValidationRules = computed(() => {
    return props.instructions.location_validation || props.instructions.time_validation;
});

const hasRequirements = computed(() => {
    return props.instructions.required_inputs && props.instructions.required_inputs.length > 0;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Amount Card (Prominent) -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Coins class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">You will receive</p>
                            <p class="text-2xl font-bold">{{ instructions.formatted_amount }}</p>
                        </div>
                    </div>
                    <Badge :variant="statusBadgeVariant">
                        {{ statusText }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Requirements Card -->
        <Card v-if="hasRequirements">
            <CardHeader>
                <CardTitle>What you need to provide</CardTitle>
                <CardDescription>
                    Required information to redeem this voucher
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div 
                        v-for="input in instructions.required_inputs" 
                        :key="input.value"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <component 
                            :is="getInputIcon(input.value)" 
                            class="h-5 w-5 text-muted-foreground flex-shrink-0"
                        />
                        <span class="text-sm font-medium">{{ input.label }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- No Requirements -->
        <Alert v-else>
            <CheckCircle class="h-4 w-4" />
            <AlertDescription>
                No additional information required to redeem this voucher.
            </AlertDescription>
        </Alert>

        <!-- Validity Card -->
        <Card>
            <CardHeader>
                <CardTitle>Validity</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <!-- Scheduled voucher -->
                <div v-if="voucherStatus === 'scheduled' && formattedStartsAt" class="flex items-start gap-3">
                    <Calendar class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium">Available from</p>
                        <p class="text-sm text-muted-foreground">{{ formattedStartsAt }}</p>
                    </div>
                </div>

                <!-- Expiration -->
                <div v-if="formattedExpiresAt" class="flex items-start gap-3">
                    <Clock :class="[
                        'h-5 w-5 flex-shrink-0 mt-0.5',
                        voucherStatus === 'expired' ? 'text-destructive' : 'text-muted-foreground'
                    ]" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ voucherStatus === 'expired' ? 'Expired on' : 'Expires on' }}
                        </p>
                        <p class="text-sm text-muted-foreground">{{ formattedExpiresAt }}</p>
                        <p v-if="timeUntilExpiry" class="text-xs text-muted-foreground mt-1">
                            {{ timeUntilExpiry }} remaining
                        </p>
                    </div>
                </div>

                <!-- No expiration -->
                <div v-if="!formattedExpiresAt && voucherStatus !== 'scheduled'" class="flex items-start gap-3">
                    <Info class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm text-muted-foreground">No expiration date</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Assignment & Security Card -->
        <Card v-if="hasAssignmentOrSecurity">
            <CardHeader>
                <CardTitle>Assignment & Security</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <!-- Assignment -->
                <div v-if="instructions.validation.is_assigned" class="flex items-start gap-3">
                    <Phone class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium">Assigned to mobile number</p>
                        <p class="text-sm text-muted-foreground font-mono">
                            {{ instructions.validation.assigned_mobile_masked || 'Protected' }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            Only this number can redeem this voucher
                        </p>
                    </div>
                </div>

                <!-- Available to anyone -->
                <div v-else class="flex items-start gap-3">
                    <CheckCircle class="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium">Available to anyone</p>
                        <p class="text-xs text-muted-foreground">
                            Not assigned to a specific mobile number
                        </p>
                    </div>
                </div>

                <!-- Secret required -->
                <div v-if="instructions.validation.has_secret" class="flex items-start gap-3">
                    <Lock class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium">Secret code required</p>
                        <p class="text-xs text-muted-foreground">
                            You must provide a secret code to redeem this voucher
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Validation Rules Card -->
        <Card v-if="hasValidationRules">
            <CardHeader>
                <CardTitle>Validation Rules</CardTitle>
                <CardDescription>
                    Restrictions that apply to this voucher
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Location validation -->
                <div v-if="instructions.location_validation" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <MapPin class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium">Location Requirement</p>
                            <p class="text-sm text-muted-foreground">
                                {{ instructions.location_validation.description }}
                            </p>
                            <Badge 
                                :variant="instructions.location_validation.on_failure === 'block' ? 'destructive' : 'secondary'"
                                class="mt-2"
                            >
                                {{ instructions.location_validation.on_failure === 'block' ? 'Strict' : 'Warning only' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Time validation -->
                <div v-if="instructions.time_validation" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <Timer class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium">Time Restrictions</p>
                            <p class="text-sm text-muted-foreground">
                                {{ instructions.time_validation.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Rider Message Card -->
        <Alert v-if="instructions.rider && instructions.rider.message">
            <Info class="h-4 w-4" />
            <AlertDescription>
                <p class="font-medium mb-1">Additional Information</p>
                <p class="text-sm">{{ instructions.rider.message }}</p>
                <a 
                    v-if="instructions.rider.url" 
                    :href="instructions.rider.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-primary hover:underline mt-2 inline-block"
                >
                    Learn more →
                </a>
            </AlertDescription>
        </Alert>
    </div>
</template>
