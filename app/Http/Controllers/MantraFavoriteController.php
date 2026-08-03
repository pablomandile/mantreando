<?php

namespace App\Http\Controllers;

use App\Models\Mantra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MantraFavoriteController
{
    /** Toggle de favorito: crea o actualiza la fila pivot del usuario. */
    public function __invoke(Request $request, Mantra $mantra): RedirectResponse
    {
        Gate::authorize('view', $mantra);

        $user = $request->user();
        $current = $user->mantras()->where('mantra_id', $mantra->id)->first();

        $user->mantras()->syncWithoutDetaching([
            $mantra->id => [
                'is_favorite' => ! (bool) ($current?->pivot->is_favorite ?? false),
            ],
        ]);

        return back();
    }
}
