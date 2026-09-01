<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MantraController;
use App\Http\Controllers\MantraFavoriteController;
use App\Http\Controllers\MantraPracticeSettingsController;
use App\Http\Controllers\MantraReorderController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PrayerIntentionController;
use App\Http\Controllers\PrayerReasonController;
use App\Http\Controllers\RecitationController;
use App\Http\Controllers\RetreatController;
use App\Http\Controllers\RetreatDeityController;
use App\Http\Controllers\RetreatMantraController;
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

// Texto fijo, sin props: no necesita controlador. Fuera del grupo 'verified'
// porque saber quién hizo la app no depende de confirmar el mail.
Route::inertia('about', 'about/Index')->middleware('auth')->name('about');

Route::middleware(['auth', 'verified'])->group(function () {
    // La práctica ES el mala: la isla toma todo de IndexedDB (el mantra
    // seleccionado viaja opcionalmente como ?mantra=ID). Lo único que manda
    // el servidor es la biblioteca, para que el select no arranque vacío
    // mientras IndexedDB abre.
    Route::get('practice', PracticeController::class)->name('practice.index');
    Route::inertia('practice/spike', 'practice/Spike')->name('practice.spike');

    Route::get('goal', [SessionGoalController::class, 'edit'])->name('goal.edit');
    Route::patch('goal', [SessionGoalController::class, 'update'])->name('goal.update');

    Route::patch('recitations/{recitation}/commitment', [RecitationController::class, 'updateCommitment'])->name('recitations.commitment');
    Route::post('recitations/{recitation}/log', [RecitationController::class, 'log'])->name('recitations.log');
    // La lista y el registro los usa cualquiera; el alta y la edición de los
    // textos las autoriza la policy, que pide rol de administrador. Sin 'show':
    // el texto ya se lee completo desde la lista, plegado.
    Route::resource('recitations', RecitationController::class)
        ->except(['show'])
        ->parameters(['recitations' => 'recitation']);

    // Catálogo de motivos: lo mantiene un administrador y queda para todas
    // las cuentas. Va antes del resource de 'prayers' para que no lo tape.
    Route::get('prayers/reasons', [PrayerReasonController::class, 'index'])->name('prayers.reasons.index');
    Route::post('prayers/reasons', [PrayerReasonController::class, 'store'])->name('prayers.reasons.store');
    Route::patch('prayers/reasons/{reason}', [PrayerReasonController::class, 'update'])->name('prayers.reasons.update');
    Route::delete('prayers/reasons/{reason}', [PrayerReasonController::class, 'destroy'])->name('prayers.reasons.destroy');

    // Archivar no borra: guarda la fecha para la línea de tiempo.
    Route::patch('prayers/{prayer}/archive', [PrayerIntentionController::class, 'archive'])->name('prayers.archive');
    // Sin 'show': la tarjeta de la lista ya muestra todo. Los archivados se
    // piden con ?archived=1, no con ruta propia, para que el item del menú
    // siga resaltado.
    Route::resource('prayers', PrayerIntentionController::class)
        ->except(['show'])
        ->parameters(['prayers' => 'prayer']);

    // Catálogo de deidades y sus etapas: lo mantiene un administrador y queda
    // para todas las cuentas. Va antes de las rutas del retiro para no ser
    // tapado por 'retreats/{retreat}'.
    Route::get('retreats/deities', [RetreatDeityController::class, 'index'])->name('retreats.deities.index');
    Route::post('retreats/deities', [RetreatDeityController::class, 'store'])->name('retreats.deities.store');
    Route::get('retreats/deities/{deity}/edit', [RetreatDeityController::class, 'edit'])->name('retreats.deities.edit');
    // POST y no PUT: el formulario lleva archivos, así que va con _method.
    Route::post('retreats/deities/{deity}', [RetreatDeityController::class, 'update'])->name('retreats.deities.update');
    Route::delete('retreats/deities/{deity}', [RetreatDeityController::class, 'destroy'])->name('retreats.deities.destroy');

    Route::post('retreats/deities/{deity}/mantras', [RetreatMantraController::class, 'store'])->name('retreats.mantras.store');
    Route::patch('retreats/mantras/{mantra}', [RetreatMantraController::class, 'update'])->name('retreats.mantras.update');
    Route::delete('retreats/mantras/{mantra}', [RetreatMantraController::class, 'destroy'])->name('retreats.mantras.destroy');

    // El retiro del usuario: el ábaco y su conteo.
    Route::get('retreats', [RetreatController::class, 'index'])->name('retreats.index');
    Route::post('retreats/activate', [RetreatController::class, 'activate'])->name('retreats.activate');
    Route::patch('retreats/{retreat}', [RetreatController::class, 'update'])->name('retreats.update');
    Route::patch('retreats/{retreat}/count', [RetreatController::class, 'count'])->name('retreats.count');
    Route::patch('retreats/{retreat}/stage', [RetreatController::class, 'completeStage'])->name('retreats.stage');
    Route::post('retreats/{retreat}/reset', [RetreatController::class, 'reset'])->name('retreats.reset');

    Route::post('mantras/reorder', MantraReorderController::class)->name('mantras.reorder');
    // Misma pantalla que la biblioteca, filtrada. Con ruta propia y no
    // ?favorites=1 para que el resaltado del menú funcione: compara pathname,
    // así que por query "Mantras" y "Favoritos" serían el mismo item.
    Route::get('mantras/favorites', [MantraController::class, 'index'])->name('mantras.favorites');
    Route::resource('mantras', MantraController::class)->except(['show'])->parameters(['mantras' => 'mantra']);
    Route::get('mantras/{mantra}', [MantraController::class, 'show'])->name('mantras.show')->whereNumber('mantra');
    Route::post('mantras/{mantra}/favorite', MantraFavoriteController::class)->name('mantras.favorite');
    Route::patch('mantras/{mantra}/practice-settings', MantraPracticeSettingsController::class)->name('mantras.practice-settings');

    Route::get('stats', StatsController::class)->name('stats.index');
});

require __DIR__.'/settings.php';
