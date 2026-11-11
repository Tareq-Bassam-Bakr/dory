<?php

use App\Http\Controllers\Web\BillingCheckoutPageController;
use App\Http\Controllers\Web\GuestQueueController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

/*
 * DORY's backend is an API. The root URL just identifies the service.
 * Load balancers can use /up (framework liveness) or /api/v1/health.
 */
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));

/*
 * Guest web page behind each queue's QR code (docs/GUEST_JOINING.md).
 * The backend keeps no sessions, so Laravel's session CSRF token cannot be
 * used; VerifyGuestFormSubmission, a double-submit nonce and SameSite=Strict
 * cookies protect the forms instead (docs/QUEUE_SECURITY.md).
 */
Route::pattern('guestToken', '[A-Za-z0-9]{40}');
Route::pattern('guestTicket', '[0-9A-Za-z]{26}');

Route::middleware(['guest.locale', 'throttle:queue-public'])
    // Both of Laravel's session-based forgery checks (13 uses
    // PreventRequestForgery in the web group); see the comment above.
    ->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class])
    ->name('guest.')
    ->group(function (): void {
        Route::get('join/{guestToken}', [GuestQueueController::class, 'show'])->name('join');
        Route::post('join/{guestToken}', [GuestQueueController::class, 'join'])
            ->middleware(['guest.form', 'throttle:queue-join'])->name('join.store');
        Route::get('ticket/{guestTicket}', [GuestQueueController::class, 'ticket'])->name('ticket');
        Route::post('ticket/{guestTicket}/cancel', [GuestQueueController::class, 'cancel'])
            ->middleware('guest.form')->name('ticket.cancel');
    });

/*
 * Online payment pages (docs/PAYMENTS.md). "pay" opens only through the
 * signed, expiring link the API gives the owner; "return" is where the
 * payment provider sends the payer back, and only makes the server ask the
 * provider what happened.
 */
Route::middleware(['guest.locale', 'throttle:billing-pages'])
    ->prefix('billing/checkout')
    ->name('billing.checkout.')
    ->group(function (): void {
        Route::get('{checkout}/pay', [BillingCheckoutPageController::class, 'pay'])
            ->middleware(ValidateSignature::relative(['lang']))->name('pay');
        Route::get('{checkout}/return', [BillingCheckoutPageController::class, 'return'])->name('return');
    });
