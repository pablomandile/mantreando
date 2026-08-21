<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Evita que el navegador guarde la respuesta XHR de Inertia.
     *
     * Una misma URL contesta HTML (navegación) o JSON (XHR) según el header
     * X-Inertia, y lo único que distingue las dos respuestas para una caché es
     * el `Vary: X-Inertia` que pone el middleware padre. El CDN de Hostinger lo
     * borra cuando comprime con brotli —que es lo que pide cualquier navegador
     * real—, así que las dos comparten clave de caché: la URL pelada. Si además
     * la respuesta es guardable, al restaurar una pestaña descartada Chrome
     * reusa la entrada JSON sin revalidar y el usuario ve el JSON crudo.
     *
     * Va acá y no en un middleware aparte: el padre setea el Vary y puede
     * reemplazar la respuesta entera en onVersionChange(), así que cualquier
     * middleware posterior del grupo `web` correría antes y quedaría pisado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // El CDN lo borra, pero se declara igual: es lo correcto y sirve en
        // cualquier intermediario que sí lo respete.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        // `no-store`, no `no-cache`: `no-cache` permite guardar y solo obliga a
        // revalidar, y la navegación de historial —el caso de este bug— saltea
        // la revalidación. Y solo sobre el XHR, nunca sobre el HTML: `no-store`
        // en el documento principal desactiva el back/forward cache de Chrome.
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'googleEnabled' => (bool) config('services.google.client_id'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
