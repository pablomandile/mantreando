<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MantraController;
use App\Http\Controllers\MantraFavoriteController;
use App\Http\Controllers\MantraPracticeSettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('practice.index')
        : Inertia::render('Welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('practice', 'practice/Index')->name('practice.index');
    Route::inertia('practice/spike', 'practice/Spike')->name('practice.spike');

    // La pantalla de práctica solo recibe el ID: el contenido del mantra
    // sale de IndexedDB (isla offline).
    Route::get('practice/session/{mantra}', function (App\Models\Mantra $mantra) {
        Illuminate\Support\Facades\Gate::authorize('view', $mantra);

        return Inertia::render('practice/Session', ['mantraId' => $mantra->id]);
    })->name('practice.session')->whereNumber('mantra');

    Route::resource('mantras', MantraController::class)->except(['show'])->parameters(['mantras' => 'mantra']);
    Route::get('mantras/{mantra}', [MantraController::class, 'show'])->name('mantras.show')->whereNumber('mantra');
    Route::post('mantras/{mantra}/favorite', MantraFavoriteController::class)->name('mantras.favorite');
    Route::patch('mantras/{mantra}/practice-settings', MantraPracticeSettingsController::class)->name('mantras.practice-settings');

    Route::inertia('stats', 'stats/Index')->name('stats.index');
});

require __DIR__.'/settings.php';
