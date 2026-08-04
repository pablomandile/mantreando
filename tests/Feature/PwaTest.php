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
