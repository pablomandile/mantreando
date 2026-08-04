<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MantraResource;
use App\Models\Mantra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PracticeBootstrapController
{
    /**
     * Todo lo que la isla de práctica cachea en IndexedDB para funcionar
     * offline: mantras accesibles (sistema + propios) con las preferencias
     * del usuario, y sus datos de configuración.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $prefs = DB::table('mantra_user')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('mantra_id');

        $mantras = Mantra::query()
            ->accessibleBy($user)
            ->with('category')
            // Mismo orden personal que la biblioteca (pivot.position)
            ->leftJoin('mantra_user', function ($join) use ($user) {
                $join->on('mantra_user.mantra_id', '=', 'mantras.id')
                    ->where('mantra_user.user_id', $user->id);
            })
            ->orderByRaw('COALESCE(mantra_user.position, 999999)')
            ->orderBy('mantras.name')
            ->select('mantras.*')
            ->get()
            ->each(function (Mantra $mantra, int $index) use ($prefs) {
                $mantra->userPrefs = $prefs->get($mantra->id);
                $mantra->sortIndex = $index; // orden personal, para la isla
            });

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

        $preset = \App\Models\MalaPreset::where('user_id', $user->id)
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
                    'texture_url' => $preset?->texture_url,
                ],
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
