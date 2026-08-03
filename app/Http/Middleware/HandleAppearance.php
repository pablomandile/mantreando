<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance') ?? 'system';

        View::share('appearance', $appearance);

        // Persistencia sin endpoint: el cliente ya manda su tema en la cookie
        // en cada request; si difiere del guardado, se sincroniza acá. Así
        // users.theme queda disponible para otros dispositivos y Capacitor.
        $user = $request->user();

        if (
            $user !== null
            && in_array($appearance, ['light', 'dark', 'system'], true)
            && $user->theme !== $appearance
        ) {
            $user->update(['theme' => $appearance]);
        }

        return $next($request);
    }
}
