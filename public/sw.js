// Stratum CMS — minimal PWA offline shell (Stage 9 PWA support, 2026-07-19).
// Deliberately narrow scope: cache-first for genuinely static assets only
// (CSS/JS/images), never for HTML — most pages here are per-member
// personalized (unread counts, forum posts, dashboards), so caching HTML
// would show stale, actively misleading content. Navigations are always
// network-first; only a real network failure falls back to a tiny cached
// offline notice. No background sync, no push event handler — real Web
// Push is a separate, not-yet-made decision (needs a new server-side
// dependency + VAPID keys), out of scope for this pass.

const CACHE_NAME = 'stratum-shell-v1';
const OFFLINE_URL = '/offline.html';

const SHELL_ASSETS = [
    '/assets/css/theme.css',
    '/assets/images/icon-circle.png',
    '/assets/images/favicon.png',
    OFFLINE_URL,
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(SHELL_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) { return key !== CACHE_NAME; })
                    .map(function (key) { return caches.delete(key); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Page navigations: always go to the network first (this app's
    // content is personalized per member and must never be served stale).
    // Only a genuine network failure (offline) falls back to the cached
    // offline notice.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // Static assets under /assets/: cache-first, since these are
    // versioned/stable and this is exactly what a service worker's cache
    // is for.
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.match(request).then(function (cached) {
                return cached || fetch(request).then(function (response) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) { cache.put(request, copy); });
                    return response;
                });
            })
        );
    }
});
