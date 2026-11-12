<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformAuditAction;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The platform audit log (docs/ADMIN_DASHBOARD.md, "Audit log"): sensitive
 * operations, newest first. Read-only: entries are never changed or deleted
 * from the dashboard.
 */
class AuditController extends AdminController
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'action' => ['nullable', Rule::in(PlatformAuditAction::values())],
            'actor' => ['nullable', 'string', 'max:120'],
            'business' => ['nullable', 'string', 'max:26'],
            'user' => ['nullable', 'string', 'max:26'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filters = [
            'action' => (string) ($validated['action'] ?? ''),
            'actor' => trim((string) ($validated['actor'] ?? '')),
            'business' => (string) ($validated['business'] ?? ''),
            'user' => (string) ($validated['user'] ?? ''),
            'from' => (string) ($validated['from'] ?? ''),
            'to' => (string) ($validated['to'] ?? ''),
        ];

        $business = $filters['business'] === '' ? null
            : Business::query()->withTrashed()->where('public_id', $filters['business'])->first();
        $user = $filters['user'] === '' ? null
            : User::query()->where('public_id', $filters['user'])->first();
        $scope = match (true) {
            $business !== null => __('admin.audit.scope_business', ['name' => $business->name]),
            $user !== null => __('admin.audit.scope_user', ['name' => $user->name]),
            default => null,
        };

        $entries = PlatformAuditLog::query()
            ->with(['actor', 'business'])
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['actor'] !== '', fn (Builder $query) => $query->whereHas('actor', fn (Builder $actors) => $actors
                ->where('email', 'like', $this->like(mb_strtolower($filters['actor'])))
                ->orWhere('name', 'like', $this->like($filters['actor']))))
            // An unknown id shows nothing rather than everything.
            ->when($filters['business'] !== '', fn (Builder $query) => $query->where('business_id', $business?->getKey() ?? 0))
            ->when($filters['user'] !== '', fn (Builder $query) => $query->where(fn (Builder $entries) => $entries
                ->where('actor_id', $user?->getKey() ?? 0)
                ->orWhere(fn (Builder $subject) => $subject->where('subject_type', 'user')->where('subject_id', $user?->getKey() ?? 0))))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($filters['from'], 'UTC')->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse($filters['to'], 'UTC')->endOfDay()))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit', [
            'filters' => $filters,
            'entries' => $entries,
            'actions' => PlatformAuditAction::values(),
            'scope' => $scope,
        ]);
    }
}
