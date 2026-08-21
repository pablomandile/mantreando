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

/**
 * Red de seguridad para el JSON de Inertia servido a una navegacion.
 *
 * El middleware (HandleInertiaRequests) ya marca la respuesta XHR con
 * `no-store`, pero eso solo evita entradas NUEVAS: los navegadores que ya
 * guardaron el JSON bajo esta URL lo siguen reusando, y cuando pasa la app no
 * arranca, asi que ningun script de la pagina puede repararlo. El unico que
 * intercepta la navegacion es este service worker.
 */
async function recuperarHtml(request) {
    return fetch(request.url, {
        cache: 'reload',
        headers: { Accept: 'text/html' },
    });
}

async function pageNetworkFirst(request) {
    const cache = await caches.open(PAGE_CACHE);

    try {
        let response = await fetch(request);

        // Se mira el header X-Inertia de la RESPUESTA, no el content-type: una
        // navegacion puede legitimamente contestar JSON (una exportacion que se
        // descarga) y no hay que pedirla dos veces. Ese header solo aparece en
        // una respuesta armada para un XHR de Inertia.
        if (response.headers.get('x-inertia')) {
            response = await recuperarHtml(request);

            // Si la sesion vencio, la URL redirige al login. Una respuesta ya
            // redirigida no se le puede entregar a una navegacion —el Service
            // Worker API lo prohibe—, asi que se le pasa el redirect y lo sigue
            // el navegador. Tampoco se cachea.
            if (response.redirected) {
                return Response.redirect(response.url, 302);
            }
        }

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

    // `request.mode === 'navigate'` y nada mas. NO agregar el heuristico
    // habitual de "o el Accept incluye text/html": el router de Inertia manda
    // `Accept: text/html, application/xhtml+xml` en sus XHR, asi que ese
    // heuristico da true para cada navegacion de la SPA y pageNetworkFirst les
    // "arreglaria" la respuesta devolviendo el HTML de arranque en vez del JSON
    // de la pagina. La app dejaria de navegar por completo.
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
