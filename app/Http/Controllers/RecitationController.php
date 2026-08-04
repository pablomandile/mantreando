<?php

namespace App\Http\Controllers;

use App\Models\Recitation;
use Inertia\Inertia;
use Inertia\Response;

class RecitationController
{
    /**
     * Otras recitaciones: se leen enteras, así que van todas en una página
     * con el texto plegable. Sin paginación ni búsqueda: son pocas y fijas.
     */
    public function index(): Response
    {
        return Inertia::render('recitations/Index', [
            'recitations' => Recitation::orderBy('position')
                ->orderBy('title')
                ->get(['id', 'title', 'text']),
        ]);
    }
}
