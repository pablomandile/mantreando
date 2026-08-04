<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Practice\UpdateStreaks;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetTodayController
{
    /**
     * Borra la práctica de UN día del usuario: sesiones y agregados.
     *
     * Con mantra_id borra solo ese mantra, que es como lo usa el botón
     * "Reiniciar" de la práctica: la meta diaria es por mantra, así que
     * reiniciar no puede llevarse por delante lo recitado con los otros.
     * Sin mantra_id, borra el día entero.
     *
     * Eso solo es real si se borra en el servidor: si no, el siguiente
     * bootstrap lo restaura.
     *
     * La fecha la manda el dispositivo (§7: local_date SIEMPRE se calcula en
     * el device y el servidor nunca la deriva); se acota a hoy o ayer en la
     * timezone del usuario para que un cliente no pueda barrer el historial.
     */
    public function __invoke(Request $request, UpdateStreaks $streaks): JsonResponse
    {
        $data = $request->validate([
            'local_date' => ['required', 'date_format:Y-m-d'],
            'mantra_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $date = $data['local_date'];
        $mantraId = $data['mantra_id'] ?? null;

        if (! $this->isRecent($user->timezone, $date)) {
            return response()->json([
                'message' => 'Solo se puede reiniciar el día en curso.',
            ], 422);
        }

        // Los mantras tocados: sus rachas por mantra hay que recalcularlas.
        $mantraIds = $user->practiceSessions()
            ->where('local_date', $date)
            ->when($mantraId !== null, fn ($q) => $q->where('mantra_id', $mantraId))
            ->pluck('mantra_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($user, $date, $mantraId) {
            $user->practiceSessions()
                ->where('local_date', $date)
                ->when($mantraId !== null, fn ($q) => $q->where('mantra_id', $mantraId))
                ->delete();

            if ($mantraId === null) {
                $user->dailyAggregates()->where('local_date', $date)->delete();

                return;
            }

            // Solo un mantra: se borra su fila y el total del día (mantra_id
            // null) se recalcula desde lo que quedó, no se borra.
            $user->dailyAggregates()
                ->where('local_date', $date)
                ->where('mantra_id', $mantraId)
                ->delete();

            $this->rebuildDayTotal($user, $date);
        });

        // Recalcula desde daily_aggregates, que es la fuente de las rachas.
        $streaks->forUser($user, $mantraIds);

        return response()->json(['data' => ['local_date' => $date]]);
    }

    /**
     * Rehace la fila de total del día (mantra_id null) sumando las filas por
     * mantra que quedaron. Si no queda ninguna, la borra.
     */
    private function rebuildDayTotal(User $user, string $date): void
    {
        $totals = $user->dailyAggregates()
            ->where('local_date', $date)
            ->whereNotNull('mantra_id')
            ->selectRaw('COALESCE(SUM(recitations),0) as recitations, COALESCE(SUM(malas),0) as malas, COALESCE(SUM(duration_seconds),0) as duration_seconds, COALESCE(SUM(sessions_count),0) as sessions_count')
            ->first();

        $dayTotal = $user->dailyAggregates()
            ->where('local_date', $date)
            ->whereNull('mantra_id');

        if ($totals === null || (int) $totals->recitations === 0) {
            $dayTotal->delete();

            return;
        }

        $dayTotal->update([
            'recitations' => (int) $totals->recitations,
            'malas' => (int) $totals->malas,
            'duration_seconds' => (int) $totals->duration_seconds,
            'sessions_count' => (int) $totals->sessions_count,
        ]);
    }

    /** Hoy o ayer en la timezone del usuario (la práctica cruza medianoche). */
    private function isRecent(?string $timezone, string $date): bool
    {
        try {
            $today = now($timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            $today = now();
        }

        return $date === $today->toDateString()
            || $date === $today->copy()->subDay()->toDateString();
    }
}
