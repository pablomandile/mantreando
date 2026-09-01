<?php

namespace App\Http\Controllers;

use App\Models\Retreat;
use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use App\Models\RetreatProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RetreatController
{
    /**
     * Retiro de aproximación: el contador largo, por deidad, con el ábaco de
     * tres líneas.
     *
     * Se practica un retiro por vez (el de activated_at más reciente), pero el
     * avance de los demás se conserva: cambiar de deidad no borra nada.
     *
     * Esta cuenta es independiente de la práctica del mala: no toca
     * practice_sessions ni las rachas, igual que las Otras recitaciones.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $retreat = $user->retreats()
            ->with('deity.mantras')
            ->where('is_active', true)
            ->first();

        return Inertia::render('retreats/Index', [
            'deities' => $this->deityOptions(),
            'retreat' => $retreat === null ? null : $this->retreatPayload($retreat),
            'canManageDeities' => Gate::allows('create', RetreatDeity::class),
        ]);
    }

    /**
     * Elige la deidad del retiro. La primera vez crea la fila con su fecha de
     * inicio; después solo la trae al frente.
     */
    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'retreat_deity_id' => ['required', 'integer', 'exists:retreat_deities,id'],
            // El día lo pone el dispositivo, como el resto de la app.
            'local_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['retreat_deity_id' => 'deidad']);

        $today = $data['local_date'] ?? $this->localDate($request);
        $user = $request->user();

        // Apagar el anterior y prender el nuevo va junto: si se cortara en el
        // medio quedarían dos activos, o ninguno.
        DB::transaction(function () use ($user, $data, $today): void {
            $user->retreats()->where('is_active', true)->update(['is_active' => false]);

            $retreat = $user->retreats()->firstOrCreate(
                ['retreat_deity_id' => $data['retreat_deity_id']],
                ['started_on' => $today, 'is_active' => true],
            );

            if (! $retreat->wasRecentlyCreated) {
                // archived_on también se limpia: si no, un retiro guardado
                // en el historial apareceria ahi Y como el activo a la vez
                // en cuanto el usuario vuelve a elegir esa misma deidad.
                $retreat->update(['is_active' => true, 'archived_on' => null]);
            }
        });

        return back();
    }

    /**
     * Guarda el conteo de una etapa. Llega el valor ABSOLUTO y no un
     * incremento: el cliente cuenta en pantalla y sincroniza cada tanto, así
     * que un reintento (o una respuesta que llega tarde) nunca duplica.
     */
    public function count(Request $request, Retreat $retreat): RedirectResponse
    {
        Gate::authorize('update', $retreat);

        $data = $request->validate([
            'retreat_mantra_id' => ['required', 'integer', 'exists:retreat_mantras,id'],
            'count' => ['required', 'integer', 'min:0', 'max:100000000'],
            'local_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['count' => 'conteo']);

        // Se busca DENTRO de la deidad del retiro: una etapa ajena no aparece.
        $mantra = $this->stageOf($retreat, $data['retreat_mantra_id']);

        if ($mantra === null) {
            return back()->withErrors(['retreat_mantra_id' => 'Esa etapa no es de este retiro.']);
        }

        RetreatProgress::updateOrCreate(
            ['retreat_id' => $retreat->id, 'retreat_mantra_id' => $mantra->id],
            ['count' => $data['count']],
        );

        // first_counted_on/last_counted_on son la práctica en sí, no la
        // elección del selector: started_on se pone al activar la deidad,
        // que puede ser días antes de tocar una sola cuenta.
        $today = $data['local_date'] ?? $this->localDate($request);
        $retreat->update([
            'first_counted_on' => $retreat->first_counted_on ?? ($data['count'] > 0 ? $today : null),
            'last_counted_on' => $today,
        ]);

        return back();
    }

    /**
     * Guarda las notas y/o la dedicación del retiro. Los dos campos son
     * independientes: `sometimes` hace que validate() devuelva solo los que
     * vinieron, así que guardar uno no pisa el otro con lo que tuviera el
     * formulario en ese momento (las notas se autoguardan solas).
     */
    public function update(Request $request, Retreat $retreat): RedirectResponse
    {
        Gate::authorize('update', $retreat);

        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'dedications' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ], [], ['notes' => 'notas', 'dedications' => 'dedicación']);

        $retreat->update($data);

        return back();
    }

    /**
     * Reinicia el conteo del retiro: todas las etapas vuelven a cero y a
     * abiertas, y el retiro deja de estar completo. No borra la fila ni las
     * notas — solo lo contado.
     *
     * El nombre de la deidad se pide también acá, no solo en el diálogo del
     * frente: un POST suelto (o una segunda pestaña) no puede reiniciar un
     * retiro ajeno a lo que el usuario ve en pantalla.
     */
    public function reset(Request $request, Retreat $retreat): RedirectResponse
    {
        Gate::authorize('update', $retreat);

        $data = $request->validate([
            'confirm_name' => ['required', 'string'],
        ], [], ['confirm_name' => 'nombre de la deidad']);

        $retreat->loadMissing('deity');

        if (mb_strtolower(trim($data['confirm_name'])) !== mb_strtolower($retreat->deity->name)) {
            return back()->withErrors(['confirm_name' => 'El nombre no coincide con la deidad del retiro.']);
        }

        DB::transaction(function () use ($retreat): void {
            $retreat->progress()->update(['count' => 0, 'completed_on' => null]);
            $retreat->update([
                'completed_on' => null,
                'first_counted_on' => null,
                'last_counted_on' => null,
            ]);
        });

        return back();
    }

    /**
     * Cierra (o reabre) una etapa. La cierra el usuario y no la cifra: casi
     * siempre se recita de más antes de pasar a la siguiente.
     */
    public function completeStage(Request $request, Retreat $retreat): RedirectResponse
    {
        Gate::authorize('update', $retreat);

        $data = $request->validate([
            'retreat_mantra_id' => ['required', 'integer', 'exists:retreat_mantras,id'],
            'completed' => ['required', 'boolean'],
            'local_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['retreat_mantra_id' => 'etapa']);

        $mantra = $this->stageOf($retreat, $data['retreat_mantra_id']);

        if ($mantra === null) {
            return back()->withErrors(['retreat_mantra_id' => 'Esa etapa no es de este retiro.']);
        }

        $today = $data['local_date'] ?? $this->localDate($request);

        RetreatProgress::updateOrCreate(
            ['retreat_id' => $retreat->id, 'retreat_mantra_id' => $mantra->id],
            ['completed_on' => $data['completed'] ? $today : null],
        );

        // El retiro se completa cuando no queda ninguna etapa abierta.
        $retreat->load('deity.mantras');
        $retreat->update([
            'completed_on' => $retreat->currentStage() === null && $retreat->deity->mantras->isNotEmpty()
                ? $today
                : null,
        ]);

        return back();
    }

    /**
     * Guarda el retiro terminado en el historial. Terminar el conteo (cerrar
     * la última etapa) NO manda nada al historial por sí solo: hasta que el
     * usuario confirma acá, el retiro sigue siendo el activo y la pantalla de
     * felicitaciones vuelve a aparecer cada vez que entra. Guardar lo saca de
     * en medio (is_active en false) para que se pueda elegir otra deidad.
     */
    public function archive(Request $request, Retreat $retreat): RedirectResponse
    {
        Gate::authorize('update', $retreat);

        if ($retreat->completed_on === null) {
            return back()->withErrors(['retreat' => 'Este retiro todavía no está terminado.']);
        }

        $retreat->update([
            'archived_on' => $this->localDate($request),
            'is_active' => false,
        ]);

        return redirect()->route('retreats.history');
    }

    /**
     * El historial: los retiros que el usuario guardó, con todo lo que
     * escribió (notas, dedicaciones) y cómo le fue en cada etapa.
     */
    public function history(Request $request): Response
    {
        $retreats = $request->user()->retreats()
            ->with('deity.mantras')
            ->whereNotNull('archived_on')
            ->orderByDesc('archived_on')
            ->get()
            ->map(fn (Retreat $retreat): array => $this->retreatPayload($retreat))
            ->all();

        return Inertia::render('retreats/History', [
            'retreats' => array_values($retreats),
        ]);
    }

    /**
     * Borra un retiro guardado en el historial. Solo ahí: uno activo o
     * terminado-pero-todavía-no-guardado no se toca por acá, para que este
     * botón no pueda llevarse puesto un conteo en curso.
     */
    public function destroy(Retreat $retreat): RedirectResponse
    {
        Gate::authorize('delete', $retreat);

        if ($retreat->archived_on === null) {
            return back()->withErrors(['retreat' => 'Este retiro no está en el historial.']);
        }

        $retreat->delete();

        return back();
    }

    /**
     * Una etapa de este retiro, buscada dentro de su deidad: si el id viene de
     * otra, no aparece y no hace falta comparar nada a mano.
     */
    private function stageOf(Retreat $retreat, int $mantraId): ?RetreatMantra
    {
        return $retreat->deity->mantras()->where('id', $mantraId)->first();
    }

    /**
     * El catálogo de deidades para el selector.
     *
     * @return list<array{id: int, name: string, image_url: string|null, stages: int}>
     */
    private function deityOptions(): array
    {
        $deities = RetreatDeity::withCount('mantras')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (RetreatDeity $deity): array => [
                'id' => $deity->id,
                'name' => $deity->name,
                'image_url' => $deity->image_url,
                'stages' => $deity->mantras_count ?? 0,
            ])
            ->all();

        return array_values($deities);
    }

    /**
     * El retiro activo con sus etapas y el conteo de cada una.
     *
     * @return array<string, mixed>
     */
    private function retreatPayload(Retreat $retreat): array
    {
        $progress = $retreat->progress()->get()->keyBy('retreat_mantra_id');

        $stages = $retreat->deity->mantras
            ->map(function (RetreatMantra $mantra) use ($progress): array {
                $row = $progress->get($mantra->id);

                return [
                    'id' => $mantra->id,
                    'name' => $mantra->name,
                    'text' => $mantra->text,
                    'goal' => $mantra->goal,
                    'count' => (int) ($row->count ?? 0),
                    'completed_on' => $row?->completed_on?->toDateString(),
                ];
            })
            ->all();

        return [
            'id' => $retreat->id,
            'deity' => [
                'id' => $retreat->deity->id,
                'name' => $retreat->deity->name,
                'image_url' => $retreat->deity->image_url,
                'syllable_image_url' => $retreat->deity->syllable_image_url,
                'color' => $retreat->deity->color?->value,
            ],
            'started_on' => $retreat->started_on->toDateString(),
            'completed_on' => $retreat->completed_on?->toDateString(),
            'first_counted_on' => $retreat->first_counted_on?->toDateString(),
            'last_counted_on' => $retreat->last_counted_on?->toDateString(),
            'archived_on' => $retreat->archived_on?->toDateString(),
            'notes' => $retreat->notes,
            'dedications' => $retreat->dedications,
            'stages' => array_values($stages),
            'current_stage_id' => $retreat->currentStage()?->id,
        ];
    }

    /**
     * El día del dispositivo, con la timezone del perfil como respaldo. Misma
     * regla que la práctica (§7): el servidor no inventa fechas.
     */
    private function localDate(Request $request): string
    {
        try {
            return now($request->user()->timezone ?: config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
