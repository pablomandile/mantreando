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
    // La práctica ES el mala: la isla toma todo de IndexedDB (el mantra
    // seleccionado viaja opcionalmente como ?mantra=ID).
    Route::inertia('practice', 'practice/Index')->name('practice.index');
    Route::inertia('practice/spike', 'practice/Spike')->name('practice.spike');

    Route::get('goal', [App\Http\Controllers\SessionGoalController::class, 'edit'])->name('goal.edit');
    Route::patch('goal', [App\Http\Controllers\SessionGoalController::class, 'update'])->name('goal.update');

    Route::post('mantras/reorder', App\Http\Controllers\MantraReorderController::class)->name('mantras.reorder');
    Route::resource('mantras', MantraController::class)->except(['show'])->parameters(['mantras' => 'mantra']);
    Route::get('mantras/{mantra}', [MantraController::class, 'show'])->name('mantras.show')->whereNumber('mantra');
    Route::post('mantras/{mantra}/favorite', MantraFavoriteController::class)->name('mantras.favorite');
    Route::patch('mantras/{mantra}/practice-settings', MantraPracticeSettingsController::class)->name('mantras.practice-settings');

    Route::get('stats', App\Http\Controllers\StatsController::class)->name('stats.index');
});

require __DIR__.'/settings.php';
