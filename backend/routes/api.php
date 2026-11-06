<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Businesses\BranchController;
use App\Http\Controllers\Api\V1\Businesses\BusinessCategoryController;
use App\Http\Controllers\Api\V1\Businesses\BusinessController;
use App\Http\Controllers\Api\V1\Businesses\BusinessInvitationController;
use App\Http\Controllers\Api\V1\Businesses\BusinessMemberController;
use App\Http\Controllers\Api\V1\Businesses\InvitationController;
use App\Http\Controllers\Api\V1\Businesses\ServiceController;
use App\Http\Controllers\Api\V1\Queues\CustomerTicketController;
use App\Http\Controllers\Api\V1\Queues\PublicQueueController;
use App\Http\Controllers\Api\V1\Queues\QueueController;
use App\Http\Controllers\Api\V1\Queues\QueueSessionController;
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

        // Services
        Route::get('branches/{branch}/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('branches/{branch}/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        // Team members
        Route::get('businesses/{business}/members', [BusinessMemberController::class, 'index'])->name('members.index');
        Route::put('businesses/{business}/members/{member}', [BusinessMemberController::class, 'update'])->name('members.update');
        Route::delete('businesses/{business}/members/{member}', [BusinessMemberController::class, 'destroy'])->name('members.destroy');

        // Invitations, business side
        Route::get('businesses/{business}/invitations', [BusinessInvitationController::class, 'index'])->name('invitations.index');
        Route::post('businesses/{business}/invitations', [BusinessInvitationController::class, 'store'])
            ->middleware('throttle:invitations')->name('invitations.store');
        Route::delete('businesses/{business}/invitations/{invitation}', [BusinessInvitationController::class, 'destroy'])
            ->name('invitations.destroy');

        // Queue configuration (docs/QUEUE_API_REFERENCE.md)
        Route::get('businesses/{business}/queues', [QueueController::class, 'businessIndex'])->name('queues.business-index');
        Route::get('branches/{branch}/queues', [QueueController::class, 'index'])->name('queues.index');
        Route::post('branches/{branch}/queues', [QueueController::class, 'store'])->name('queues.store');
        Route::get('queues/{queue}', [QueueController::class, 'show'])->name('queues.show');
        Route::put('queues/{queue}', [QueueController::class, 'update'])->name('queues.update');
        Route::delete('queues/{queue}', [QueueController::class, 'destroy'])->name('queues.destroy');
        Route::get('queues/{queue}/public-link', [QueueController::class, 'publicLink'])->name('queues.public-link');
        Route::post('queues/{queue}/rotate-public-token', [QueueController::class, 'rotatePublicToken'])
            ->name('queues.rotate-public-token');

        // Queue sessions
        Route::get('queues/{queue}/current-session', [QueueSessionController::class, 'current'])->name('queues.current-session');
        Route::get('queues/{queue}/sessions', [QueueSessionController::class, 'index'])->name('queues.sessions');
        Route::middleware('throttle:queue-operations')->group(function (): void {
            Route::post('queues/{queue}/open', [QueueSessionController::class, 'open'])->name('queues.open');
            Route::post('queues/{queue}/pause', [QueueSessionController::class, 'pause'])->name('queues.pause');
            Route::post('queues/{queue}/resume', [QueueSessionController::class, 'resume'])->name('queues.resume');
            Route::post('queues/{queue}/close', [QueueSessionController::class, 'close'])->name('queues.close');
        });

        // Customers: joining and following their own tickets
        Route::post('queues/{joinableQueue}/join', [CustomerTicketController::class, 'join'])
            ->middleware('throttle:queue-join')->name('tickets.join');
        Route::get('me/tickets', [CustomerTicketController::class, 'index'])->name('me.tickets');
        Route::get('tickets/{myTicket}/position', [CustomerTicketController::class, 'position'])->name('tickets.position');
        Route::post('tickets/{myTicket}/cancel', [CustomerTicketController::class, 'cancel'])->name('tickets.cancel');

        // Invitations, invitee side (the token is the secret)
        Route::middleware('throttle:invitation-token')->group(function (): void {
            Route::post('invitations/{invitationToken}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
            Route::post('invitations/{invitationToken}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
        });
    });

    // Guests: public queue link / QR code. No account; the queue token is in
    // the link, the ticket token travels only in the X-Ticket-Token header.
    Route::prefix('public')->name('public.')->middleware('throttle:queue-public')->group(function (): void {
        Route::get('queues/{publicQueueToken}', [PublicQueueController::class, 'show'])->name('queues.show');
        Route::post('queues/{publicQueueToken}/join', [PublicQueueController::class, 'join'])
            ->middleware('throttle:queue-join')->name('queues.join');
        Route::middleware('ticket.token')->group(function (): void {
            Route::get('tickets/{publicTicket}', [PublicQueueController::class, 'ticket'])->name('tickets.show');
            Route::post('tickets/{publicTicket}/cancel', [PublicQueueController::class, 'cancel'])->name('tickets.cancel');
        });
    });

    // Viewing an invitation works signed out, so the app can show what it is about.
    Route::get('invitations/{invitationToken}', [InvitationController::class, 'show'])
        ->middleware('throttle:invitation-token')->name('invitations.show');
});
