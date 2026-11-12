<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Models\BillingInvoice;
use App\Services\Admin\PlatformStatistics;
use App\Services\Billing\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Revenue (docs/ADMIN_DASHBOARD.md, "Revenue"): paid invoices only, split
 * into payments approved by an admin and payments verified with the bank.
 * Payments awaiting approval are shown apart and never counted.
 */
class RevenueController extends AdminController
{
    public function __invoke(Request $request, PlatformStatistics $statistics, PlanCatalog $catalog): View
    {
        $validated = $request->validate(['months' => ['nullable', 'integer', Rule::in([3, 6, 12, 24])]]);
        $months = (int) ($validated['months'] ?? 12);

        $pending = BillingInvoice::query()
            ->where('status', InvoiceStatus::Open->value)
            ->where('provider', 'manual')
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return view('admin.revenue', [
            'months' => $months,
            'revenue' => $statistics->revenue($months),
            'pending' => $pending,
            'planNames' => OverviewController::planNames($catalog),
        ]);
    }
}
