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
                $retreat->update(['is_active' => true]);
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
