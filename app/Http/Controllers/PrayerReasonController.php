<?php

namespace App\Http\Controllers;

use App\Enums\MantraColor;
use App\Http\Requests\PrayerReasonRequest;
use App\Models\PrayerReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mantenimiento del catálogo de motivos de la Lista de oración. Solo para
 * administradores: lo que se agrega acá aparece en la lista de todas las
 * cuentas, igual que las Otras recitaciones.
 *
 * Una sola pantalla con alta y edición en línea, porque un motivo son tres
 * campos y no justifica páginas aparte.
 */
class PrayerReasonController
{
    public function index(): Response
    {
        Gate::authorize('create', PrayerReason::class);

        $reasons = PrayerReason::withCount('intentions')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (PrayerReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'color' => $reason->color?->value,
                'position' => $reason->position,
                // En uso = está en la lista de alguien, así que no se borra.
                'in_use' => ($reason->intentions_count ?? 0) > 0,
            ])
            ->values()
            ->all();

        return Inertia::render('prayers/Reasons', [
            'reasons' => $reasons,
            'colors' => MantraColor::options(),
            'nextPosition' => (int) PrayerReason::max('position') + 1,
        ]);
    }

    public function store(PrayerReasonRequest $request): RedirectResponse
    {
        Gate::authorize('create', PrayerReason::class);

        $data = $request->validated();
        $position = $data['position'] ?? (int) PrayerReason::max('position') + 1;

        PrayerReason::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'position' => $position,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Motivo creado.']);

        return back();
    }

    public function update(PrayerReasonRequest $request, PrayerReason $reason): RedirectResponse
    {
        Gate::authorize('update', PrayerReason::class);

        $data = $request->validated();

        // El slug NO se regenera al corregir el nombre: es la identidad
        // estable que usa el seeder para no duplicar la fila.
        if (($data['position'] ?? null) === null) {
            unset($data['position']);
        }

        $reason->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Motivo actualizado.']);

        return back();
    }

    public function destroy(PrayerReason $reason): RedirectResponse
    {
        Gate::authorize('delete', PrayerReason::class);

        // Borrarlo dejaría a esas personas sin motivo en listas ajenas, que
        // el administrador ni siquiera ve. Mejor cortar acá con un aviso que
        // chocar contra el restrictOnDelete de la migración.
        if ($reason->intentions()->exists()) {
            return back()->withErrors([
                'reason' => 'Este motivo está en uso en alguna lista y no puede eliminarse.',
            ]);
        }

        $reason->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Motivo eliminado.']);

        return back();
    }

    /**
     * Slug derivado del nombre, con sufijo si ya existe. Un nombre sin
     * caracteres latinos daría cadena vacía: en ese caso cae en 'motivo'.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'motivo';
        $slug = $base;
        $suffix = 2;

        while (PrayerReason::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
