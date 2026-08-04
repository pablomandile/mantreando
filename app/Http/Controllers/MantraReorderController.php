<?php

namespace App\Http\Controllers;

use App\Models\Mantra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MantraReorderController
{
    /**
     * Guarda el orden personal de la biblioteca: recibe la lista completa
     * de IDs en el orden elegido y persiste posiciones 1..n en la pivot.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $user = $request->user();

        $accessible = Mantra::accessibleBy($user)
            ->whereIn('id', $validated['ids'])
            ->pluck('id')
            ->flip();

        $sync = [];

        foreach (array_values($validated['ids']) as $index => $id) {
            if ($accessible->has((int) $id)) {
                $sync[(int) $id] = ['position' => $index + 1];
            }
        }

        $user->mantras()->syncWithoutDetaching($sync);

        return back();
    }
}
