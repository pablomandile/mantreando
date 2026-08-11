<?php

namespace App\Http\Controllers;

use App\Enums\MantraColor;
use App\Http\Requests\RecitationRequest;
use App\Models\Recitation;
use App\Models\RecitationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
     *
     * La lista es la misma para todas las cuentas (las mantiene un
     * administrador); lo único que cambia por usuario son su compromiso, su
     * cuenta de hoy y si aparecen los controles de edición.
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
            'canManage' => Gate::allows('create', Recitation::class),
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

    public function create(): Response
    {
        Gate::authorize('create', Recitation::class);

        return Inertia::render('recitations/Create', [
            // El orden sugerido deja la nueva al final de la lista.
            'nextPosition' => (int) Recitation::max('position') + 1,
        ]);
    }

    public function store(RecitationRequest $request): RedirectResponse
    {
        Gate::authorize('create', Recitation::class);

        $data = $request->validated();
        $position = $data['position'] ?? (int) Recitation::max('position') + 1;

        Recitation::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'position' => $position,
            // Sin color la tarjeta queda gris entre las demás, que sí lo traen
            // del seeder.
            'color' => $this->colorFor($position),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recitación creada.']);

        return to_route('recitations.index');
    }

    public function edit(Recitation $recitation): Response
    {
        Gate::authorize('update', $recitation);

        return Inertia::render('recitations/Edit', [
            'recitation' => $recitation->only(['id', 'title', 'text', 'position']),
        ]);
    }

    public function update(RecitationRequest $request, Recitation $recitation): RedirectResponse
    {
        Gate::authorize('update', $recitation);

        // El slug NO se regenera al cambiar el título: es la identidad estable
        // que usa el seeder para no duplicar filas (ver la migración).
        $recitation->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recitación actualizada.']);

        return to_route('recitations.index');
    }

    public function destroy(Recitation $recitation): RedirectResponse
    {
        Gate::authorize('delete', $recitation);

        $recitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recitación eliminada.']);

        return to_route('recitations.index');
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

    /**
     * Color al azar para una recitación nueva, evitando el de las vecinas por
     * posición: es la regla que ya sigue el seeder, dos tarjetas seguidas no
     * repiten color.
     *
     * Con positions empatadas la lista desempata por título, así que "vecina"
     * es una aproximación; alcanza para que no queden dos iguales pegadas.
     *
     * array_rand (Mersenne Twister) y no random_int a propósito: esto es
     * estética, no criptografía, y así los tests pueden sembrarlo con mt_srand
     * para ser deterministas.
     */
    private function colorFor(int $position): MantraColor
    {
        $taken = array_filter([
            Recitation::where('position', '<=', $position)
                ->orderByDesc('position')->first()?->color?->value,
            Recitation::where('position', '>=', $position)
                ->orderBy('position')->first()?->color?->value,
        ]);

        $options = array_values(array_filter(
            MantraColor::cases(),
            fn (MantraColor $color): bool => ! in_array($color->value, $taken, true),
        ));

        // Con 9 colores y 2 vecinas nunca queda vacío; si la paleta se achicara,
        // mejor repetir un color que tirar un error.
        return $options === []
            ? MantraColor::Neutral
            : $options[array_rand($options)];
    }

    /**
     * Slug derivado del título, con sufijo si ya existe. Un título vacío de
     * caracteres latinos (p. ej. solo tibetano) daría cadena vacía: en ese caso
     * cae en 'recitacion'.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 70, '') ?: 'recitacion';
        $slug = $base;
        $suffix = 2;

        while (Recitation::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
