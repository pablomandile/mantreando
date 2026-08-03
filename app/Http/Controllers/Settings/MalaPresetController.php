<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MalaPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MalaPresetController extends Controller
{
    /** Página de personalización del mala. */
    public function edit(Request $request): Response
    {
        $preset = $this->activePreset($request);

        return Inertia::render('settings/Mala', [
            'preset' => [
                'material' => $preset?->material ?? 'wood',
                'texture_url' => $preset?->texture_url,
            ],
            'materials' => MalaPreset::MATERIALS,
        ]);
    }

    /** Cambia material y/o textura del preset activo (se crea si no existe). */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'material' => ['required', Rule::in(MalaPreset::MATERIALS)],
            'texture' => ['nullable', 'image', 'max:2048'],
            'remove_texture' => ['nullable', 'boolean'],
        ], [], ['material' => 'material', 'texture' => 'textura']);

        $user = $request->user();
        $preset = $this->activePreset($request)
            ?? new MalaPreset(['user_id' => $user->id, 'is_active' => true]);

        $preset->material = $validated['material'];

        if ($request->hasFile('texture')) {
            $this->deleteTexture($preset);
            $preset->texture_path = $request->file('texture')
                ->store("malas/{$user->id}/textures", 'public');
        } elseif ($validated['remove_texture'] ?? false) {
            $this->deleteTexture($preset);
            $preset->texture_path = null;
        }

        $preset->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mala actualizado.']);

        return back();
    }

    private function activePreset(Request $request): ?MalaPreset
    {
        return MalaPreset::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();
    }

    private function deleteTexture(MalaPreset $preset): void
    {
        if ($preset->texture_path !== null) {
            Storage::disk('public')->delete($preset->texture_path);
        }
    }
}
