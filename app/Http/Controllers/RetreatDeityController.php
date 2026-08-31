<?php

namespace App\Http\Controllers;

use App\Enums\MantraColor;
use App\Http\Requests\RetreatDeityRequest;
use App\Models\Mantra;
use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Mantenimiento del catálogo de deidades del Retiro de aproximación. Solo
 * administradores: lo que se carga acá aparece en todas las cuentas.
 *
 * La carpeta donde viven las imágenes subidas. No cuelga de la carpeta del
 * admin que las subió: si mañana se borra esa cuenta, la imagen la siguen
 * viendo todos.
 */
class RetreatDeityController
{
    private const UPLOAD_DIR = 'retreats';

    public function index(): Response
    {
        Gate::authorize('create', RetreatDeity::class);

        $deities = RetreatDeity::withCount(['mantras', 'retreats'])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (RetreatDeity $deity): array => [
                'id' => $deity->id,
                'name' => $deity->name,
                'image_url' => $deity->image_url,
                'syllable_image_url' => $deity->syllable_image_url,
                'color' => $deity->color?->value,
                'position' => $deity->position,
                'stages' => $deity->mantras_count ?? 0,
                // En uso = alguien la está recitando, así que no se borra.
                'in_use' => ($deity->retreats_count ?? 0) > 0,
            ])
            ->all();

