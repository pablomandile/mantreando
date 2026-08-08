<?php

namespace App\Http\Controllers;

use App\Models\Recitation;
use App\Models\RecitationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecitationController
{
    /**
     * Otras recitaciones: se leen enteras, así que van todas en una página
     * con el texto plegable. Sin paginación ni búsqueda: son pocas y fijas.
     *
     * Cada una lleva su compromiso diario y lo recitado hoy. Es una cuenta
     * aparte de la de los mantras: no comparten objetivo.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = $this->localDate($request);

        $commitments = $user->recitations()
            ->pluck('daily_commitment', 'recitations.id');

        $todayCounts = RecitationLog::where('user_id', $user->id)
            ->where('local_date', $today)
            ->pluck('count', 'recitation_id');

        $recitations = Recitation::orderBy('position')
            ->orderBy('title')
            ->get(['id', 'title', 'text', 'color'])
            ->map(fn (Recitation $recitation): array => [
                'id' => $recitation->id,
                'title' => $recitation->title,
                'text' => $recitation->text,
                'color' => $recitation->color?->value,
                'daily_commitment' => $commitments[$recitation->id] ?? null,
                'today_count' => (int) ($todayCounts[$recitation->id] ?? 0),
            ])
            ->values()
            ->all();

        return Inertia::render('recitations/Index', [
            'recitations' => $recitations,
            'localDate' => $today,
        ]);
    }

    /** Fija (o borra, con null) el compromiso diario de una recitación. */
    public function updateCommitment(Request $request, Recitation $recitation): RedirectResponse
    {
        $data = $request->validate([
            'daily_commitment' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], [], ['daily_commitment' => 'compromiso diario']);

        $request->user()->recitations()->syncWithoutDetaching([
            $recitation->id => ['daily_commitment' => $data['daily_commitment'] ?? null],
        ]);

        return back();
    }

    /**
     * Registra recitaciones de hoy. La cantidad se SUMA a lo que ya haya,
     * así se puede ir registrando de a poco a lo largo del día.
     */
    public function log(Request $request, Recitation $recitation): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
            'local_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['count' => 'cantidad']);

        $log = RecitationLog::firstOrCreate([
            'user_id' => $request->user()->id,
            'recitation_id' => $recitation->id,
            'local_date' => $data['local_date'] ?? $this->localDate($request),
        ]);

        $log->increment('count', $data['count']);

        return back();
    }

    /**
     * El día del dispositivo (§7: local_date se calcula en el device). El
     * cliente lo manda en la query; si falta, se cae a la timezone del perfil.
     */
    private function localDate(Request $request): string
    {
        $fromDevice = $request->query('local_date');

        if (is_string($fromDevice) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDevice)) {
            return $fromDevice;
        }

        try {
            return now($request->user()->timezone ?: config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
