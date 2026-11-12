<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BillingException;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\Admin\BillingAdministration;
use App\Services\Admin\PlatformStatistics;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\SubscriptionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Subscriptions (docs/ADMIN_DASHBOARD.md, "Billing"): every business's plan
 * as it stands now, and the admin changes of one business's subscription.
 * All changes go through the subscription lifecycle (SubscriptionService)
 * and are recorded with the admin as actor.
 */
class SubscriptionController extends AdminController
{
    public function __construct(
        private readonly BillingAdministration $billing,
        private readonly PlanCatalog $catalog,
    ) {}

    /**
     * States are computed now (the same resolver as the API), so the list
     * is right even before the scheduler stored a change. That reads every
     * matching business; fine for thousands, see docs/ADMIN_DASHBOARD.md.
     */
    public function index(Request $request, SubscriptionResolver $resolver, PlatformStatistics $statistics): View
    {
        $validated = $request->validate([
            'state' => ['nullable', Rule::in(PlatformStatistics::STATES)],
            'plan' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = [
            'state' => (string) ($validated['state'] ?? ''),
            'plan' => (string) ($validated['plan'] ?? ''),
            'q' => trim((string) ($validated['q'] ?? '')),
        ];

        $totals = array_fill_keys(PlatformStatistics::STATES, 0);
        $rows = [];
        $now = now();
        Business::query()
            ->with(['subscription.plan.prices', 'subscription.scheduledPlan.prices'])
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', $this->like($filters['q']))
                ->orWhere('slug', 'like', $this->like($filters['q']))
                ->orWhere('public_id', $filters['q'])))
            ->lazyByIdDesc(500)
            ->each(function (Business $business) use ($resolver, $statistics, $filters, $now, &$totals, &$rows): void {
                $state = $resolver->resolve($business->subscription, $now);
                if ($filters['plan'] !== '' && $state->plan->code !== $filters['plan'] && $state->subscribedPlan->code !== $filters['plan']) {
                    return;
                }
                $key = $statistics->stateKey($state);
                $totals[$key]++;
                if ($filters['state'] === '' || $filters['state'] === $key) {
                    $rows[] = ['business' => $business, 'state' => $state, 'key' => $key];
                }
            });

        $perPage = (int) config('admin.per_page', 25);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.subscriptions.index', [
            'filters' => $filters,
            'totals' => $totals,
            'rows' => $paginator,
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function extendTrial(Request $request, Business $adminBusiness): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'plan' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->perform(function () use ($request, $adminBusiness, $validated): void {
            $plan = null;
            if (($validated['plan'] ?? null) !== null) {
                $plan = $this->catalog->find($validated['plan']);
                if ($plan === null || $plan->is_free) {
                    throw BillingException::planNotAvailable();
                }
            }
            $this->billing->extendTrial($adminBusiness, (int) $validated['days'], $plan, $this->admin($request));
        }, __('admin.subscriptions.trial_extended'));
    }

    public function assignPlan(Request $request, Business $adminBusiness): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:40'],
            // The plan stays until the end of that day (UTC).
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->perform(function () use ($request, $adminBusiness, $validated): void {
            $plan = $this->catalog->find($validated['plan']) ?? throw BillingException::planNotAvailable();
            $until = ($validated['until'] ?? null) === null ? null : Carbon::parse($validated['until'], 'UTC')->endOfDay();
            $this->billing->assignPlan($adminBusiness, $plan, $until, $this->admin($request), $validated['note'] ?? null);
        }, __('admin.subscriptions.plan_assigned'));
    }

    public function end(Request $request, Business $adminBusiness): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:120']]);

        return $this->perform(
            fn () => $this->billing->endSubscription($adminBusiness, $this->admin($request), $validated['reason'] ?? null),
            __('admin.subscriptions.ended'),
        );
    }

    public function cancel(Request $request, Business $adminBusiness): RedirectResponse
    {
        return $this->perform(
            fn () => $this->billing->cancel($adminBusiness, $this->admin($request)),
            __('admin.subscriptions.canceled_done'),
        );
    }

    public function resume(Request $request, Business $adminBusiness): RedirectResponse
    {
        return $this->perform(
            fn () => $this->billing->resume($adminBusiness, $this->admin($request)),
            __('admin.subscriptions.resumed'),
        );
    }
}
