<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout title="Reset password" description="Enter your new password">
        <Head title="Reset Password" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required readonly />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">New Password</Label>
                    <Input id="password" v-model="form.password" type="password" required autofocus />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Reset password
                </Button>
            </div>
        </form>
    </PublicLayout>
</template>
