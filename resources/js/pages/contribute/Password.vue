<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { Lock, Eye, EyeOff, User, FileUp } from 'lucide-vue-next'

interface Props {
    voucher_code: string
    token: string
    label?: string | null
    recipient_name?: string | null
}

const props = defineProps<Props>()

const showPassword = ref(false)
const page = usePage()

const form = useForm({
    voucher: props.voucher_code,
    token: props.token,
    password: '',
    // Pass through signed URL params
    signature: new URLSearchParams(window.location.search).get('signature') || '',
    expires: new URLSearchParams(window.location.search).get('expires') || '',
})

function submit() {
    form.post('/contribute/verify-password', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Password Required" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="bg-white rounded-lg shadow-md p-6">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="mb-4 flex justify-center">
                        <div class="h-16 w-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <Lock class="h-8 w-8 text-blue-600" />
                        </div>
                    </div>
                    <h1 class="text-xl font-semibold text-gray-900 mb-2">
                        Password Required
                    </h1>
                    <p class="text-gray-600 text-sm">
                        This contribution link is password protected.
                    </p>
                </div>

                <!-- Context info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <FileUp class="h-4 w-4 text-gray-400" />
                        <span class="text-gray-600">Voucher:</span>
                        <span class="font-mono font-medium">{{ voucher_code }}</span>
                    </div>
                    <div v-if="recipient_name" class="flex items-center gap-2 text-sm">
                        <User class="h-4 w-4 text-gray-400" />
                        <span class="text-gray-600">Recipient:</span>
                        <span class="font-medium">{{ recipient_name }}</span>
                    </div>
                    <div v-if="label" class="text-xs text-gray-500">
                        {{ label }}
                    </div>
                </div>

                <!-- Password form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Enter Password
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter password"
                                autocomplete="off"
                                autofocus
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <Eye v-if="!showPassword" class="h-5 w-5" />
                                <EyeOff v-else class="h-5 w-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.password"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
                    >
                        {{ form.processing ? 'Verifying...' : 'Continue' }}
                    </button>
                </form>

                <p class="mt-6 text-xs text-center text-gray-500">
                    Password provided by the voucher owner
                </p>
            </div>
        </div>
    </div>
</template>
