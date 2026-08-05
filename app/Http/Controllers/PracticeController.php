<?php

namespace App\Http\Controllers;

use App\Actions\Practice\ListIslandMantras;
use App\Http\Resources\MantraResource;
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
     */
    public function __invoke(Request $request, ListIslandMantras $listMantras): Response
    {
        return Inertia::render('practice/Index', [
            'mantras' => MantraResource::collection(
                $listMantras->handle($request->user())
            ),
        ]);
    }
}
