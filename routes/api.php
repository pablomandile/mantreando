<?php

use App\Http\Controllers\Api\V1\PracticeBootstrapController;
use App\Http\Controllers\Api\V1\PracticeSessionSyncController;
use App\Http\Controllers\Api\V1\ResetTodayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::get('practice/bootstrap', PracticeBootstrapController::class)->name('practice.bootstrap');
    Route::post('practice-sessions', PracticeSessionSyncController::class)->name('practice-sessions.store');
    Route::delete('practice/today', ResetTodayController::class)->name('practice.today.destroy');
});
