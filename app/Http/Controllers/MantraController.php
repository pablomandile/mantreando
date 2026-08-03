<?php

namespace App\Http\Controllers;

use App\Http\Requests\MantraRequest;
use App\Models\Mantra;
use App\Models\MantraCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MantraController
{
    /**
     * Biblioteca: mantras del sistema + propios, con búsqueda y filtro por
     * categoría. Server-driven (Inertia): la biblioteca requiere conexión;
     * solo la práctica es isla offline.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $category = $request->query('category');

        $prefs = DB::table('mantra_user')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('mantra_id');

        $mantras = Mantra::query()
            ->accessibleBy($user)
            ->with('category')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('text', 'like', "%{$search}%")
                        ->orWhere('transliteration', 'like', "%{$search}%");
                });
            })
            ->when($category, fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $category),
            ))
            ->orderBy('name')
            ->get()
            ->map(fn (Mantra $mantra) => [
                'id' => $mantra->id,
                'name' => $mantra->name,
                'text' => $mantra->text,
                'transliteration' => $mantra->transliteration,
                'image_url' => $mantra->image_url,
                'is_system' => $mantra->isSystem(),
                'category' => [
                    'name' => $mantra->category->localized_name,
                    'slug' => $mantra->category->slug,
                ],
                'is_favorite' => (bool) ($prefs->get($mantra->id)->is_favorite ?? false),
            ]);

        return Inertia::render('mantras/Index', [
            'mantras' => $mantras,
            'categories' => $this->categories(),
            'filters' => ['q' => $search, 'category' => $category],
        ]);
    }

    public function show(Request $request, Mantra $mantra): Response
    {
        Gate::authorize('view', $mantra);

        $user = $request->user();

        $prefs = DB::table('mantra_user')
            ->where('user_id', $user->id)
            ->where('mantra_id', $mantra->id)
            ->first();

        // Progreso histórico del usuario con este mantra (para el objetivo total)
        $totalRecitations = (int) $user->dailyAggregates()
            ->where('mantra_id', $mantra->id)
            ->sum('recitations');

        $mantraStreak = $user->streaks()->where('mantra_id', $mantra->id)->first();

        return Inertia::render('mantras/Show', [
            'mantra' => [
                'id' => $mantra->id,
                'name' => $mantra->name,
                'original_name' => $mantra->original_name,
                'transliteration' => $mantra->transliteration,
                'text' => $mantra->text,
                'translation' => $mantra->translation,
                'description' => $mantra->description,
                'benefits' => $mantra->benefits,
                'image_url' => $mantra->image_url,
                'is_system' => $mantra->isSystem(),
                'category' => $mantra->category->localized_name,
                'can_edit' => Gate::allows('update', $mantra),
            ],
            'prefs' => [
                'is_favorite' => (bool) ($prefs->is_favorite ?? false),
                'daily_commitment' => $prefs->daily_commitment ?? null,
                'total_goal' => $prefs->total_goal ?? null,
            ],
            'progress' => [
                'total_recitations' => $totalRecitations,
                'streak_current' => (int) ($mantraStreak->current_count ?? 0),
                'streak_max' => (int) ($mantraStreak->max_count ?? 0),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('mantras/Create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(MantraRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image'], $data['remove_image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')
                ->store("mantras/{$request->user()->id}", 'public');
        }

        $mantra = $request->user()->ownMantras()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra creado.']);

        return to_route('mantras.show', $mantra);
    }

    public function edit(Mantra $mantra): Response
    {
        Gate::authorize('update', $mantra);

        return Inertia::render('mantras/Edit', [
            'mantra' => $mantra->only([
                'id', 'name', 'original_name', 'transliteration', 'text',
                'translation', 'description', 'benefits', 'category_id',
            ]) + ['image_url' => $mantra->image_url],
            'categories' => $this->categories(),
        ]);
    }

    public function update(MantraRequest $request, Mantra $mantra): RedirectResponse
    {
        Gate::authorize('update', $mantra);

        $data = $request->validated();
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['image'], $data['remove_image']);

        if ($request->hasFile('image')) {
            $this->deleteImage($mantra);
            $data['image_path'] = $request->file('image')
                ->store("mantras/{$request->user()->id}", 'public');
        } elseif ($removeImage) {
            $this->deleteImage($mantra);
            $data['image_path'] = null;
        }

        $mantra->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra actualizado.']);

        return to_route('mantras.show', $mantra);
    }

    public function destroy(Mantra $mantra): RedirectResponse
    {
        Gate::authorize('delete', $mantra);

        // El historial de práctica es sagrado (y la FK lo restringe igual):
        // un mantra con sesiones no se borra.
        if ($mantra->practiceSessions()->exists()) {
            return back()->withErrors([
                'mantra' => 'Este mantra tiene sesiones de práctica registradas y no puede eliminarse.',
            ]);
        }

        $this->deleteImage($mantra);
        $mantra->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mantra eliminado.']);

        return to_route('mantras.index');
    }

    private function categories()
    {
        return MantraCategory::orderBy('position')->get()->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->localized_name,
            'slug' => $category->slug,
        ]);
    }

    private function deleteImage(Mantra $mantra): void
    {
        if ($mantra->image_path !== null) {
            Storage::disk('public')->delete($mantra->image_path);
        }
    }
}
