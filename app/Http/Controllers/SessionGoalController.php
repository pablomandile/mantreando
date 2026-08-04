<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionGoalController
{
    public const DEFAULT_DAILY_GOAL = 108;

    /**
     * Panel "Objetivo": las metas de la práctica.
     * - daily_goal: recitaciones por día (todas las prácticas). Es la meta
     *   del switch "Seguir objetivo" del mala. Default: 108.
     * - total_goal: recitaciones acumuladas de por vida (opcional).
     * (Los compromisos POR MANTRA siguen viviendo en cada mantra.)
     */
    public function edit(Request $request): Response
    {
        $settings = $request->user()->settings ?? [];

        return Inertia::render('goal/Index', [
            'dailyGoal' => (int) ($settings['daily_goal'] ?? self::DEFAULT_DAILY_GOAL),
            'totalGoal' => isset($settings['total_goal']) ? (int) $settings['total_goal'] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'daily_goal' => ['required', 'integer', 'min:1', 'max:1000000'],
                'total_goal' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            ],
            [],
            [
                'daily_goal' => 'objetivo diario',
                'total_goal' => 'objetivo global',
            ],
        );

        $user = $request->user();
        $user->update([
            'settings' => [...($user->settings ?? []), ...$validated],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objetivo guardado.')]);

        return back();
    }
}
