<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Businesses\BranchController;
use App\Http\Controllers\Api\V1\Businesses\BusinessCategoryController;
use App\Http\Controllers\Api\V1\Businesses\BusinessController;
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
| Tenant parameters ({business}, {branch}, {service}, {member},
| {invitation}) are resolved through the user's own memberships by
| TenantResolver (see AppServiceProvider::configureRouteBindings). A
| resource the user may not reach is a 404, exactly like a missing one.
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

    // Public reference data.
    Route::get('business-categories', BusinessCategoryController::class)->name('business-categories.index');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        // Businesses
        Route::post('businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('businesses/manage', [BusinessController::class, 'index'])->name('businesses.manage');
        Route::get('businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::put('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::delete('businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');

        // Branches
        Route::get('businesses/{business}/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('businesses/{business}/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });
});
