<?php

namespace App\Http\Controllers\Admin;

use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Admin\UserAdministration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Accounts (docs/ADMIN_DASHBOARD.md, "Users"): find any account, see its
 * memberships, suspend or reactivate it. Never your own account or another
 * admin's (gate platform.manage-user, checked again by the service).
 */
class UserController extends AdminController
{
    public function __construct(private readonly UserAdministration $users) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'deactivated'])],
            'role' => ['nullable', Rule::in(['admin', 'business', 'customer'])],
        ]);
        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => (string) ($validated['status'] ?? ''),
            'role' => (string) ($validated['role'] ?? ''),
        ];

        $users = User::query()
            ->withCount('memberships')
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', $this->like($filters['q']))
                ->orWhere('email', 'like', $this->like(mb_strtolower($filters['q'])))
                ->orWhere('phone', 'like', $this->like($filters['q']))
                ->orWhere('public_id', $filters['q'])))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['role'] === 'admin', fn (Builder $query) => $query->where('is_platform_admin', true))
            ->when($filters['role'] === 'business', fn (Builder $query) => $query->where('is_platform_admin', false)->has('memberships'))
            ->when($filters['role'] === 'customer', fn (Builder $query) => $query->where('is_platform_admin', false)->doesntHave('memberships'))
            ->orderByDesc('id')
            ->paginate((int) config('admin.per_page', 25))
            ->withQueryString();

        return view('admin.users.index', compact('filters', 'users'));
    }

    public function show(Request $request, User $adminUser): View
    {
        $user = $adminUser->load([
            'memberships' => fn ($memberships) => $memberships
                ->with(['business' => fn ($business) => $business->withTrashed()])
                ->orderBy('id'),
        ]);

        return view('admin.users.show', [
            'user' => $user,
            'tokens' => $user->tokens()->count(),
            'canManage' => Gate::forUser($this->admin($request))->allows('platform.manage-user', $user),
            'activity' => PlatformAuditLog::query()->with('actor')
                ->where(fn (Builder $entries) => $entries
                    ->where(fn (Builder $subject) => $subject->where('subject_type', 'user')->where('subject_id', $user->getKey()))
                    ->orWhere('actor_id', $user->getKey()))
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function suspend(Request $request, User $adminUser): RedirectResponse
    {
        $admin = $this->admin($request);
        $allowed = Gate::forUser($admin)->inspect('platform.manage-user', $adminUser);
        if ($allowed->denied()) {
            return back()->with('error', __('admin.errors.'.($allowed->code() ?? 'forbidden')));
        }
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->perform(
            fn () => $this->users->suspend($adminUser, $admin, trim($validated['reason'])),
            __('admin.users.suspended'),
        );
    }

    public function reactivate(Request $request, User $adminUser): RedirectResponse
    {
        $admin = $this->admin($request);
        $allowed = Gate::forUser($admin)->inspect('platform.manage-user', $adminUser);
        if ($allowed->denied()) {
            return back()->with('error', __('admin.errors.'.($allowed->code() ?? 'forbidden')));
        }

        return $this->perform(
            fn () => $this->users->reactivate($adminUser, $admin),
            __('admin.users.reactivated'),
        );
    }
}
