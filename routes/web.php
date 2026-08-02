<?php

use App\Http\Controllers\Auth\GoogleAuthController;
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
    Route::inertia('mantras', 'mantras/Index')->name('mantras.index');
    Route::inertia('stats', 'stats/Index')->name('stats.index');
});

require __DIR__.'/settings.php';
