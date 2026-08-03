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
            ->orderBy('name')
            ->get()
            ->each(function (Mantra $mantra) use ($prefs) {
                $mantra->userPrefs = $prefs->get($mantra->id);
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
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
