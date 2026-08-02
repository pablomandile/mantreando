<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController
{
    /**
     * Redirige al consentimiento OAuth de Google.
     */
    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'El acceso con Google no está configurado.']);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Callback de Google: matchea por google_id, linkea por email,
     * o crea una cuenta nueva sin contraseña.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'No se pudo iniciar sesión con Google. Intentá de nuevo.']);
        }

        $email = strtolower((string) $googleUser->getEmail());

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            $user = User::where('email', $email)->first();

            if ($user !== null) {
                // Cuenta existente con este email: se linkea a Google sin tocar el password.
                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                // Cuenta nueva vía Google: sin password. La timezone la captura
                // el cliente en el primer load autenticado (useDeviceTimezone).
                $user = User::create([
                    'name' => $googleUser->getName() ?: $email,
                    'email' => $email,
                    'password' => null,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'locale' => 'es',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } else {
            $user->forceFill(['avatar' => $googleUser->getAvatar()])->save();
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('practice.index'));
    }
}
