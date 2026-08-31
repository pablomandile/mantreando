<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetreatMantraRequest;
use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Las etapas de una deidad: qué mantra se recita, cuántas veces y en qué
 * orden. Solo administradores, igual que la deidad de la que cuelgan.
 */
class RetreatMantraController
{
    public function store(RetreatMantraRequest $request, RetreatDeity $deity): RedirectResponse
    {
        Gate::authorize('create', RetreatMantra::class);

        $data = $request->validated();

        $deity->mantras()->create([
            ...$data,
            'position' => $data['position'] ?? (int) $deity->mantras()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra agregado al retiro.']);

        return back();
    }

    public function update(RetreatMantraRequest $request, RetreatMantra $mantra): RedirectResponse
    {
        Gate::authorize('update', RetreatMantra::class);

        $data = $request->validated();

        if (($data['position'] ?? null) === null) {
            unset($data['position']);
        }

        $mantra->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra actualizado.']);

        return back();
    }

    public function destroy(RetreatMantra $mantra): RedirectResponse
    {
        Gate::authorize('delete', RetreatMantra::class);

        // Borrarla se llevaría el conteo de quien la esté recitando; el
        // restrictOnDelete de la migración lo impediría igual, pero con un
        // error de base en vez de un aviso.
        if ($mantra->progress()->exists()) {
            return back()->withErrors([
                'stage' => 'Este mantra tiene conteos registrados y no puede eliminarse.',
            ]);
        }

        $mantra->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra eliminado.']);

        return back();
    }
}
