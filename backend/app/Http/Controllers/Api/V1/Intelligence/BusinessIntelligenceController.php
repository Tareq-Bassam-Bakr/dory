<?php

namespace App\Http\Controllers\Api\V1\Intelligence;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Services\Billing\Entitlements;
use App\Services\Business\CurrentBusinessContext;
use App\Services\Intelligence\ActivityHistory;
use App\Services\Intelligence\BusinessInsights;
use App\Services\Intelligence\PeakTimeForecaster;
use App\Services\Notifications\NotificationTexts;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Business intelligence for owners and managers (docs/AI_ARCHITECTURE.md).
 * Only the business's own records, only the branches the reader reaches;
 * ?branch= narrows to one of them.
 *
 * Plans (docs/SUBSCRIPTIONS.md): insights need the "insights" feature, one
 * branch on its own needs "branch_insights".
 */
class BusinessIntelligenceController extends Controller
{
    public function __construct(
        private readonly CurrentBusinessContext $context,
        private readonly ActivityHistory $history,
        private readonly Entitlements $entitlements,
    ) {}

    public function insights(Request $request, Business $business, BusinessInsights $insights): JsonResponse
    {
        $branches = $this->scope($request, $business);
        $days = 2 * max(1, (int) config('dory.intelligence.insight_days'));
        $locale = NotificationTexts::requestLocale(
            $request->header('Accept-Language'),
            (string) ($request->user()->locale ?? 'en'),
        );

        return ApiResponse::success(
            $insights->analyse($this->history->tickets($branches, now()->subDays($days)), $locale)
                + ['branches' => $this->branchIds($branches)],
            __('api.intelligence.insights'),
        );
    }

    public function peakTimes(Request $request, Business $business, PeakTimeForecaster $forecaster): JsonResponse
    {
        $branches = $this->scope($request, $business);
        $days = max(1, (int) config('dory.intelligence.history_days'));
        $timezone = $branches->first()?->timezone ?: 'UTC';

        return ApiResponse::success(
            $forecaster->forecast($this->history->tickets($branches, now()->subDays($days)), $timezone)
                + ['branches' => $this->branchIds($branches)],
            __('api.intelligence.peak_times'),
        );
    }

    /**
     * @return Collection<int, Branch>
     */
    private function scope(Request $request, Business $business): Collection
    {
        Gate::authorize('viewInsights', $business);
        $this->entitlements->ensureFeature($business, PlanFeature::Insights);
        $branch = $request->query('branch');
        $branch = is_string($branch) && $branch !== '' ? $branch : null;
        if ($branch !== null) {
            $this->entitlements->ensureFeature($business, PlanFeature::BranchInsights);
        }

        return $this->history->branches($business, $this->context->membership(), $branch);
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return list<string>
     */
    private function branchIds(Collection $branches): array
    {
        return $branches->pluck('public_id')->values()->all();
    }
}
