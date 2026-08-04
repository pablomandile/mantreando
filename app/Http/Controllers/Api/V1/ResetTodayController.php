<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Practice\UpdateStreaks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetTodayController
{
    /**
     * Borra la práctica de UN día del usuario: sesiones y agregados.
     *
     * El botón "Reiniciar" de la práctica pone en cero el contador y el total
     * del día, y eso solo es real si se borra en el servidor: si no, el
     * siguiente bootstrap lo restaura.
     *
     * La fecha la manda el dispositivo (§7: local_date SIEMPRE se calcula en
     * el device y el servidor nunca la deriva); se acota a hoy o ayer en la
     * timezone del usuario para que un cliente no pueda barrer el historial.
     */
    public function __invoke(Request $request, UpdateStreaks $streaks): JsonResponse
    {
        $data = $request->validate([
            'local_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $date = $data['local_date'];

        if (! $this->isRecent($user->timezone, $date)) {
            return response()->json([
                'message' => 'Solo se puede reiniciar el día en curso.',
            ], 422);
        }

        // Los mantras tocados hoy: sus rachas por mantra hay que recalcularlas.
        $mantraIds = $user->practiceSessions()
            ->where('local_date', $date)
            ->pluck('mantra_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($user, $date) {
            $user->practiceSessions()->where('local_date', $date)->delete();
            $user->dailyAggregates()->where('local_date', $date)->delete();
        });

        // Recalcula desde daily_aggregates, que es la fuente de las rachas.
        $streaks->forUser($user, $mantraIds);

        return response()->json(['data' => ['local_date' => $date]]);
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
