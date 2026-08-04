<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimezoneController extends Controller
{
    /**
     * Captura silenciosa de la timezone del dispositivo.
     *
     * La invoca useDeviceTimezone() una sola vez cuando el usuario autenticado
     * no tiene timezone (típicamente cuentas creadas vía Google, donde el
     * servidor no puede conocerla). El usuario puede cambiarla después en
     * Ajustes → Perfil.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // all_with_bc: acepta los IDs legacy que reportan Chrome/Edge
            'timezone' => ['required', 'string', 'timezone:all_with_bc'],
        ]);

        $request->user()->update(['timezone' => $validated['timezone']]);

        return back();
    }
}
