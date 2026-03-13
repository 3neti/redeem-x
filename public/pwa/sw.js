// Auto-stamped by Vite build plugin — DO NOT edit this line manually
const SW_BUILD = '2026-03-13T06:41:37.710Z';
const CACHE_NAME = `redeem-x-pwa-${SW_BUILD}`;
const OFFLINE_URL = '/pwa/offline';

// Assets to cache immediately on install (static, rarely change)
const PRECACHE_ASSETS = [
    '/pwa/manifest.webmanifest',
    '/pwa/icons/icon-72x72.png',
    '/pwa/icons/icon-96x96.png',
    '/pwa/icons/icon-128x128.png',
    '/pwa/icons/icon-144x144.png',
    '/pwa/icons/icon-152x152.png',
    '/pwa/icons/icon-192x192.png',
    '/pwa/icons/icon-384x384.png',
    '/pwa/icons/icon-512x512.png',
];

// Install event - precache essential assets
self.addEventListener('install', (event) => {
    console.log(`[SW] Installing build ${SW_BUILD}`);
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate event - purge ALL old caches
self.addEventListener('activate', (event) => {
    console.log(`[SW] Activating build ${SW_BUILD}, purging old caches`);
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => {
                        console.log(`[SW] Deleting old cache: ${name}`);
                        return caches.delete(name);
                    })
            );
        })
    );
    self.clients.claim();
});

// Fetch event
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    // Handle API requests - network first with cache fallback for GET
    if (url.pathname.startsWith('/api/')) {
        if (request.method === 'GET') {
            event.respondWith(networkFirstWithCache(request));
        }
        return;
    }

    // Handle navigation requests - network only, offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // Vite build assets (/build/) — NETWORK FIRST
    // These have content-hashed filenames; SW cache-first is redundant and dangerous.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(networkFirstWithCache(request));
        return;
    }

    // Other static assets (icons, fonts, manifest) — cache first is safe
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirstWithNetwork(request));
        return;
    }

    // Default: network first
    event.respondWith(networkFirstWithCache(request));
});

// Network first, cache fallback strategy
async function networkFirstWithCache(request) {
    try {
        const response = await fetch(request);
        // Only cache GET requests with successful responses
        if (response.ok && request.method === 'GET') {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        throw error;
    }
}

// Cache first, network fallback strategy
async function cacheFirstWithNetwork(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.error('[SW] Fetch failed:', error);
        throw error;
    }
}

// Check if request is for a static asset
function isStaticAsset(pathname) {
    const staticExtensions = [
        '.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg',
        '.ico', '.woff', '.woff2', '.ttf', '.eot'
    ];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Handle background sync for offline queue
self.addEventListener('sync', (event) => {
    if (event.tag === 'offline-sync') {
        event.waitUntil(syncOfflineQueue());
    }
});

async function syncOfflineQueue() {
    // This is triggered when the app comes back online
    // The actual sync logic is handled by the Vue app's syncManager
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({ type: 'SYNC_REQUESTED' });
    });
}

// Handle messages from the app
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
