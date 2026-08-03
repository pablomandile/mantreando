import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Capacitor envuelve la app COMO CLIENTE del servidor (server.url): la app
 * es Inertia server-driven, así que el APK carga el sitio hosteado y la isla
 * de práctica sigue funcionando offline gracias al service worker + IndexedDB.
 *
 * Antes de compilar el APK de producción: apuntar server.url al dominio
 * real con HTTPS y quitar cleartext.
 */
const config: CapacitorConfig = {
    appId: 'ar.com.malaflow.app',
    appName: 'malaflow',
    webDir: 'public/build', // no se usa con server.url, pero el CLI lo exige
    server: {
        // DEV: la IP de tu máquina en la LAN (php artisan serve --host=0.0.0.0)
        // PROD: 'https://malaflow.tu-dominio.com'
        url: 'http://10.0.2.2:8000', // 10.0.2.2 = localhost del host en el emulador Android
        cleartext: true, // solo para dev sin HTTPS — QUITAR en producción
    },
};

export default config;
