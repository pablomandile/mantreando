<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('practice', 'practice/Index')->name('practice.index');
});

require __DIR__.'/settings.php';
