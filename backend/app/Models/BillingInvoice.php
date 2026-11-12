<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A billing record: one paid, open or failed charge for one plan period.
 *
 * @property int $id
 * @property string $public_id
 * @property ?string $number
 * @property int $business_id
 * @property ?int $subscription_id
 * @property int $plan_id
 * @property BillingPeriod $billing_period
 * @property string $currency
 * @property int $amount_minor
 * @property InvoiceStatus $status
 * @property ?Carbon $period_start
 * @property ?Carbon $period_end
 * @property Carbon $issued_at
 * @property ?Carbon $paid_at
 * @property ?Carbon $failed_at
 * @property ?string $failure_reason
 * @property string $provider
 * @property ?PaymentMethod $payment_method
 * @property ?string $provider_reference
 * @property ?int $recorded_by
 * @property ?string $notes
 * @property-read SubscriptionPlan $plan
 */
class BillingInvoice extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

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
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount_minor' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The platform admin who recorded a payment received outside DORY.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The platform admin who approved or rejected it.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Recorded by an admin, not yet approved or rejected.
     */
    public function awaitsApproval(): bool
    {
        return $this->status === InvoiceStatus::Open && $this->provider === 'manual';
    }

    /**
     * Confirmed with the payment provider itself (an online payment).
     */
    public function isGatewayVerified(): bool
    {
        return $this->status === InvoiceStatus::Paid && ! in_array($this->provider, ['manual', 'admin'], true);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }
}
