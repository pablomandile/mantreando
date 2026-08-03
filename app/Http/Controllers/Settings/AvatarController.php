<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AvatarController extends Controller
{
    /** Sube (o reemplaza) el avatar del usuario. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['avatar' => ['required', 'image', 'max:2048']],
            [],
            ['avatar' => 'imagen'],
        );

        $user = $request->user();

        $this->deleteLocalAvatar($user->avatar);

        $user->update([
            'avatar' => $request->file('avatar')->store("avatars/{$user->id}", 'public'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avatar actualizado.']);

        return back();
    }

    /** Quita el avatar (local o externo). */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->deleteLocalAvatar($user->avatar);
        $user->update(['avatar' => null]);

        return back();
    }

    private function deleteLocalAvatar(?string $avatar): void
    {
        if ($avatar !== null && ! str_starts_with($avatar, 'http')) {
            Storage::disk('public')->delete($avatar);
        }
    }
}
