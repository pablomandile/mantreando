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
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
