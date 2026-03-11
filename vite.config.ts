import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Stamps public/pwa/sw.js with the current build timestamp after each production build.
 * This forces browsers to detect the SW as "changed" and trigger install → activate → cache purge.
 */
function swVersionStamp() {
    return {
        name: 'sw-version-stamp',
        closeBundle() {
            const swPath = resolve(__dirname, 'public/pwa/sw.js');
            const versionPath = resolve(__dirname, 'VERSION');
            const timestamp = new Date().toISOString();

            // Stamp SW
            try {
                let content = readFileSync(swPath, 'utf-8');
                content = content.replace(
                    /const SW_BUILD = '[^']*';/,
                    `const SW_BUILD = '${timestamp}';`,
                );
                writeFileSync(swPath, content, 'utf-8');
                console.log(`\n  ✓ sw.js stamped: ${timestamp}`);
            } catch (e) {
                console.error('\n  ✗ Failed to stamp sw.js:', e.message);
            }

            // Stamp VERSION file with git commit hash
            try {
                const hash = execSync('git rev-parse --short HEAD', { encoding: 'utf-8' }).trim();
                writeFileSync(versionPath, hash, 'utf-8');
                console.log(`  ✓ VERSION stamped: ${hash}\n`);
            } catch (e) {
                console.error('  ✗ Failed to stamp VERSION:', e.message, '\n');
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        swVersionStamp(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});
