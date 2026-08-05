<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Practice\ListIslandMantras;
use App\Http\Resources\MantraResource;
use App\Models\MalaPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticeBootstrapController
{
    /**
     * Todo lo que la isla de práctica cachea en IndexedDB para funcionar
     * offline: mantras accesibles (sistema + propios) con las preferencias
     * del usuario, y sus datos de configuración.
     */
    public function __invoke(Request $request, ListIslandMantras $listMantras): JsonResponse
    {
        $user = $request->user();

        $mantras = $listMantras->handle($user);

        // "Hoy" del usuario para LEER agregados (compromisos diarios).
        // Esto no viola la regla de oro: las local_date de las SESIONES
        // siguen viniendo siempre del dispositivo.
        try {
            $localToday = now($user->timezone ?: config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            $localToday = now()->toDateString(); // timezone corrupta: UTC
        }

        $todayAggregates = $user->dailyAggregates()
            ->where('local_date', $localToday)
            ->get();

        // Totales históricos por mantra (progreso de objetivos totales)
        $totalsByMantra = $user->dailyAggregates()
            ->whereNotNull('mantra_id')
            ->selectRaw('mantra_id, SUM(recitations) as total')
            ->groupBy('mantra_id')
            ->pluck('total', 'mantra_id')
            ->mapWithKeys(fn ($total, $id) => [(string) $id => (int) $total]);

        $globalStreak = $user->streaks()->whereNull('mantra_id')->first();

        $preset = MalaPreset::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'timezone' => $user->timezone,
                    'locale' => $user->locale,
                    'theme' => $user->theme,
                    'settings' => $user->settings ?? (object) [],
                ],
                'mantras' => MantraResource::collection($mantras),
                'today' => [
                    'local_date' => $localToday,
                    'total' => (int) ($todayAggregates->firstWhere('mantra_id', null)->recitations ?? 0),
                    'by_mantra' => $todayAggregates
                        ->whereNotNull('mantra_id')
                        ->mapWithKeys(fn ($row) => [(string) $row->mantra_id => (int) $row->recitations]),
                ],
                'totals' => ['by_mantra' => $totalsByMantra],
                'streak' => [
                    'current' => (int) ($globalStreak->current_count ?? 0),
                    'max' => (int) ($globalStreak->max_count ?? 0),
                ],
                'mala_preset' => [
                    'material' => $preset->material ?? 'wood',
                    'tassel_color' => $preset?->tassel_color,
                    'texture_url' => $preset?->texture_url,
                ],
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
