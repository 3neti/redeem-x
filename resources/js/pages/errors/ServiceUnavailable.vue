<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AlertCircle } from 'lucide-vue-next';

interface Props {
    message?: string;
    support_email?: string;
}

const props = withDefaults(defineProps<Props>(), {
    message: 'We\'re experiencing technical difficulties. Please try again later.',
    support_email: 'support@example.com',
});

function goBack() {
    window.history.back();
}
</script>

<template>
    <PublicLayout>
        <Head title="Service Unavailable" />
        
        <div class="container mx-auto max-w-md px-4 py-16">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100">
                            <AlertCircle class="h-6 w-6 text-orange-600" />
                        </div>
                        <div>
                            <CardTitle>Service Temporarily Unavailable</CardTitle>
                            <CardDescription>Error 503</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        {{ message }}
                    </p>
                    
                    <div class="rounded-lg bg-muted p-4">
                        <p class="text-sm font-medium mb-2">Need Help?</p>
                        <p class="text-sm text-muted-foreground">
                            If this problem persists, please contact support at:
                        </p>
                        <a 
                            :href="`mailto:${support_email}`" 
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ support_email }}
                        </a>
                    </div>
                    
                    <div class="flex gap-3 pt-2">
                        <Button 
                            variant="outline" 
                            class="flex-1" 
                            @click="goBack"
                        >
                            Go Back
                        </Button>
                        <Button 
                            class="flex-1" 
                            @click="() => window.location.href = '/'"
                        >
                            Go Home
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
