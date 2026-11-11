<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingException;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\SubscriptionState;
use BackedEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Subscription transitions (docs/SUBSCRIPTIONS.md, "Lifecycle"): trial,
 * activation, renewal, upgrade, downgrade, cancellation, grace period and
 * expiry, both as computed on the fly and as stored by the sweep.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function state(?Business $business = null): SubscriptionState
    {
        // A new instance each time: nothing cached between steps.
        return app(Entitlements::class)->state($business ?? $this->business);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    /**
     * @return list<string>
     */
    private function events(): array
    {
        return SubscriptionEvent::query()
            ->where('business_id', $this->business->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionEvent $event): string => $event->type instanceof BackedEnum ? $event->type->value : (string) $event->type)
            ->all();
    }

    private function lastEvent(): SubscriptionEvent
    {
        return SubscriptionEvent::query()->where('business_id', $this->business->getKey())->orderByDesc('id')->firstOrFail();
    }

    /**
     * Past the 14-day trial, without storing anything.
     */
    private function afterTrial(): void
    {
        $this->travel(15)->days();
    }

    // ----- Trial -------------------------------------------------------------

    public function test_a_new_business_starts_with_the_pro_trial(): void
    {
        $state = $this->state();

        $this->assertSame(SubscriptionState::TRIALING, $state->status);
        $this->assertSame('pro', $state->plan->code);
        $this->assertTrue($state->trialEndsAt->equalTo(now()->addDays(14)));
        $this->assertTrue($state->plan->hasFeature(PlanFeature::Insights));
        $this->assertSame(['trial_started'], $this->events());
    }

    public function test_an_owner_gets_one_trial_and_later_businesses_start_free(): void
    {
        $second = $this->businessOwnedBy($this->owner, ['name' => 'Second Clinic']);

        $this->assertSame(SubscriptionState::ACTIVE, $this->state($second)->status);
        $this->assertSame('free', $this->state($second)->plan->code);
        // Another owner still gets one.
        $this->assertSame('pro', $this->state($this->businessOwnedBy(User::factory()->create()))->plan->code);
    }

    public function test_trials_can_be_switched_off(): void
    {
        config(['billing.trial.days' => 0]);

        $this->assertSame('free', $this->state($this->businessOwnedBy(User::factory()->create()))->plan->code);
    }

    public function test_the_trial_ends_on_time_even_before_the_scheduler_runs(): void
    {
        $this->travel(14)->days();
        $this->travel(1)->minutes();

        $state = $this->state();
        $this->assertSame(SubscriptionState::EXPIRED, $state->status);
        $this->assertSame('free', $state->plan->code);
        $this->assertSame('pro', $state->subscribedPlan->code);
        $this->assertSame(SubscriptionStatus::Trialing, $this->subscriptionOf($this->business)->status);

        $this->assertSame(1, $this->service()->sweep());
        $this->assertSame(SubscriptionStatus::Expired, $this->subscriptionOf($this->business)->status);
        $this->assertSame(['trial_started', 'trial_ended'], $this->events());
        // Idempotent.
        $this->assertSame(0, $this->service()->sweep());
        $this->assertSame(['trial_started', 'trial_ended'], $this->events());
    }

    public function test_canceling_a_trial_moves_to_the_free_plan_at_once(): void
    {
        $this->service()->cancel($this->business, $this->owner);

        $this->assertSame(SubscriptionState::ACTIVE, $this->state()->status);
        $this->assertSame('free', $this->state()->plan->code);
        $this->assertSame('canceled', $this->lastEvent()->metadata['reason']);
    }

    public function test_paying_during_the_trial_keeps_the_remaining_trial_days(): void
    {
        $this->travel(4)->days();
        $this->paidPlan($this->business, 'pro');

        $state = $this->state();
        $this->assertSame(SubscriptionState::ACTIVE, $state->status);
        $this->assertTrue($state->periodEnd->equalTo(Carbon::parse('2026-11-25 10:00:00')->addMonthNoOverflow()));
    }

    // ----- Paid periods ----------------------------------------------------------

    public function test_a_payment_activates_a_paid_month_with_a_numbered_invoice(): void
    {
        $this->afterTrial();
        $invoice = $this->paidPlan($this->business, 'pro');

        $state = $this->state();
        $this->assertSame(SubscriptionState::ACTIVE, $state->status);
        $this->assertSame('pro', $state->plan->code);
        $this->assertTrue($state->isPaid());
        $this->assertTrue($state->periodEnd->equalTo(now()->addMonthNoOverflow()));
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame(1500, $invoice->amount_minor);
        $this->assertSame('USD', $invoice->currency);
        $this->assertMatchesRegularExpression('/^DORY-2026-\d{6}$/', $invoice->number);
        $this->assertSame('activated', $this->lastEvent()->type->value);
    }

    public function test_paying_again_renews_from_the_end_of_the_current_period(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $end = $this->state()->periodEnd->copy();

        $this->travel(10)->days();
        $this->paidPlan($this->business, 'pro');

        $this->assertTrue($this->state()->periodEnd->equalTo($end->copy()->addMonthNoOverflow()));
        $this->assertSame(2, BillingInvoice::query()->where('business_id', $this->business->getKey())->count());
        $this->assertSame('renewed', $this->lastEvent()->type->value);
    }

    public function test_a_yearly_payment_buys_a_year(): void
    {
        $this->afterTrial();
        $invoice = $this->paidPlan($this->business, 'pro', BillingPeriod::Yearly);

        $this->assertSame(15000, $invoice->amount_minor);
        $this->assertTrue($this->state()->periodEnd->equalTo(now()->addYearNoOverflow()));
    }

    public function test_upgrading_mid_period_turns_unused_time_into_extra_days(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $this->travel(15)->days();

        $this->paidPlan($this->business, 'business');

        $state = $this->state();
        $this->assertSame('business', $state->plan->code);
        // Half of a 30-day Pro month (7.50 of 15.00) buys 7.50/49.00 of a
        // 31-day Business month: 4.74 days.
        $extra = now()->addMonthNoOverflow()->diffInSeconds($state->periodEnd, true) / 86400;
        $this->assertEqualsWithDelta(4.74, $extra, 0.01);
        $this->assertSame('upgraded', $this->lastEvent()->type->value);
        $this->assertSame(4.7, $this->lastEvent()->metadata['credit_days']);
    }

    public function test_a_smaller_plan_cannot_be_bought_while_a_bigger_one_runs(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'business');

        try {
            $this->paidPlan($this->business, 'pro');
            $this->fail('A smaller plan was bought over a running bigger one.');
        } catch (BillingException $e) {
            $this->assertSame('subscription_change_not_allowed', $e->errorCode());
            $this->assertSame('use_downgrade', $e->details()['reason']);
        }
        $this->assertSame('business', $this->state()->plan->code);
    }

    public function test_a_downgrade_waits_for_the_end_of_the_paid_period(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'business');
        $end = $this->state()->periodEnd->copy();

        $this->service()->downgrade($this->business, $this->plan('pro'), null, $this->owner);
        $this->assertSame('business', $this->state()->plan->code);
        $this->assertSame('pro', $this->state()->scheduledPlan->code);

        $this->travelTo($end->copy()->addHour());
        $this->assertSame(SubscriptionState::PAST_DUE, $this->state()->status);
        $this->assertSame('pro', $this->state()->plan->code);
        $this->service()->sweep();
        $stored = $this->subscriptionOf($this->business);
        $this->assertSame('pro', $stored->plan->code);
        $this->assertSame(1500, $stored->amount_minor);

        $this->paidPlan($this->business, 'pro');
        $this->assertSame(SubscriptionState::ACTIVE, $this->state()->status);
        $this->assertTrue($this->state()->periodEnd->equalTo($end->copy()->addMonthNoOverflow()));
    }

    public function test_canceling_keeps_the_paid_period_and_resuming_undoes_it(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $end = $this->state()->periodEnd->copy();

        $this->service()->cancel($this->business, $this->owner);
        $this->assertTrue($this->state()->cancelAtPeriodEnd);
        $this->assertSame('pro', $this->state()->plan->code);
        $this->service()->resume($this->business, $this->owner);
        $this->assertFalse($this->state()->cancelAtPeriodEnd);
        $this->service()->cancel($this->business, $this->owner);

        // A cancelled plan gets no grace days.
        $this->travelTo($end->copy()->addMinute());
        $this->assertSame(SubscriptionState::EXPIRED, $this->state()->status);
        $this->assertSame('free', $this->state()->plan->code);
        $this->assertSame(1, $this->service()->sweep());
        $this->assertSame('canceled', $this->lastEvent()->metadata['reason']);
        $this->assertSame(['trial_started', 'activated', 'canceled', 'resumed', 'canceled', 'expired'], $this->events());
    }

    public function test_an_unpaid_period_keeps_the_plan_for_the_grace_days_then_ends(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $end = $this->state()->periodEnd->copy();

        $this->travelTo($end->copy()->addDay());
        $state = $this->state();
        $this->assertSame(SubscriptionState::PAST_DUE, $state->status);
        $this->assertSame('pro', $state->plan->code);
        $this->assertTrue($state->graceEndsAt->equalTo($end->copy()->addDays(7)));
        $this->assertSame(1, $this->service()->sweep());
        $this->assertSame(SubscriptionStatus::PastDue, $this->subscriptionOf($this->business)->status);

        $this->travelTo($end->copy()->addDays(7)->addMinute());
        $this->assertSame('free', $this->state()->plan->code);
        $this->assertSame(1, $this->service()->sweep());
        $this->assertSame(SubscriptionStatus::Expired, $this->subscriptionOf($this->business)->status);
        $this->assertSame('unpaid', $this->lastEvent()->metadata['reason']);
    }

    public function test_paying_during_the_grace_days_continues_the_period(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $end = $this->state()->periodEnd->copy();
        $this->travelTo($end->copy()->addDays(3));
        $this->service()->sweep();

        $this->paidPlan($this->business, 'pro');

        $this->assertSame(SubscriptionState::ACTIVE, $this->state()->status);
        $this->assertTrue($this->state()->periodEnd->equalTo($end->copy()->addMonthNoOverflow()));
        $this->assertSame('renewed', $this->lastEvent()->type->value);
    }

    public function test_a_period_that_ends_long_after_the_last_sweep_goes_straight_to_expired(): void
    {
        $this->afterTrial();
        $this->paidPlan($this->business, 'pro');
        $this->travel(3)->months();

        $this->assertSame(1, $this->service()->sweep());
        $this->assertSame(SubscriptionStatus::Expired, $this->subscriptionOf($this->business)->status);
    }

    // ----- Defaults --------------------------------------------------------------

    public function test_a_business_without_a_subscription_record_is_on_the_free_plan(): void
    {
        $legacy = Business::factory()->create();

        $this->assertSame('free', $this->state($legacy)->plan->code);
        $this->paidPlan($legacy, 'pro');
        $this->assertSame('pro', $this->state($legacy)->plan->code);
    }

    public function test_with_billing_switched_off_every_business_has_everything(): void
    {
        config(['billing.enabled' => false]);
        $this->travel(2)->months();

        $state = $this->state();
        $this->assertFalse($state->billingEnabled);
        $this->assertNull($state->plan->limit(PlanLimit::Branches));
        $this->assertTrue($state->plan->hasFeature(PlanFeature::BranchInsights));
    }
}
