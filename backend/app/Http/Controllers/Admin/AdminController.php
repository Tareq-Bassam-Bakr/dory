<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AdminActionException;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * Base of the admin dashboard controllers (docs/ADMIN_DASHBOARD.md).
 *
 * The routes already require a signed-in, active platform admin
 * (AuthenticateAdmin and the platform.admin gate). Actions that the current
 * state does not allow come back as a message on the page, in the admin's
 * language, instead of the API's JSON error.
 */
abstract class AdminController extends Controller
{
    protected function admin(Request $request): User
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof User, 403);

        return $admin;
    }

    /**
     * Runs $action, then goes back (or to the URL it returns) with $success,
     * or with the reason the action was refused.
     *
     * @param  callable(): (string|null|mixed)  $action  May return a URL to go to.
     */
    protected function perform(callable $action, string $success): RedirectResponse
    {
        try {
            $result = $action();
        } catch (AdminActionException|BillingException $refused) {
            return back()->withInput()->with('error', $this->refusal($refused));
        }

        return (is_string($result) ? redirect()->to($result) : back())->with('status', $success);
    }

    /**
     * Why an action was refused, in the admin's language (the API's own
     * messages are English only).
     */
    protected function refusal(AdminActionException|BillingException $refused): string
    {
        if ($refused instanceof AdminActionException) {
            $key = 'admin.errors.'.$refused->reason();
        } else {
            $reason = $refused->details()['reason'] ?? null;
            $key = $refused->errorCode() === 'subscription_change_not_allowed' && is_string($reason)
                ? 'admin.errors.change.'.$reason
                : 'admin.errors.'.$refused->errorCode();
        }

        return Lang::has($key) ? __($key) : $refused->getMessage();
    }

    protected function like(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }
}
