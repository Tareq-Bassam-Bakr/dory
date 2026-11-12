<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\BillingInvoiceResource;
use App\Models\BillingInvoice;
use App\Services\Admin\BillingAdministration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Payment records for platform admins (docs/SUBSCRIPTIONS.md, "Admin API"):
 * the list, and approving or rejecting payments recorded as received outside
 * DORY. Only approved payments start a plan and count as revenue.
 */
class BillingPaymentController extends Controller
{
    public function __construct(private readonly BillingAdministration $billing) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['awaiting_approval', 'paid', 'failed', 'void'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $payments = BillingInvoice::query()
            ->with('plan')
            ->when(($validated['status'] ?? null) === 'awaiting_approval', fn ($query) => $query
                ->where('status', InvoiceStatus::Open->value)->where('provider', 'manual'))
            ->when(in_array($validated['status'] ?? null, ['paid', 'failed', 'void'], true), fn ($query) => $query
                ->where('status', $validated['status']))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return ApiResponse::paginated($payments, BillingInvoiceResource::class, __('api.admin.payments'));
    }

    public function approve(Request $request, BillingInvoice $billingInvoice): JsonResponse
    {
        $invoice = $this->billing->approvePayment($billingInvoice, $request->user());

        return ApiResponse::success(new BillingInvoiceResource($invoice->load('plan')), __('api.admin.payment_approved'));
    }

    public function reject(Request $request, BillingInvoice $billingInvoice): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:120']]);
        $invoice = $this->billing->rejectPayment($billingInvoice, $request->user(), $validated['reason']);

        return ApiResponse::success(new BillingInvoiceResource($invoice->load('plan')), __('api.admin.payment_rejected'));
    }
}
