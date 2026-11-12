<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessRole;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Admin\BusinessAdministration;
use App\Services\Admin\PlatformStatistics;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Businesses (docs/ADMIN_DASHBOARD.md, "Businesses"): find any business,
 * see its owner, plan, branches and team, and approve, suspend or
 * reactivate it.
 */
class BusinessController extends AdminController
{
    public function __construct(private readonly BusinessAdministration $businesses) {}

    public function index(Request $request, SubscriptionResolver $resolver, PlatformStatistics $statistics): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'archived'])],
            'review' => ['nullable', Rule::in(['approved', 'pending'])],
        ]);
        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => (string) ($validated['status'] ?? ''),
            'review' => (string) ($validated['review'] ?? ''),
        ];

        $businesses = Business::query()
            ->withTrashed()
            ->with([
                'members' => fn ($members) => $members->where('role', BusinessRole::Owner->value)->with('user'),
                'subscription.plan.prices',
                'subscription.scheduledPlan.prices',
            ])
            ->withCount('branches')
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q']))
            ->when($filters['status'] === 'archived', fn (Builder $query) => $query->where(fn (Builder $archived) => $archived
                ->whereNotNull('businesses.deleted_at')->orWhere('businesses.status', 'archived')))
            ->when(in_array($filters['status'], ['active', 'inactive', 'suspended'], true), fn (Builder $query) => $query
                ->whereNull('businesses.deleted_at')->where('businesses.status', $filters['status']))
            ->when($filters['review'] === 'approved', fn (Builder $query) => $query->whereNotNull('approved_at'))
            ->when($filters['review'] === 'pending', fn (Builder $query) => $query->whereNull('approved_at'))
            ->orderByDesc('id')
            ->paginate((int) config('admin.per_page', 25))
            ->withQueryString();

        $locale = app()->getLocale();
        $states = [];
        foreach ($businesses as $business) {
            $state = $resolver->resolve($business->subscription);
            $states[$business->getKey()] = ['plan' => $state->plan->name($locale), 'key' => $statistics->stateKey($state)];
        }

        return view('admin.businesses.index', compact('filters', 'businesses', 'states'));
    }

    public function show(Business $adminBusiness, Entitlements $entitlements, PlatformStatistics $statistics): View
    {
        $business = $adminBusiness->load([
            'category',
            'approvedBy',
            'branches' => fn ($branches) => $branches->withCount('queues')->orderBy('name'),
            'members' => fn ($members) => $members->with('user')->orderBy('id'),
        ]);
        $state = $entitlements->state($business);
        $plans = SubscriptionPlan::query()->with('prices')->orderBy('sort_order')->orderBy('id')->get();
        $events = SubscriptionEvent::query()
            ->where('business_id', $business->getKey())
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(30)
            ->get();
        $locale = app()->getLocale();

        return view('admin.businesses.show', [
            'business' => $business,
            'owner' => $business->members->first(fn ($member) => $member->role === BusinessRole::Owner)?->user,
            'state' => $state,
            'stateKey' => $statistics->stateKey($state),
            'usage' => $entitlements->usage($business),
            'limits' => [
                'branches' => $state->plan->max_branches,
                'queues' => $state->plan->max_queues,
                'members' => $state->plan->max_members,
            ],
            'plans' => $plans,
            'paidPlans' => $plans->where('is_free', false)->values(),
            'invoices' => BillingInvoice::query()->with('plan')
                ->where('business_id', $business->getKey())
                ->orderByDesc('issued_at')->orderByDesc('id')
                ->limit(25)
                ->get(),
            'events' => $events,
            'planLabels' => $plans->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->getKey() => $plan->name($locale)])->all(),
            'actors' => User::query()
                ->whereIn('id', $events->pluck('actor_id')->filter()->unique()->values())
                ->get(['id', 'name'])
                ->keyBy('id'),
            'activity' => PlatformAuditLog::query()->with('actor')
                ->where('business_id', $business->getKey())
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'approvalRequired' => (bool) config('dory.businesses.require_approval'),
        ]);
    }

    public function approve(Request $request, Business $adminBusiness): RedirectResponse
    {
        return $this->perform(
            fn () => $this->businesses->approve($adminBusiness, $this->admin($request)),
            __('admin.businesses.approved'),
        );
    }

    public function suspend(Request $request, Business $adminBusiness): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->perform(
            fn () => $this->businesses->suspend($adminBusiness, $this->admin($request), trim($validated['reason'])),
            __('admin.businesses.suspended'),
        );
    }

    public function reactivate(Request $request, Business $adminBusiness): RedirectResponse
    {
        return $this->perform(
            fn () => $this->businesses->reactivate($adminBusiness, $this->admin($request)),
            __('admin.businesses.reactivated'),
        );
    }

    /**
     * Name, slug, id, or the owner's email or name.
     *
     * @param  Builder<Business>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $like = $this->like($search);
        $query->where(fn (Builder $match) => $match
            ->where('businesses.name', 'like', $like)
            ->orWhere('businesses.slug', 'like', $like)
            ->orWhere('businesses.public_id', $search)
            ->orWhereHas('members', fn (Builder $members) => $members
                ->where('role', BusinessRole::Owner->value)
                ->whereHas('user', fn (Builder $users) => $users
                    ->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like))));
    }
}
