<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Controllers\Api\V1\Users\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DORY API v1
|--------------------------------------------------------------------------
|
| Every route is prefixed with /api (bootstrap/app.php) and /v1 (below).
| Rate limiters are defined in AppServiceProvider. Authenticated routes use
| Sanctum bearer tokens and reject suspended accounts (`active`).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        Route::middleware('throttle:password-reset')->group(function (): void {
            Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.forgot');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::prefix('users')->name('users.')->middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('locale', [ProfileController::class, 'updateLocale'])->name('locale.update');
    });
});
