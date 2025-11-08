<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <!-- Simple Header -->
        <header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur">
            <div class="container mx-auto flex h-16 items-center px-4">
                <Link href="/" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Simple Footer -->
        <footer class="border-t py-6">
            <div class="container mx-auto px-4 text-center text-sm text-muted-foreground">
                <p>&copy; {{ new Date().getFullYear() }} Redeem-X. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
