<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Props {
    status?: string;
}

defineProps<Props>();

const form = useForm({
    email: '',
});

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <PublicLayout title="Forgot password" description="Enter your email to receive a password reset link">
        <Head title="Forgot Password" />

        <div v-if="$page.props.flash?.status || status" class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-600">
            {{ $page.props.flash?.status || status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" required autofocus />
                    <InputError :message="form.errors.email" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Send reset link
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                <Link href="/login" class="underline underline-offset-4 hover:text-foreground">Back to login</Link>
            </div>
        </form>
    </PublicLayout>
</template>
