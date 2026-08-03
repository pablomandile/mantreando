<?php

namespace App\Http\Controllers;

use App\Models\Mantra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MantraPracticeSettingsController
{
    /**
     * Compromiso diario y objetivo total del usuario para un mantra
     * (viven en la pivot: los mantras del sistema son compartidos).
     */
    public function __invoke(Request $request, Mantra $mantra): RedirectResponse
    {
        Gate::authorize('view', $mantra);

        $validated = $request->validate([
            'daily_commitment' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'total_goal' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
        ], [], [
            'daily_commitment' => 'compromiso diario',
            'total_goal' => 'objetivo total',
        ]);

        $request->user()->mantras()->syncWithoutDetaching([
            $mantra->id => $validated,
        ]);

        return back();
    }
}
