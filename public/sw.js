/**
 * Service worker de mantreando (Etapa 10). Escrito a mano — sin Workbox:
 * menos magia, cero riesgo de incompatibilidad con Vite 8/rolldown.
 *
 * Estrategias:
 * - /build/*  (assets con hash inmutable) → cache-first.
 * - /storage/* (imágenes/texturas)        → stale-while-revalidate.
 * - Navegaciones (HTML)                   → network-first con fallback a la
 *   copia cacheada de esa URL y, en última instancia, al shell de /practice.
 * - /api/*                                → passthrough: la isla ya tiene su
 *   propia cache en IndexedDB (Dexie) y el sync maneja el offline.
 *
 * Background Sync: al recuperar conectividad el SW avisa a las pestañas
 * abiertas para que drenen la outbox (postMessage 'sync-outbox'). Si no hay
 * pestañas, el fallback de siempre aplica: se sincroniza al abrir la app
 * (regla del plan: Background Sync nunca es el único camino).
 */

const VERSION = 'v1';
const ASSET_CACHE = `mantreando-assets-${VERSION}`;
const PAGE_CACHE = `mantreando-pages-${VERSION}`;
const KNOWN_CACHES = [ASSET_CACHE, PAGE_CACHE];
// Prefijos propios (incluye el nombre anterior de la app, para limpiar
// caches huérfanas en clientes que ya la tenían instalada).
const OWN_PREFIXES = ['mantreando-', 'malaflow-'];

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();
            await Promise.all(
                names
                    .filter(
                        (name) =>
                            OWN_PREFIXES.some((prefix) => name.startsWith(prefix)) &&
                            !KNOWN_CACHES.includes(name),
                    )
                    .map((name) => caches.delete(name)),
            );
            await self.clients.claim();
        })(),
    );
});

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(ASSET_CACHE);
        cache.put(request, response.clone());
    }

    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached ?? network;
}

async function pageNetworkFirst(request) {
    const cache = await caches.open(PAGE_CACHE);

    try {
        const response = await fetch(request);

        if (response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await cache.match(request, { ignoreSearch: true });

        return cached ?? (await cache.match('/practice')) ?? Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        return; // la isla maneja su propio offline
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    // /img: imágenes que vienen con la app (los budas de los mantras del
    // sistema); /storage: las que sube el usuario. Se cachean al vuelo, no
    // en el install, así la primera visita no descarga la biblioteca entera.
    if (
        url.pathname.startsWith('/storage/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/img/')
    ) {
        event.respondWith(staleWhileRevalidate(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(pageNetworkFirst(request));
    }
});

// Background Sync: avisar a las pestañas que drenen la outbox.
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-outbox') {
        event.waitUntil(
            self.clients
                .matchAll({ type: 'window' })
                .then((clients) =>
                    clients.forEach((client) => client.postMessage({ type: 'sync-outbox' })),
                ),
        );
    }
});
