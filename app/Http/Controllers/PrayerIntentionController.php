<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrayerIntentionRequest;
use App\Models\PrayerIntention;
use App\Models\PrayerReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PrayerIntentionController
{
    /**
     * Lista de oración: por quién ora el usuario. Es privada, así que todo
     * sale de la relación y no de un where suelto sobre el modelo.
     *
     * No hay paginación ni búsqueda: es una lista corta que se lee entera de
     * un vistazo, que es justamente para lo que sirve.
     *
     * Los archivados se piden por query string (?archived=1) y no con una
     * ruta propia, para que el item del menú siga resaltado: NavMain compara
     * el pathname.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $showingArchived = $request->boolean('archived');

        $query = $user->prayerIntentions()->with('reasons:id,name,color');

        if ($showingArchived) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $intentions = $query->orderBy('name')
            ->get(['id', 'name', 'custom_reason', 'archived_at'])
            ->map(fn (PrayerIntention $intention): array => [
                'id' => $intention->id,
                'name' => $intention->name,
                'custom_reason' => $intention->custom_reason,
                'archived_at' => $intention->archived_at?->toDateString(),
                'reason_ids' => $intention->reasons->pluck('id')->all(),
                'reasons' => $intention->reasons
                    ->map(fn (PrayerReason $reason): array => [
                        'id' => $reason->id,
                        'name' => $reason->name,
                        'color' => $reason->color?->value,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('prayers/Index', [
            'intentions' => $intentions,
            'reasons' => $this->reasonOptions(),
            'showingArchived' => $showingArchived,
            'activeCount' => $user->prayerIntentions()->whereNull('archived_at')->count(),
            'archivedCount' => $user->prayerIntentions()->whereNotNull('archived_at')->count(),
            'canManageReasons' => Gate::allows('create', PrayerReason::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('prayers/Create', [
            'reasons' => $this->reasonOptions(),
        ]);
    }

    public function store(PrayerIntentionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Vía la relación: el dueño nunca viaja en el formulario.
        $intention = $request->user()->prayerIntentions()->create([
            'name' => $data['name'],
            'custom_reason' => $data['custom_reason'] ?? null,
        ]);

        $intention->reasons()->sync($data['reason_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se agregó a la lista.']);

        return to_route('prayers.index');
    }

    public function edit(PrayerIntention $prayer): Response
    {
        Gate::authorize('update', $prayer);

        return Inertia::render('prayers/Edit', [
            'intention' => [
                'id' => $prayer->id,
                'name' => $prayer->name,
                'custom_reason' => $prayer->custom_reason,
                'reason_ids' => $prayer->reasons()->pluck('prayer_reasons.id')->all(),
            ],
            'reasons' => $this->reasonOptions(),
        ]);
    }

    public function update(PrayerIntentionRequest $request, PrayerIntention $prayer): RedirectResponse
    {
        Gate::authorize('update', $prayer);

        $data = $request->validated();

        $prayer->update([
            'name' => $data['name'],
            'custom_reason' => $data['custom_reason'] ?? null,
        ]);

        $prayer->reasons()->sync($data['reason_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardaron los cambios.']);

        return to_route('prayers.index');
    }

    /**
     * Archiva o devuelve a la lista. Nunca borra: la fecha de archivo es el
     * dato que después arma la línea de tiempo de oraciones.
     */
    public function archive(Request $request, PrayerIntention $prayer): RedirectResponse
    {
        Gate::authorize('update', $prayer);

        $data = $request->validate([
            'archived' => ['required', 'boolean'],
        ], [], ['archived' => 'archivado']);

        $prayer->archived_at = $data['archived'] ? now() : null;
        $prayer->save();

        return back();
    }

    /**
     * Borrado definitivo, para altas equivocadas. Lo normal es archivar: eso
     * conserva la fila y su fecha.
     */
    public function destroy(PrayerIntention $prayer): RedirectResponse
    {
        Gate::authorize('delete', $prayer);

        $prayer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se eliminó de la lista.']);

        return to_route('prayers.index');
    }

    /**
     * El catálogo de motivos, igual para todas las cuentas. Va tanto al
     * formulario (para elegir) como a la lista (para filtrar).
     *
     * @return list<array{id: int, name: string, color: string|null}>
     */
    private function reasonOptions(): array
    {
        $reasons = PrayerReason::orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (PrayerReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'color' => $reason->color?->value,
            ])
            ->all();

        return array_values($reasons);
    }
}
