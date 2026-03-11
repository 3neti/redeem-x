<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import InputError from '@/components/InputError.vue';

const form = useForm({
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout title="Create an account" description="Enter your details to get started">
        <Head title="Register" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" type="text" placeholder="Your name" required autofocus />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" required />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile">Mobile</Label>
                    <PhoneInput v-model="form.mobile" :error="form.errors.mobile" required />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input id="password" v-model="form.password" type="password" required />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <Link href="/login" class="underline underline-offset-4 hover:text-foreground">Log in</Link>
            </div>
        </form>
    </PublicLayout>
</template>
