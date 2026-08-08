<?php

namespace App\Http\Controllers;

use App\Actions\Practice\ListIslandMantras;
use App\Http\Resources\MantraResource;
use App\Models\MalaPreset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController
{
    /**
     * La práctica es una isla offline que vive de IndexedDB, pero abrir esa
     * base tarda —con la PWA recién abierta se notaba— y hasta entonces el
     * select de mantras aparecía vacío. Mandar la biblioteca en el primer
     * render lo pinta lleno de entrada; cuando IndexedDB responde toma el
     * mando la cache local, que sigue siendo la fuente de verdad de la isla.
     *
     * Offline también sirve: el service worker guarda este HTML, así que la
     * lista viaja con él aunque no haya red.
     *
     * Van también los ajustes y el preset del mala, por la misma razón: sin
     * ellos la pantalla mostraba el objetivo por defecto (108) en vez del
     * configurado, y el mala con las cuentas y la borla de fábrica, hasta que
     * IndexedDB respondía.
     */
    public function __invoke(Request $request, ListIslandMantras $listMantras): Response
    {
        $user = $request->user();

        $preset = MalaPreset::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return Inertia::render('practice/Index', [
            // resolve() y no la colección tal cual: al serializarse, un
            // JsonResource se envuelve en {"data": [...]}, y el prop tiene que
            // ser la lista pelada para que la isla la use igual que lo que
            // cachea en IndexedDB.
            'mantras' => MantraResource::collection(
                $listMantras->handle($user)
            )->resolve(),
            // Mismas formas que el bootstrap de la isla: se usan tal cual
            // hasta que la cache local emite.
            'settings' => $user->settings ?? (object) [],
            'preset' => [
                'material' => $preset->material ?? 'wood',
                'tassel_color' => $preset?->tassel_color,
                'texture_url' => $preset?->texture_url,
            ],
        ]);
    }
}
