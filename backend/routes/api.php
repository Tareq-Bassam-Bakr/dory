<?php

use App\Http\Controllers\Api\V1\Admin\BillingPlanController;
use App\Http\Controllers\Api\V1\Admin\BillingSubscriptionController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Billing\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Billing\PlanController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Businesses\BranchController;
use App\Http\Controllers\Api\V1\Businesses\BusinessCategoryController;
use App\Http\Controllers\Api\V1\Businesses\BusinessController;
use App\Http\Controllers\Api\V1\Businesses\BusinessInvitationController;
use App\Http\Controllers\Api\V1\Businesses\BusinessMemberController;
use App\Http\Controllers\Api\V1\Businesses\InvitationController;
use App\Http\Controllers\Api\V1\Businesses\ServiceController;
use App\Http\Controllers\Api\V1\Customers\FavoriteController;
use App\Http\Controllers\Api\V1\Discovery\DiscoveryController;
use App\Http\Controllers\Api\V1\Intelligence\BusinessIntelligenceController;
use App\Http\Controllers\Api\V1\Notifications\DeviceController;
use App\Http\Controllers\Api\V1\Notifications\GuestDeviceController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Queues\CustomerTicketController;
use App\Http\Controllers\Api\V1\Queues\PublicQueueController;
use App\Http\Controllers\Api\V1\Queues\QueueController;
use App\Http\Controllers\Api\V1\Queues\QueueDashboardController;
use App\Http\Controllers\Api\V1\Queues\QueueOperationsController;
use App\Http\Controllers\Api\V1\Queues\QueueSessionController;
use App\Http\Controllers\Api\V1\Queues\QueueStaffController;
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

    // Plans for businesses (docs/SUBSCRIPTIONS.md), public like a price page.
    // Customers never need a plan.
    Route::get('billing/plans', [PlanController::class, 'index'])
        ->middleware('throttle:discovery')->name('billing.plans');

    // Payment provider notifications (docs/PAYMENTS.md). No account: the
    // provider authenticates with its shared secret.
    Route::post('billing/webhooks/{provider}', PaymentWebhookController::class)
        ->middleware('throttle:billing-webhooks')->name('billing.webhooks');

    // Public discovery (docs/CUSTOMER_API_REFERENCE.md). No account needed;
    // only public businesses, branches and services are ever returned.
    Route::prefix('discovery')->name('discovery.')->middleware('throttle:discovery')->group(function (): void {
        Route::get('businesses', [DiscoveryController::class, 'businesses'])->name('businesses');
        Route::get('businesses/{publicBusiness}', [DiscoveryController::class, 'business'])->name('businesses.show');
        Route::get('businesses/{publicBusiness}/branches', [DiscoveryController::class, 'businessBranches'])->name('businesses.branches');
        Route::get('nearby', [DiscoveryController::class, 'nearby'])->name('nearby');
        Route::get('queues', [DiscoveryController::class, 'queues'])->name('queues');
        Route::get('cities', [DiscoveryController::class, 'cities'])->name('cities');
        Route::get('branches/{publicBranch}', [DiscoveryController::class, 'branch'])->name('branches.show');
        Route::get('branches/{publicBranch}/services', [DiscoveryController::class, 'branchServices'])->name('branches.services');
    });

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

        // Subscription and billing (docs/SUBSCRIPTIONS.md): owners manage it,
        // managers see the plan and usage.
        Route::get('businesses/{business}/subscription', [SubscriptionController::class, 'show'])
            ->name('businesses.subscription.show');
        Route::get('businesses/{business}/invoices', [SubscriptionController::class, 'invoices'])
            ->name('businesses.invoices');
        Route::middleware('throttle:billing-checkout')->group(function (): void {
            Route::post('businesses/{business}/subscription/checkout', [SubscriptionController::class, 'checkout'])
                ->name('businesses.subscription.checkout');
            Route::get('businesses/{business}/subscription/checkouts/{checkout}', [SubscriptionController::class, 'checkoutStatus'])
                ->name('businesses.subscription.checkout-status');
        });
        Route::post('businesses/{business}/subscription/downgrade', [SubscriptionController::class, 'downgrade'])
            ->name('businesses.subscription.downgrade');
        Route::post('businesses/{business}/subscription/cancel', [SubscriptionController::class, 'cancel'])
            ->name('businesses.subscription.cancel');
        Route::post('businesses/{business}/subscription/resume', [SubscriptionController::class, 'resume'])
            ->name('businesses.subscription.resume');

        // Queue configuration (docs/QUEUE_API_REFERENCE.md)
        Route::get('businesses/{business}/queues', [QueueController::class, 'businessIndex'])->name('queues.business-index');

        // Business intelligence (docs/AI_ARCHITECTURE.md)
        Route::middleware('throttle:insights')->group(function (): void {
            Route::get('businesses/{business}/insights', [BusinessIntelligenceController::class, 'insights'])
                ->name('businesses.insights');
            Route::get('businesses/{business}/peak-times', [BusinessIntelligenceController::class, 'peakTimes'])
                ->name('businesses.peak-times');
        });
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

        // Staff assigned to a queue
        Route::get('queues/{queue}/staff', [QueueStaffController::class, 'index'])->name('queues.staff.index');
        Route::post('queues/{queue}/staff', [QueueStaffController::class, 'store'])->name('queues.staff.store');
        Route::delete('queues/{queue}/staff/{assignment}', [QueueStaffController::class, 'destroy'])->name('queues.staff.destroy');

        // Live session view and serving customers
        Route::get('queue-sessions/{queueSession}/dashboard', [QueueDashboardController::class, 'dashboard'])
            ->name('queue-sessions.dashboard');
        Route::get('queue-sessions/{queueSession}/tickets', [QueueDashboardController::class, 'tickets'])
            ->name('queue-sessions.tickets');
        Route::get('tickets/{ticket}', [QueueOperationsController::class, 'show'])->name('tickets.show');
        Route::middleware('throttle:queue-operations')->group(function (): void {
            Route::post('queue-sessions/{queueSession}/call-next', [QueueOperationsController::class, 'callNext'])
                ->name('queue-sessions.call-next');
            Route::post('tickets/{ticket}/call', [QueueOperationsController::class, 'call'])->name('tickets.call');
            Route::post('tickets/{ticket}/start', [QueueOperationsController::class, 'start'])->name('tickets.start');
            Route::post('tickets/{ticket}/complete', [QueueOperationsController::class, 'complete'])->name('tickets.complete');
            Route::post('tickets/{ticket}/skip', [QueueOperationsController::class, 'skip'])->name('tickets.skip');
            Route::post('tickets/{ticket}/no-show', [QueueOperationsController::class, 'noShow'])->name('tickets.no-show');
            Route::post('tickets/{ticket}/recall', [QueueOperationsController::class, 'recall'])->name('tickets.recall');
        });

        // Customers: joining and following their own tickets
        Route::post('queues/{joinableQueue}/join', [CustomerTicketController::class, 'join'])
            ->middleware('throttle:queue-join')->name('tickets.join');
        Route::get('me/tickets', [CustomerTicketController::class, 'index'])->name('me.tickets');
        Route::get('tickets/{myTicket}/position', [CustomerTicketController::class, 'position'])->name('tickets.position');
        Route::post('tickets/{myTicket}/cancel', [CustomerTicketController::class, 'cancel'])->name('tickets.cancel');
        Route::post('tickets/{myTicket}/review', [CustomerTicketController::class, 'review'])->name('tickets.review');

        // Saved businesses, always the signed-in customer's own.
        Route::get('me/favorites', [FavoriteController::class, 'index'])->name('me.favorites.index');
        Route::post('me/favorites', [FavoriteController::class, 'store'])->name('me.favorites.store');
        Route::delete('me/favorites/{favoriteBusiness}', [FavoriteController::class, 'destroy'])->name('me.favorites.destroy');

        // Notification center and preferences (docs/NOTIFICATION_CENTER.md).
        Route::get('me/notifications', [NotificationController::class, 'index'])->name('me.notifications.index');
        Route::post('me/notifications/read-all', [NotificationController::class, 'readAll'])->name('me.notifications.read-all');
        Route::post('me/notifications/{notification}/read', [NotificationController::class, 'read'])->name('me.notifications.read');
        Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('me.notification-preferences.show');
        Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('me.notification-preferences.update');

        // Push devices (docs/DEVICE_TOKEN_SECURITY.md).
        Route::middleware('throttle:devices')->group(function (): void {
            Route::post('devices/register', [DeviceController::class, 'register'])->name('devices.register');
            Route::delete('devices/{device}/unregister', [DeviceController::class, 'unregister'])->name('devices.unregister');
        });

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
        // The same, for a queue found through discovery (26-character id).
        Route::get('queues/{joinableQueue}', [PublicQueueController::class, 'showById'])->name('queues.by-id.show');
        Route::post('queues/{joinableQueue}/join', [PublicQueueController::class, 'joinById'])
            ->middleware('throttle:queue-join')->name('queues.by-id.join');
        Route::middleware('ticket.token')->group(function (): void {
            Route::get('tickets/{publicTicket}', [PublicQueueController::class, 'ticket'])->name('tickets.show');
            Route::post('tickets/{publicTicket}/cancel', [PublicQueueController::class, 'cancel'])->name('tickets.cancel');
            // Push devices of a guest ticket: the ticket token proves ownership.
            Route::middleware('throttle:devices')->group(function (): void {
                Route::post('tickets/{publicTicket}/devices/register', [GuestDeviceController::class, 'register'])
                    ->name('tickets.devices.register');
                Route::delete('tickets/{publicTicket}/devices/unregister', [GuestDeviceController::class, 'unregister'])
                    ->name('tickets.devices.unregister');
            });
        });
    });

    // Platform administration of plans and subscriptions (docs/SUBSCRIPTIONS.md,
    // "Admin API"). platform.admin runs before any route binding.
    Route::prefix('admin/billing')->name('admin.billing.')
        ->middleware(['auth:sanctum', 'active', 'platform.admin'])
        ->group(function (): void {
            Route::get('plans', [BillingPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [BillingPlanController::class, 'store'])->name('plans.store');
            Route::put('plans/{billingPlan}', [BillingPlanController::class, 'update'])->name('plans.update');
            Route::get('subscriptions', [BillingSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('businesses/{billingBusiness}', [BillingSubscriptionController::class, 'show'])->name('businesses.show');
            Route::post('businesses/{billingBusiness}/payments', [BillingSubscriptionController::class, 'recordPayment'])
                ->name('businesses.payments');
            Route::post('businesses/{billingBusiness}/trial', [BillingSubscriptionController::class, 'extendTrial'])
                ->name('businesses.trial');
            Route::post('businesses/{billingBusiness}/plan', [BillingSubscriptionController::class, 'assignPlan'])
                ->name('businesses.plan');
            Route::post('businesses/{billingBusiness}/expire', [BillingSubscriptionController::class, 'expire'])
                ->name('businesses.expire');
        });

    // Viewing an invitation works signed out, so the app can show what it is about.
    Route::get('invitations/{invitationToken}', [InvitationController::class, 'show'])
        ->middleware('throttle:invitation-token')->name('invitations.show');
});
