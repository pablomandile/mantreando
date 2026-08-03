<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PracticeSettingsController extends Controller
{
    /**
     * Preferencias de práctica (settings JSON del usuario): vibración,
     * sonido y modo por defecto. La isla las recibe vía el bootstrap
     * y las aplica al iniciar una sesión.
     */
    public function edit(Request $request): Response
    {
        $settings = $request->user()->settings ?? [];

        return Inertia::render('settings/Practice', [
            'practice' => [
                'haptics_enabled' => (bool) ($settings['haptics_enabled'] ?? true),
                'sound_enabled' => (bool) ($settings['sound_enabled'] ?? false),
                'default_mode' => $settings['default_mode'] ?? 'traditional',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'haptics_enabled' => ['required', 'boolean'],
            'sound_enabled' => ['required', 'boolean'],
            'default_mode' => ['required', Rule::in(['traditional', 'assisted'])],
        ]);

        $user = $request->user();
        $user->update([
            'settings' => [...($user->settings ?? []), ...$validated],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferencias guardadas.']);

        return back();
    }
}
