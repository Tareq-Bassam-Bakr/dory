<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\SubscriptionPlan;
use App\Services\Admin\PlatformStatistics;
use App\Services\Billing\PlanCatalog;
use App\Services\Payments\CheckoutService;
use Illuminate\View\View;

/**
 * The dashboard's first page: platform numbers at a glance.
 */
class OverviewController extends AdminController
{
    public function __invoke(PlatformStatistics $statistics, CheckoutService $checkouts, PlanCatalog $catalog): View
    {
        return view('admin.overview', [
            'onlinePayments' => $checkouts->available(),
            'counts' => $statistics->counts(),
            'subscriptions' => $statistics->subscriptions(),
            'revenue' => $statistics->revenue(6),
            'planNames' => self::planNames($catalog),
            'awaiting' => BillingInvoice::query()
                ->with('business')
                ->where('status', InvoiceStatus::Open->value)
                ->where('provider', 'manual')
                ->orderBy('issued_at')
                ->limit(6)
                ->get(),
            'growth' => $statistics->newBusinessesByMonth(6),
        ]);
    }

    /**
     * Plan code => name in the admin's language (with the "all features"
     * plan in force while billing is off).
     *
     * @return array<string, string>
     */
    public static function planNames(PlanCatalog $catalog): array
    {
        $locale = app()->getLocale();
        $names = SubscriptionPlan::query()->orderBy('sort_order')->get()
            ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->code => $plan->name($locale)])
            ->all();

        return $names + ['unlimited' => $catalog->unlimited()->name($locale)];
    }
}
