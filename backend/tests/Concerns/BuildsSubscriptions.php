<?php

namespace Tests\Concerns;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethod;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;

/**
 * Plans for test businesses (docs/SUBSCRIPTIONS.md).
 */
trait BuildsSubscriptions
{
    protected function plan(string $code): SubscriptionPlan
    {
        return SubscriptionPlan::query()->with('prices')->where('code', $code)->firstOrFail();
    }

    /**
     * The plan given by a platform admin, without payment or end date.
     */
    protected function onPlan(Business $business, string $code): Business
    {
        app(SubscriptionService::class)->assignPlan($business, $this->plan($code), null, $this->billingAdmin());

        return $business;
    }

    /**
     * One paid period of the plan, recorded as a bank transfer.
     */
    protected function paidPlan(Business $business, string $code, BillingPeriod $period = BillingPeriod::Monthly, string $currency = 'USD'): BillingInvoice
    {
        $plan = $this->plan($code);

        return app(SubscriptionService::class)->recordPayment(
            $business,
            $plan,
            $period,
            $currency,
            $plan->priceFor($period, $currency)->amount_minor,
            ['provider' => 'manual', 'method' => PaymentMethod::BankTransfer],
            $this->billingAdmin(),
        );
    }

    protected function subscriptionOf(Business $business): BusinessSubscription
    {
        return BusinessSubscription::query()->with(['plan', 'scheduledPlan'])->where('business_id', $business->getKey())->firstOrFail();
    }

    protected function billingAdmin(): User
    {
        return User::query()->where('is_platform_admin', true)->first()
            ?? User::factory()->platformAdmin()->create();
    }
}
