import { syncAll } from '@/lib/practice/sync';

/**
 * Registro del service worker y cableado de Background Sync.
 * El SW avisa por postMessage cuando el navegador recupera conectividad
 * (evento sync) y la pestaña drena la outbox. Si no hay pestañas abiertas,
 * el fallback universal sigue vigente: sincronizar al abrir la app.
 */
export function initializePwa(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        // El `?v=` no es decorativo. El CDN de Hostinger tenia /sw.js cacheado
        // con `public, max-age=604800`, y el navegador se entera de que hay un
        // service worker nuevo justamente pegandole a la URL registrada: con la
        // copia vieja en el borde, ninguna actualizacion llegaba por dias. El
        // `no-cache` que puso public/.htaccess solo aplica a entradas NUEVAS del
        // borde, asi que hace falta una URL distinta para saltear la vieja.
        // Registrar otro scriptURL con el mismo scope reemplaza la registracion
        // anterior. Con el no-cache ya puesto, este numero no se vuelve a subir.
        void navigator.serviceWorker.register('/sw.js?v=2');
    });

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'sync-outbox') {
            void syncAll();
        }
    });
}

/**
 * Pide al navegador un one-off Background Sync (si existe soporte).
 * Se llama al encolar una sesión: si el usuario cierra la pestaña offline,
 * el SW se despierta al volver la conexión.
 */
export async function requestBackgroundSync(): Promise<void> {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.ready;

        if ('sync' in registration) {
            await (
                registration as ServiceWorkerRegistration & {
                    sync: { register(tag: string): Promise<void> };
                }
            ).sync.register('sync-outbox');
        }
    } catch {
        // Sin soporte (Firefox/Safari) o sin permiso: el fallback de
        // sincronizar al abrir la app cubre el caso.
    }
}
