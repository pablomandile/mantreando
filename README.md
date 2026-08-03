# malaflow

Mala virtual para la práctica diaria de mantras budistas. PWA con Laravel 13 +
Inertia 3 + Vue 3 (TypeScript) que funciona **offline durante la práctica**:
las sesiones se registran localmente (IndexedDB) y se sincronizan de forma
idempotente cuando hay conexión.

## Desarrollo

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# crear la base MySQL `malaflow` (Laragon: root sin password)
php artisan migrate --seed
php artisan storage:link
composer run dev   # server + queue + vite
```

Tests: `./vendor/bin/pest` (backend, sqlite :memory:) y `npm run test:js`
(motor y física del mala, Vitest).

## Arquitectura (resumen)

- **Híbrido Inertia + isla offline**: biblioteca/perfil/stats son
  server-driven; el módulo de práctica (`resources/js/lib/practice`,
  `lib/mala`) es TypeScript puro sin dependencias de red durante la práctica.
- **Integridad del conteo**: cada sesión lleva un UUID generado en el
  cliente; el servidor hace insert-or-ignore por UUID (los reintentos de la
  outbox nunca duplican). `local_date` se calcula SIEMPRE en el dispositivo.
- **Agregados y rachas**: el dashboard lee de `daily_aggregates`
  (precalculado); las rachas se RECALCULAN desde los agregados (robusto ante
  sesiones offline que llegan fuera de orden).
- **PWA**: service worker a mano (`public/sw.js`) — assets cache-first,
  navegaciones network-first con fallback, Background Sync de la outbox con
  fallback universal de sincronizar al abrir.

## Google OAuth

Crear un OAuth Client en Google Cloud Console y setear `GOOGLE_CLIENT_ID` y
`GOOGLE_CLIENT_SECRET`. Google no acepta dominios `.test` como redirect URI:
para probar en vivo usar `php artisan serve` con
`GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback`.

## App Android (Capacitor)

El proyecto nativo ya está generado en `android/` y el plugin de Haptics
instalado (la vibración nativa resuelve la limitación de iOS/web). La app
envuelve al sitio hosteado (`server.url` en `capacitor.config.ts`) — la isla
de práctica sigue funcionando offline por el service worker + IndexedDB.

Para compilar el APK hace falta **Android Studio** (o JDK 17 + Android SDK),
que no está instalado en esta máquina:

1. Instalar [Android Studio](https://developer.android.com/studio).
2. En `capacitor.config.ts`: apuntar `server.url` al dominio de producción
   con HTTPS y **quitar `cleartext`**. Para probar contra el server local
   desde el emulador, dejar `http://10.0.2.2:8000` y correr
   `php artisan serve --host=0.0.0.0`.
3. `npx cap sync android`
4. `npx cap open android` → Build → Build APK(s), o por CLI:
   `cd android && .\gradlew assembleDebug`
   (el APK queda en `android/app/build/outputs/apk/debug/`).

## Deploy (VPS)

- HTTPS obligatorio (el service worker lo exige fuera de localhost).
- nginx con **gzip/brotli** (Lighthouse: ~2.7 s de mejora en FCP), HTTP/2 y
  cache headers largos para `/build/*` (nombres con hash).
- `php artisan config:cache route:cache view:cache`, opcache habilitado,
  `QUEUE_CONNECTION` real (database + worker) si crece el volumen.
- Opcional: SSR de Inertia (`npm run build:ssr` + `composer dev:ssr`) si se
  quiere mejorar el First Paint de las páginas públicas.
