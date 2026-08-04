<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locale de la request: la preferencia del usuario autenticado
     * (users.locale, editable en Perfil) o el default de la app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sin ?-> : dentro de ?? el acceso sobre null ya devuelve null.
        $locale = $request->user()->locale ?? config('app.locale');

        if (in_array($locale, ['es', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
