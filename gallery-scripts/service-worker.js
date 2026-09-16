/**
 * service-worker.js
 * ──────────────────
 * Registered by design-gallery.html for offline support and asset caching.
 *
 * Strategy:
 *   • Shell assets (HTML, CSS, JS, fonts) → Cache-first
 *   • Gallery images                       → Cache-first with network fallback
 *   • API / PHP endpoints                  → Network-only (never cache writes)
 *
 * Place this file at the project root (same level as design-gallery.html).
 */

const CACHE_VERSION  = 'gallery-v2';
const SHELL_CACHE    = `${CACHE_VERSION}-shell`;
const IMAGE_CACHE    = `${CACHE_VERSION}-images`;

// Assets to pre-cache on install
const SHELL_ASSETS = [
    '/',
    '/design-gallery.html',
    'https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Caveat:wght@400;600;700&display=swap',
];

// ── Install: pre-cache shell ──────────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then(cache =>
            cache.addAll(SHELL_ASSETS).catch(err => {
                // Non-fatal: fonts may fail in offline-install scenarios
                console.warn('[SW] Shell pre-cache partial failure:', err);
            })
        ).then(() => self.skipWaiting())
    );
});

// ── Activate: remove old caches ───────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k.startsWith('gallery-') && k !== SHELL_CACHE && k !== IMAGE_CACHE)
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch: route requests ─────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Never intercept non-GET or write endpoints
    if (request.method !== 'GET') return;
    if (url.pathname.endsWith('.php'))       return;
    if (url.pathname.includes('manifest.json') && request.method !== 'GET') return;

    // Gallery images → cache-first, then network, then placeholder
    if (isGalleryImage(url)) {
        event.respondWith(cacheFirstImage(request));
        return;
    }

    // manifest.json → network-first so updates propagate, fall back to cache
    if (url.pathname.endsWith('manifest.json')) {
        event.respondWith(networkFirstWithCache(request, SHELL_CACHE));
        return;
    }

    // Everything else (shell) → cache-first
    event.respondWith(cacheFirst(request, SHELL_CACHE));
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function isGalleryImage(url) {
    return /\.(jpg|jpeg|png|gif|webp|avif)$/i.test(url.pathname);
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        // Return a minimal offline placeholder for HTML requests
        if (request.headers.get('accept')?.includes('text/html')) {
            return new Response('<h2>You are offline</h2>', {
                headers: { 'Content-Type': 'text/html' },
            });
        }
        return new Response('', { status: 503 });
    }
}

async function cacheFirstImage(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(IMAGE_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        // Return a tiny 1×1 transparent PNG as placeholder
        const placeholder = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        return new Response(
            Uint8Array.from(atob(placeholder), c => c.charCodeAt(0)),
            { headers: { 'Content-Type': 'image/png' } }
        );
    }
}

async function networkFirstWithCache(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        const cached = await caches.match(request);
        return cached ?? new Response('{}', { headers: { 'Content-Type': 'application/json' } });
    }
}
