<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\AdminActionException;
use App\Exceptions\BillingException;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionPlan;
use App\Services\Admin\BillingAdministration;
use App\Support\AdminFormat;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Payment records (docs/ADMIN_DASHBOARD.md, "Payments").
 *
 * Payments received outside DORY (bank transfer, cash, mobile wallet) are
 * recorded first and change nothing; they start a plan and count as revenue
 * only once approved. Online payments are verified with the payment
 * provider by the server, never entered by hand. The list shows which is
 * which.
 */
class PaymentController extends AdminController
{
    /** Methods an admin can record; card payments only come from the gateway. */
    private const MANUAL_METHODS = ['bank_transfer', 'cash', 'mobile_wallet', 'other'];

    private const TABS = ['awaiting', 'paid', 'rejected', 'failed', 'all', 'checkouts'];

    public function __construct(private readonly BillingAdministration $billing) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in(['manual', 'gateway', 'admin'])],
            'method' => ['nullable', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::cases()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filters = [
            'tab' => (string) ($validated['tab'] ?? 'awaiting'),
            'q' => trim((string) ($validated['q'] ?? '')),
            'source' => (string) ($validated['source'] ?? ''),
            'method' => (string) ($validated['method'] ?? ''),
            'from' => (string) ($validated['from'] ?? ''),
            'to' => (string) ($validated['to'] ?? ''),
        ];

        $counts = BillingInvoice::query()
            ->selectRaw('status, provider, count(*) as total')
            ->groupBy('status', 'provider')
            ->get();
        $count = fn (callable $match): int => (int) $counts->filter($match)->sum('total');
        $tabs = [
            'awaiting' => $count(fn ($row) => $row->status === InvoiceStatus::Open && $row->provider === 'manual'),
            'paid' => $count(fn ($row) => $row->status === InvoiceStatus::Paid),
            'rejected' => $count(fn ($row) => $row->status === InvoiceStatus::Void),
            'failed' => $count(fn ($row) => $row->status === InvoiceStatus::Failed),
            'all' => (int) $counts->sum('total'),
            'checkouts' => PaymentCheckout::query()->count(),
        ];

        $data = ['filters' => $filters, 'tabs' => $tabs, 'methods' => array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::cases()),
            'fourEyes' => (bool) config('billing.manual_payments.four_eyes')];

        if ($filters['tab'] === 'checkouts') {
            $data['checkouts'] = PaymentCheckout::query()
                ->with(['plan', 'business' => fn ($business) => $business->withTrashed()])
                ->orderByDesc('id')
                ->paginate((int) config('admin.per_page', 25))
                ->withQueryString();

            return view('admin.payments.index', $data);
        }

        $data['payments'] = BillingInvoice::query()
            ->with(['plan', 'business'])
            ->when($filters['tab'] === 'awaiting', fn (Builder $query) => $query->where('status', InvoiceStatus::Open->value)->where('provider', 'manual'))
            ->when($filters['tab'] === 'paid', fn (Builder $query) => $query->where('status', InvoiceStatus::Paid->value))
            ->when($filters['tab'] === 'rejected', fn (Builder $query) => $query->where('status', InvoiceStatus::Void->value))
            ->when($filters['tab'] === 'failed', fn (Builder $query) => $query->where('status', InvoiceStatus::Failed->value))
            ->when($filters['source'] === 'manual', fn (Builder $query) => $query->where('provider', 'manual'))
            ->when($filters['source'] === 'admin', fn (Builder $query) => $query->where('provider', 'admin'))
            ->when($filters['source'] === 'gateway', fn (Builder $query) => $query->whereNotIn('provider', ['manual', 'admin']))
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('payment_method', $filters['method']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('issued_at', '>=', Carbon::parse($filters['from'], 'UTC')->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('issued_at', '<=', Carbon::parse($filters['to'], 'UTC')->endOfDay()))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('number', $filters['q'])
                ->orWhere('public_id', $filters['q'])
                ->orWhere('provider_reference', 'like', $this->like($filters['q']))
                ->orWhereHas('business', fn (Builder $business) => $business
                    ->where('name', 'like', $this->like($filters['q']))
                    ->orWhere('slug', 'like', $this->like($filters['q'])))))
            // Waiting longest first; otherwise newest first.
            ->when($filters['tab'] === 'awaiting', fn (Builder $query) => $query->orderBy('issued_at')->orderBy('id'),
                fn (Builder $query) => $query->orderByDesc('issued_at')->orderByDesc('id'))
            ->paginate((int) config('admin.per_page', 25))
            ->withQueryString();

        return view('admin.payments.index', $data);
    }

    public function create(Request $request): View
    {
        $reference = trim((string) $request->query('business', ''));

        return view('admin.payments.create', [
            'business' => $reference === '' ? null : $this->findBusiness($reference),
            'plans' => SubscriptionPlan::query()->with('prices')->where('is_free', false)->orderBy('sort_order')->orderBy('id')->get(),
            'periods' => BillingPeriod::values(),
            'currencies' => (array) config('billing.currencies'),
            'defaultCurrency' => strtoupper((string) config('billing.currency')),
            'methods' => self::MANUAL_METHODS,
            'fourEyes' => (bool) config('billing.manual_payments.four_eyes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business' => ['required', 'string', 'max:160'],
            'plan' => ['required', 'string', 'max:40'],
            'billing_period' => ['required', Rule::in(BillingPeriod::values())],
            'currency' => ['required', 'string', Rule::in((array) config('billing.currencies'))],
            'amount' => ['nullable', 'string', 'max:20'],
            'method' => ['required', Rule::in(self::MANUAL_METHODS)],
            'reference' => [
                'nullable', 'string', 'max:120',
                Rule::unique('billing_invoices', 'provider_reference')->where('provider', 'manual'),
            ],
            'received_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'business' => __('admin.common.business'),
            'reference' => __('admin.payments.reference'),
            'received_at' => __('admin.payments.received_on'),
        ]);

        $business = $this->findBusiness($validated['business'])
            ?? throw ValidationException::withMessages(['business' => __('admin.payments.business_not_found')]);
        $plan = SubscriptionPlan::query()->with('prices')->where('code', $validated['plan'])->where('is_free', false)->first()
            ?? throw ValidationException::withMessages(['plan' => __('api.billing.plan_not_available')]);
        $currency = strtoupper($validated['currency']);
        $amount = null;
        if (trim((string) ($validated['amount'] ?? '')) !== '') {
            $exponent = Money::exponent($currency);
            $pattern = $exponent === 0 ? '/^\d{1,10}$/' : '/^\d{1,10}(\.\d{1,'.$exponent.'})?$/';
            $amount = preg_match($pattern, trim($validated['amount'])) ? Money::minor(trim($validated['amount']), $currency) : null;
            if ($amount === null || $amount <= 0) {
                throw ValidationException::withMessages(['amount' => trans_choice('admin.plans.price_invalid', $exponent, ['decimals' => $exponent])]);
            }
        }

        try {
            $invoice = $this->billing->recordPayment($business, [
                'plan' => $plan,
                'billing_period' => BillingPeriod::from($validated['billing_period']),
                'currency' => $currency,
                'amount_minor' => $amount,
                'method' => PaymentMethod::from($validated['method']),
                'reference' => $this->nullIfBlank($validated['reference'] ?? null),
                'received_at' => Carbon::parse($validated['received_at'], 'UTC')->startOfDay(),
                'notes' => $this->nullIfBlank($validated['notes'] ?? null),
            ], $this->admin($request));
        } catch (BillingException) {
            throw ValidationException::withMessages(['amount' => __('admin.payments.no_price')]);
        } catch (AdminActionException $refused) {
            return back()->withInput()->with('error', $this->refusal($refused));
        }

        return redirect()->route('admin.payments.show', $invoice)->with('status', __('admin.payments.recorded'));
    }

    public function show(Request $request, BillingInvoice $adminInvoice): View
    {
        $payment = $adminInvoice->load(['plan', 'business', 'recorder', 'reviewer']);

        return view('admin.payments.show', [
            'payment' => $payment,
            'fourEyes' => (bool) config('billing.manual_payments.four_eyes'),
            'isRecorder' => (int) $payment->recorded_by === (int) $this->admin($request)->getKey(),
            'seller' => (string) config('billing.seller_name', 'DORY'),
            'activity' => PlatformAuditLog::query()->with('actor')
                ->where('subject_type', 'billing_invoice')
                ->where('subject_id', $payment->getKey())
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function approve(Request $request, BillingInvoice $adminInvoice): RedirectResponse
    {
        try {
            $invoice = $this->billing->approvePayment($adminInvoice, $this->admin($request));
        } catch (AdminActionException|BillingException $refused) {
            return back()->with('error', $this->refusal($refused));
        }

        return back()->with('status', __('admin.payments.approved', [
            'number' => $invoice->number,
            'date' => AdminFormat::date($invoice->period_end),
        ]));
    }

    public function reject(Request $request, BillingInvoice $adminInvoice): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:120']]);

        return $this->perform(
            fn () => $this->billing->rejectPayment($adminInvoice, $this->admin($request), trim($validated['reason'])),
            __('admin.payments.rejected'),
        );
    }

    /**
     * By id or slug; never an archived business.
     */
    private function findBusiness(string $reference): ?Business
    {
        return Business::query()
            ->where(fn (Builder $match) => $match->where('public_id', $reference)->orWhere('slug', $reference))
            ->first();
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