        return Inertia::render('retreats/deities/Index', [
            'deities' => array_values($deities),
            'nextPosition' => (int) RetreatDeity::max('position') + 1,
        ]);
    }

    public function store(RetreatDeityRequest $request): RedirectResponse
    {
        Gate::authorize('create', RetreatDeity::class);

        $data = $request->validated();
        $position = $data['position'] ?? (int) RetreatDeity::max('position') + 1;

        $deity = RetreatDeity::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'slug' => $this->uniqueSlug($data['name']),
            'position' => $position,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deidad creada. Cargale los mantras.']);

        return to_route('retreats.deities.edit', $deity);
    }

    public function edit(RetreatDeity $deity): Response
    {
        Gate::authorize('update', RetreatDeity::class);

        $deity->load('mantras');

        return Inertia::render('retreats/deities/Edit', [
            'deity' => [
                'id' => $deity->id,
                'name' => $deity->name,
                'color' => $deity->color?->value,
                'position' => $deity->position,
                'image_path' => $deity->image_path,
                'image_url' => $deity->image_url,
                'syllable_image_path' => $deity->syllable_image_path,
                'syllable_image_url' => $deity->syllable_image_url,
            ],
            'stages' => $deity->mantras
                ->map(fn (RetreatMantra $mantra): array => [
                    'id' => $mantra->id,
                    'name' => $mantra->name,
                    'text' => $mantra->text,
                    'goal' => $mantra->goal,
                    'position' => $mantra->position,
                    'in_use' => $mantra->progress()->exists(),
                ])
                ->values()
                ->all(),
            'colors' => MantraColor::options(),
            'gallery' => $this->gallery(),
            'nextStagePosition' => (int) $deity->mantras()->max('position') + 1,
        ]);
    }

    public function update(RetreatDeityRequest $request, RetreatDeity $deity): RedirectResponse
    {
        Gate::authorize('update', RetreatDeity::class);

        $data = $request->validated();

        // El slug NO se regenera al corregir el nombre: es la identidad
        // estable que usa el seeder para no duplicar la fila.
        $attributes = [
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'image_path' => $this->resolveImage($request, 'image', $deity),
            'syllable_image_path' => $this->resolveImage($request, 'syllable_image', $deity),
        ];

        if (($data['position'] ?? null) !== null) {
            $attributes['position'] = $data['position'];
        }

        $deity->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deidad actualizada.']);

        return back();
    }

    public function destroy(RetreatDeity $deity): RedirectResponse
    {
        Gate::authorize('delete', RetreatDeity::class);

        // Borrarla se llevaría el conteo de gente que está en pleno retiro.
        if ($deity->retreats()->exists()) {
            return back()->withErrors([
                'deity' => 'Alguien está haciendo este retiro y la deidad no puede eliminarse.',
            ]);
        }

        if ($deity->mantras()->whereHas('progress')->exists()) {
            return back()->withErrors([
                'deity' => 'Esta deidad tiene conteos registrados y no puede eliminarse.',
            ]);
        }

        $this->deleteUpload($deity->image_path, $deity->id);
        $this->deleteUpload($deity->syllable_image_path, $deity->id);

        // Las etapas caen por la FK en cascada.
        $deity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deidad eliminada.']);

        return to_route('retreats.deities.index');
    }

    /**
     * Resuelve una de las dos imágenes: un archivo nuevo, la ruta de una ya
     * cargada elegida de la grilla, o dejarla como estaba.
     *
     * La ruta elegida se valida contra la grilla: nunca se guarda una ruta
     * arbitraria que venga del formulario.
     */
    private function resolveImage(RetreatDeityRequest $request, string $field, RetreatDeity $deity): ?string
    {
        $current = $field === 'image' ? $deity->image_path : $deity->syllable_image_path;

        if ($request->hasFile($field)) {
            $this->deleteUpload($current, $deity->id);

            $stored = $request->file($field)->store(self::UPLOAD_DIR, 'public');

            // store() devuelve false si el disco falla. Guardar la deidad
            // apuntando a una imagen que nunca se escribió sería peor que
            // cortar acá con un error visible.
            if ($stored === false) {
                throw new RuntimeException("No se pudo guardar la imagen ({$field}).");
            }

            return $stored;
        }

        $chosen = $request->input($field.'_path');

        if ($chosen === null || $chosen === '') {
            return $current;
        }

        $available = array_column($this->gallery(), 'path');

        return in_array($chosen, $available, true) ? $chosen : $current;
    }

    /**
     * Borra una imagen SUBIDA que ya no usa nadie. Nunca toca las que viajan
     * con la app (img/…) ni una que otra deidad esté reusando.
     */
    private function deleteUpload(?string $path, int $exceptDeityId): void
    {
        if ($path === null || str_starts_with($path, 'img/')) {
            return;
        }

        $stillUsed = RetreatDeity::where('id', '!=', $exceptDeityId)
            ->where(function (Builder $query) use ($path): void {
                $query->where('image_path', $path)->orWhere('syllable_image_path', $path);
            })
            ->exists();

        if (! $stillUsed) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Las imágenes ya cargadas, para poder reusar una en vez de subirla otra
     * vez: las de los mantras del sistema (las láminas de las deidades) más
     * las que ya usa alguna deidad del retiro.
     *
     * @return list<array{path: string, url: string}>
     */
    private function gallery(): array
    {
        $paths = Mantra::system()
            ->whereNotNull('image_path')
            ->pluck('image_path')
            ->merge(RetreatDeity::whereNotNull('image_path')->pluck('image_path'))
            ->merge(RetreatDeity::whereNotNull('syllable_image_path')->pluck('syllable_image_path'))
            ->unique()
            ->sort()
            ->values();

        // Se resuelve la URL con el mismo modelo, que ya sabe distinguir una
        // imagen de la app de una subida (ResolvesImagePath).
        $resolver = new RetreatDeity;

        $images = $paths
            ->map(function (string $path) use ($resolver): array {
                $resolver->image_path = $path;

                return ['path' => $path, 'url' => (string) $resolver->image_url];
            })
            ->all();

        return array_values($images);
    }

    /**
     * Slug derivado del nombre, con sufijo si ya existe. Un nombre sin
     * caracteres latinos daría cadena vacía: en ese caso cae en 'deidad'.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'deidad';
        $slug = $base;
        $suffix = 2;

        while (RetreatDeity::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
