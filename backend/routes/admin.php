<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * The platform admin dashboard (docs/ADMIN_DASHBOARD.md), loaded by
 * bootstrap/app.php under /admin with the "admin" middleware group: its own
 * session cookie, CSRF protection and security headers.
 *
 * Every page except sign-in needs a signed-in, active platform admin
 * (admin.auth re-checks the account on each request, before any route
 * binding) and passes the platform.admin gate again. The services behind
 * the actions authorize nothing themselves: never add a route here without
 * these middleware (tests/Feature/Admin/AdminRoutesTest.php checks it).
 */
Route::get('login', [AuthController::class, 'show'])->name('login');
Route::post('login', [AuthController::class, 'store'])->name('login.store');

Route::middleware(['admin.auth', 'can:platform.admin', 'throttle:admin'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', OverviewController::class)->name('overview');

    Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('businesses/{adminBusiness}', [BusinessController::class, 'show'])->name('businesses.show');
    Route::post('businesses/{adminBusiness}/approve', [BusinessController::class, 'approve'])->name('businesses.approve');
    Route::post('businesses/{adminBusiness}/suspend', [BusinessController::class, 'suspend'])->name('businesses.suspend');
    Route::post('businesses/{adminBusiness}/reactivate', [BusinessController::class, 'reactivate'])->name('businesses.reactivate');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('businesses/{adminBusiness}/subscription/trial', [SubscriptionController::class, 'extendTrial'])->name('subscriptions.trial');
    Route::post('businesses/{adminBusiness}/subscription/plan', [SubscriptionController::class, 'assignPlan'])->name('subscriptions.plan');
    Route::post('businesses/{adminBusiness}/subscription/end', [SubscriptionController::class, 'end'])->name('subscriptions.end');
    Route::post('businesses/{adminBusiness}/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('businesses/{adminBusiness}/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{adminUser}', [UserController::class, 'show'])->name('users.show');
    Route::post('users/{adminUser}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{adminUser}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('plans/{adminPlan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{adminPlan}', [PlanController::class, 'update'])->name('plans.update');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{adminInvoice}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{adminInvoice}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('payments/{adminInvoice}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

    Route::get('revenue', RevenueController::class)->name('revenue');
    Route::get('audit', AuditController::class)->name('audit');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
