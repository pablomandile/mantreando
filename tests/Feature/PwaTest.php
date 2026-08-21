<?php

test('el manifest existe, es JSON válido y apunta a íconos existentes', function () {
    $path = public_path('manifest.webmanifest');
    expect(file_exists($path))->toBeTrue();

    $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('mantreando')
        ->and($manifest['start_url'])->toBe('/practice')
        ->and($manifest['display'])->toBe('standalone');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }
});

test('el service worker existe y cubre las estrategias del plan', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain('cacheFirst') // /build inmutable
        ->toContain('staleWhileRevalidate') // /storage
        ->toContain('pageNetworkFirst') // navegaciones
        ->toContain('sync-outbox'); // Background Sync
});

test('el blade enlaza manifest y theme-color', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    expect($blade)->toContain('manifest.webmanifest')
        ->toContain('theme-color');
});

test('el service worker no le entrega el JSON de Inertia a una navegación', function () {
    $sw = file_get_contents(public_path('sw.js'));

    // Red de seguridad para los navegadores que YA guardaron el JSON bajo esa
    // URL: el `no-store` del middleware solo evita entradas nuevas, y cuando el
    // bug ocurre la app no arranca, así que el SW es el único que puede repararlo.
    expect($sw)->toContain("response.headers.get('x-inertia')")
        ->toContain('recuperarHtml')
        // La sesión vencida redirige al login, y una respuesta ya redirigida no
        // se le puede entregar a una navegación.
        ->toContain('response.redirected');

    // El heurístico habitual "o el Accept incluye text/html" rompe la SPA: el
    // router de Inertia manda ese Accept en sus XHR.
    expect($sw)->not->toContain("includes('text/html')");
});

test('el .htaccess prohíbe cachear sw.js y el manifest', function () {
    $htaccess = file_get_contents(public_path('.htaccess'));

    // Son los dos archivos con los que el navegador se entera de que hay algo
    // nuevo. Hostinger servía sw.js con `public, max-age=604800`, así que el CDN
    // entregaba el viejo por días y ninguna actualización llegaba.
    expect($htaccess)->toContain('sw\.js|manifest\.webmanifest')
        ->toContain('Header set Cache-Control "no-cache, must-revalidate, max-age=0"');
});

test('el service worker se registra con una URL versionada', function () {
    // El `no-cache` del .htaccess solo aplica a entradas nuevas del borde: la
    // copia vieja de /sw.js sigue en HIT hasta que expire, y hace falta una URL
    // distinta para saltearla.
    expect(file_get_contents(resource_path('js/lib/pwa.ts')))
        ->toContain("navigator.serviceWorker.register('/sw.js?v=");
});
