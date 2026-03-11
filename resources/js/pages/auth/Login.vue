<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import InputError from '@/components/InputError.vue';

interface Props {
    enableWorkos?: boolean;
    enableMobileLogin?: boolean;
    enableRegistration?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    enableWorkos: false,
    enableMobileLogin: false,
    enableRegistration: false,
});

const loginMode = ref<'email' | 'mobile'>('email');

const form = useForm({
    login: '',
    password: '',
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}

function toggleMode() {
    loginMode.value = loginMode.value === 'email' ? 'mobile' : 'email';
    form.login = '';
}
</script>

<template>
    <PublicLayout title="Log in" description="Enter your credentials to access your account">
        <Head title="Log in" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="login">
                            {{ loginMode === 'email' ? 'Email' : 'Mobile' }}
                        </Label>
                        <button
                            v-if="enableMobileLogin"
                            type="button"
                            class="text-xs text-muted-foreground underline hover:text-foreground"
                            @click="toggleMode"
                        >
                            Use {{ loginMode === 'email' ? 'mobile' : 'email' }} instead
                        </button>
                    </div>
                    <Input
                        v-if="loginMode === 'email'"
                        id="login"
                        v-model="form.login"
                        type="email"
                        placeholder="you@example.com"
                        required
                        autofocus
                    />
                    <PhoneInput
                        v-else
                        v-model="form.login"
                        :error="form.errors.login"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.login" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <Link href="/forgot-password" class="text-xs text-muted-foreground underline hover:text-foreground">
                            Forgot password?
                        </Link>
                    </div>
                    <Input id="password" v-model="form.password" type="password" required />
                    <InputError :message="form.errors.password" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Log in
                </Button>

                <a
                    v-if="enableWorkos"
                    href="/login/workos"
                    class="inline-flex w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium ring-offset-background hover:bg-accent hover:text-accent-foreground"
                >
                    Sign in with SSO
                </a>
            </div>

            <div v-if="enableRegistration" class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <Link href="/register" class="underline underline-offset-4 hover:text-foreground">Sign up</Link>
            </div>
        </form>
    </PublicLayout>
</template>
