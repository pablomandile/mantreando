<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Regla única de dónde vive una imagen y cómo se le arma la URL.
 *
 * Las que empiezan con 'img/' viajan con la app (public/, versionadas en el
 * repo) y las usan los contenidos del sistema. Todo lo demás es una subida y
 * vive en el disco public (storage/app/public), que no está en el repo.
 *
 * La comparten los mantras y las deidades del retiro, que pueden apuntar a la
 * misma imagen: una deidad del retiro suele reusar la lámina del mantra.
 */
trait ResolvesImagePath
{
    private const APP_IMAGE_PREFIX = 'img/';

    protected function isAppImage(?string $path): bool
    {
        return $path !== null && str_starts_with($path, self::APP_IMAGE_PREFIX);
    }

    protected function resolveImageUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return $this->isAppImage($path)
            ? asset($path)
            : Storage::disk('public')->url($path);
    }

    /**
     * Miniatura cuadrada de 128 px. Las imágenes de la app traen una al lado
     * (img/budas/thumb/x.jpg); las subidas no, y reusan la original.
     */
    protected function resolveImageThumbUrl(?string $path): ?string
    {
        if (! $this->isAppImage($path)) {
            return $this->resolveImageUrl($path);
        }

        return asset(dirname((string) $path).'/thumb/'.basename((string) $path));
    }
}
