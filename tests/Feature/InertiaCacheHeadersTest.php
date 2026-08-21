<?php

use App\Http\Middleware\HandleInertiaRequests;

/*
 * Regresión del JSON crudo en pantalla.
 *
 * La misma URL contesta HTML o JSON según el header X-Inertia. El CDN de
 * Hostinger borra el `Vary: X-Inertia` que las distingue cuando comprime con
 * brotli, así que las dos comparten clave de caché; si además la respuesta es
 * guardable, al restaurar una pestaña descartada Chrome reusa la entrada JSON
 * sin revalidar y el usuario ve el JSON crudo.
 */

/** La versión del asset, o Inertia contesta 409 en vez de la página. */
function versionDeInertia(): string
{
    return (string) app(HandleInertiaRequests::class)->version(request());
}

test('prohíbe guardar la respuesta XHR de Inertia', function () {
    $respuesta = $this->get('/login', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => versionDeInertia(),
    ]);

    $respuesta->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toContain('application/json')
        // `no-cache` no alcanza: permite guardar y solo obliga a revalidar, y la
        // navegación de historial —el caso de este bug— saltea la revalidación.
        ->and($respuesta->headers->get('Cache-Control'))->toContain('no-store')
        ->and($respuesta->headers->get('Vary'))->toContain('X-Inertia');
});

test('deja cacheable el documento HTML, para no perder el bfcache', function () {
    $respuesta = $this->get('/login');

    $respuesta->assertOk();

    // Chrome no guarda en bfcache las páginas servidas con `no-store`: cada
    // "atrás" pasaría a ser una ida completa a la red, sin ningún síntoma que lo
    // delate. Por eso el `no-store` va solo sobre el XHR.
    expect($respuesta->headers->get('Content-Type'))->toContain('text/html')
        ->and($respuesta->headers->get('Cache-Control'))->not->toContain('no-store');
});
