<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { Button } from '@/components/ui/button';
import { ChevronLeft, FileText } from 'lucide-vue-next';

interface Document {
    slug: string;
    title: string;
    description: string;
}

interface Props {
    slug: string;
    title: string;
    description: string;
    content: string;
    documents: Document[];
}

const props = defineProps<Props>();

// Configure marked
marked.setOptions({
    breaks: true,
    gfm: true,
});

// Parse and sanitize markdown
const htmlContent = computed(() => {
    const rawHtml = marked.parse(props.content) as string;
    return DOMPurify.sanitize(rawHtml, {
        ALLOWED_TAGS: [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'a', 'ul', 'ol', 'li',
            'strong', 'em', 'code', 'pre',
            'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div', 'span', 'br', 'hr',
        ],
        ALLOWED_ATTR: ['href', 'class', 'id'],
    });
});

// Extract table of contents from markdown
const tableOfContents = computed(() => {
    const headings = props.content.match(/^#{2,3}\s+(.+)$/gm) || [];
    return headings.map((heading) => {
        const level = heading.match(/^#{2,3}/)?.[0].length || 2;
        const text = heading.replace(/^#{2,3}\s+/, '');
        const id = text.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        return { level, text, id };
    });
});

// Smooth scroll to section
const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

onMounted(() => {
    // Add IDs to headings for anchor links
    const contentEl = document.querySelector('.markdown-content');
    if (contentEl) {
        const headings = contentEl.querySelectorAll('h2, h3');
        headings.forEach((heading) => {
            const text = heading.textContent || '';
            const id = text.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            heading.id = id;
        });
    }
});
</script>

<template>
    <Head :title="title" />
    
    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/documentation">
                            <ChevronLeft class="h-4 w-4 mr-1" />
                            Back
                        </Link>
                    </Button>
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="container mx-auto px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-[250px_1fr]">
                <!-- Table of Contents (Desktop) -->
                <aside class="hidden lg:block">
                    <div class="sticky top-8">
                        <h2 class="text-sm font-semibold mb-4">On This Page</h2>
                        <nav class="space-y-1">
                            <button
                                v-for="item in tableOfContents"
                                :key="item.id"
                                @click="scrollToSection(item.id)"
                                :class="[
                                    'block w-full text-left text-sm hover:text-foreground transition-colors',
                                    item.level === 2 ? 'pl-0 font-medium' : 'pl-4',
                                    'text-muted-foreground'
                                ]"
                            >
                                {{ item.text }}
                            </button>
                        </nav>

                        <!-- Other Documents -->
                        <div class="mt-8 pt-8 border-t">
                            <h2 class="text-sm font-semibold mb-4">Other Documents</h2>
                            <nav class="space-y-2">
                                <Link
                                    v-for="doc in documents.filter(d => d.slug !== slug)"
                                    :key="doc.slug"
                                    :href="`/documentation/${doc.slug}`"
                                    class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <FileText class="h-4 w-4" />
                                    {{ doc.title }}
                                </Link>
                            </nav>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <main>
                    <div class="prose prose-slate dark:prose-invert max-w-none">
                        <div class="markdown-content" v-html="htmlContent"></div>
                    </div>

                    <!-- Footer Navigation -->
                    <div class="mt-12 pt-8 border-t flex justify-between">
                        <Button variant="outline" as-child>
                            <Link href="/documentation">
                                <ChevronLeft class="h-4 w-4 mr-2" />
                                All Documentation
                            </Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <a href="/docs/api" target="_blank">
                                API Reference →
                            </a>
                        </Button>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference;

.markdown-content :deep(h1) {
    @apply text-3xl font-bold mt-8 mb-4;
}

.markdown-content :deep(h2) {
    @apply text-2xl font-semibold mt-8 mb-4 scroll-mt-8;
}

.markdown-content :deep(h3) {
    @apply text-xl font-semibold mt-6 mb-3 scroll-mt-8;
}

.markdown-content :deep(h4) {
    @apply text-lg font-semibold mt-4 mb-2;
}

.markdown-content :deep(p) {
    @apply mb-4 leading-7;
}

.markdown-content :deep(ul), .markdown-content :deep(ol) {
    @apply mb-4 ml-6;
}

.markdown-content :deep(li) {
    @apply mb-2;
}

.markdown-content :deep(code) {
    @apply bg-muted px-1.5 py-0.5 rounded text-sm font-mono;
}

.markdown-content :deep(pre) {
    @apply bg-muted p-4 rounded-lg overflow-x-auto mb-4;
}

.markdown-content :deep(pre code) {
    @apply bg-transparent p-0;
}

.markdown-content :deep(table) {
    @apply w-full border-collapse mb-4;
}

.markdown-content :deep(th) {
    @apply border border-border bg-muted px-4 py-2 text-left font-semibold;
}

.markdown-content :deep(td) {
    @apply border border-border px-4 py-2;
}

.markdown-content :deep(blockquote) {
    @apply border-l-4 border-primary pl-4 italic my-4 text-muted-foreground;
}

.markdown-content :deep(a) {
    @apply text-primary hover:underline;
}

.markdown-content :deep(hr) {
    @apply my-8 border-border;
}

.markdown-content :deep(strong) {
    @apply font-semibold;
}
</style>
