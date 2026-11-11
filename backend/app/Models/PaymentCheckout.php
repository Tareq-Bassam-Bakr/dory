<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\CheckoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One online payment attempt (docs/PAYMENTS.md). Its public id is also the
 * order id at the provider, which is how a notification finds it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property int $plan_id
 * @property ?int $invoice_id
 * @property BillingPeriod $billing_period
 * @property string $currency
 * @property int $amount_minor
 * @property string $provider
 * @property CheckoutStatus $status
 * @property ?string $provider_session_id
 * @property ?string $success_indicator_hash
 * @property ?int $created_by
 * @property Carbon $expires_at
 * @property ?Carbon $completed_at
 * @property ?string $failure_reason
 * @property-read Business $business
 * @property-read SubscriptionPlan $plan
 * @property-read ?BillingInvoice $invoice
 */
class PaymentCheckout extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['success_indicator_hash'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'status' => CheckoutStatus::class,
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<BillingInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
