<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MantraController;
use App\Http\Controllers\MantraFavoriteController;
use App\Http\Controllers\MantraPracticeSettingsController;
use App\Http\Controllers\MantraReorderController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\RecitationController;
use App\Http\Controllers\SessionGoalController;
use App\Http\Controllers\StatsController;
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
    // La práctica ES el mala: la isla toma todo de IndexedDB (el mantra
    // seleccionado viaja opcionalmente como ?mantra=ID). Lo único que manda
    // el servidor es la biblioteca, para que el select no arranque vacío
    // mientras IndexedDB abre.
    Route::get('practice', PracticeController::class)->name('practice.index');
    Route::inertia('practice/spike', 'practice/Spike')->name('practice.spike');

    Route::get('goal', [SessionGoalController::class, 'edit'])->name('goal.edit');
    Route::patch('goal', [SessionGoalController::class, 'update'])->name('goal.update');

    Route::get('recitations', [RecitationController::class, 'index'])->name('recitations.index');

    Route::post('mantras/reorder', MantraReorderController::class)->name('mantras.reorder');
    Route::resource('mantras', MantraController::class)->except(['show'])->parameters(['mantras' => 'mantra']);
    Route::get('mantras/{mantra}', [MantraController::class, 'show'])->name('mantras.show')->whereNumber('mantra');
    Route::post('mantras/{mantra}/favorite', MantraFavoriteController::class)->name('mantras.favorite');
    Route::patch('mantras/{mantra}/practice-settings', MantraPracticeSettingsController::class)->name('mantras.practice-settings');

    Route::get('stats', StatsController::class)->name('stats.index');
});

require __DIR__.'/settings.php';
