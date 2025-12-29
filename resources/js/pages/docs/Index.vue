<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FileText, Shield, Database } from 'lucide-vue-next';

interface Document {
    slug: string;
    title: string;
    description: string;
}

interface Props {
    documents: Document[];
}

defineProps<Props>();

const getIcon = (slug: string) => {
    switch (slug) {
        case 'bank-integration':
            return FileText;
        case 'security':
            return Shield;
        case 'data-retention':
            return Database;
        default:
            return FileText;
    }
};
</script>

<template>
    <Head title="Documentation" />
    
    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b">
            <div class="container mx-auto px-4 py-6">
                <h1 class="text-3xl font-bold">Redeem-X Documentation</h1>
                <p class="text-muted-foreground mt-2">
                    Bank-grade documentation for integration partners
                </p>
            </div>
        </header>

        <!-- Content -->
        <main class="container mx-auto px-4 py-12">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="doc in documents"
                    :key="doc.slug"
                    :href="`/documentation/${doc.slug}`"
                    class="block transition-transform hover:scale-105"
                >
                    <Card class="h-full hover:border-primary">
                        <CardHeader>
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="getIcon(doc.slug)" class="h-6 w-6 text-primary" />
                            </div>
                            <CardTitle>{{ doc.title }}</CardTitle>
                            <CardDescription>{{ doc.description }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <span class="text-sm text-primary">Read documentation →</span>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <!-- Additional Info -->
            <div class="mt-12 rounded-lg border bg-muted/50 p-6">
                <h2 class="text-lg font-semibold mb-2">Quick Links</h2>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    <li>
                        <a href="/docs/api" class="hover:text-foreground underline">
                            API Reference (Scramble)
                        </a>
                    </li>
                    <li>
                        <a href="/api.json" class="hover:text-foreground underline">
                            OpenAPI 3.0 Specification (JSON)
                        </a>
                    </li>
                    <li>
                        <span>Support: integrations@redeem-x.com</span>
                    </li>
                </ul>
            </div>
        </main>
    </div>
</template>
