// Ejazat HR-App Service Worker
const urlParams = new URLSearchParams(self.location.search);
const baseURL = urlParams.get('base') || '/';
const CACHE_NAME = 'ejazat-pwa-v1';
const OFFLINE_URL = baseURL + 'offline.php';

const STATIC_ASSETS = [
    baseURL,
    baseURL + 'index.php',
    baseURL + 'assets/css/style.css',
    baseURL + 'assets/js/script.js',
    baseURL + 'assets/images/icon-192.png',
    baseURL + 'assets/images/icon-512.png',
    baseURL + 'assets/images/icon.svg',
    OFFLINE_URL,
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&family=Almarai:wght@400;700&family=Tajawal:wght@400;700&family=Roboto:wght@400;700&family=Poppins:wght@400;600;700&display=swap'
];

// Installs the service worker and caches basic shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Pre-caching offline page and static assets');
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Cleans up old caches when a new service worker takes control
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Clearing old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Intercepts requests and implements standard caching strategy
self.addEventListener('fetch', (event) => {
    // Only handle GET requests and skip internal/extension requests
    if (event.request.method !== 'GET' || !event.request.url.startsWith(self.location.origin)) {
        return;
    }

    const url = new URL(event.request.url);

    // Dynamic pages (PHP) -> Network First, Fallback to Cache / Offline Page
    if (url.pathname.endsWith('.php') || url.pathname === baseURL) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Update cache with the latest successful response
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // Try to get from cache
                    return caches.match(event.request).then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // Fallback to offline page
                        return caches.match(OFFLINE_URL);
                    });
                })
        );
    } else {
        // Static assets (CSS, JS, Fonts, Images) -> Cache First, Fallback to Network
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request).then((response) => {
                    // Cache the new asset dynamically
                    if (response && response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                });
            })
        );
    }
});
