<?php

namespace Tests\Feature\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\User;
use App\Services\Queues\HistoricalWaitTimeEstimator;
use App\Services\Queues\WaitEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class WaitTimeEstimationTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dory.queues.wait_history_minimum' => 5, 'dory.queues.wait_history_size' => 20]);
        $this->owner = User::factory()->create();
        $business = $this->businessOwnedBy($this->owner);
        $this->queue = $this->queueFor(
            $this->serviceOf($this->branchOf($business), ['estimated_duration_minutes' => 20]),
            ['estimated_service_duration' => 8],
        );
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function estimator(): HistoricalWaitTimeEstimator
    {
        return new HistoricalWaitTimeEstimator;
    }

    private function completeTickets(int $count, int $serviceSeconds): void
    {
        foreach (range(1, $count) as $i) {
            $start = now()->subHour()->addMinutes($i);
            $this->ticketIn($this->session, TicketStatus::Completed, [
                'started_at' => $start,
                'completed_at' => $start->copy()->addSeconds($serviceSeconds),
            ]);
        }
    }

    public function test_the_default_binding_is_the_historical_estimator(): void
    {
        $this->assertInstanceOf(HistoricalWaitTimeEstimator::class, app(WaitTimeEstimator::class));
    }

    public function test_without_enough_history_the_configured_duration_is_used(): void
    {
        $this->completeTickets(4, 60);

        $estimate = $this->estimator()->estimate($this->queue, 3);

        $this->assertSame(WaitEstimate::BASIS_CONFIGURED, $estimate->basis);
        $this->assertSame(8 * 60, $estimate->secondsPerCustomer);
        $this->assertSame(3 * 8 * 60, $estimate->seconds);
        $this->assertSame(24, $estimate->minutes());
    }

    public function test_the_service_duration_is_the_fallback_when_the_queue_has_none(): void
    {
        $this->queue->update(['estimated_service_duration' => null]);

        $this->assertSame(20 * 60, $this->estimator()->estimate($this->queue->fresh('service'), 1)->seconds);
    }

    public function test_with_enough_history_the_recent_average_is_used(): void
    {
        $this->completeTickets(5, 300);

        $estimate = $this->estimator()->estimate($this->queue, 2);

        $this->assertSame(WaitEstimate::BASIS_HISTORY, $estimate->basis);
        $this->assertSame(300, $estimate->secondsPerCustomer);
        $this->assertSame(600, $estimate->seconds);
    }

    public function test_outliers_are_clamped(): void
    {
        $this->completeTickets(4, 300);
        $this->completeTickets(1, 10 * 3600);

        $estimate = $this->estimator()->estimate($this->queue, 1);

        // (4 × 300 + 4 h) / 5, not (4 × 300 + 10 h) / 5.
        $this->assertSame((int) round((4 * 300 + 4 * 3600) / 5), $estimate->secondsPerCustomer);
    }

    public function test_assigned_staff_serve_in_parallel(): void
    {
        $business = $this->queue->branch->business;
        $this->assignToQueue($this->queue, $this->memberOf($business, BusinessRole::Employee));
        $this->assignToQueue($this->queue, $this->memberOf($business, BusinessRole::Employee));

        $estimate = $this->estimator()->estimate($this->queue, 3);

        $this->assertSame(2, $estimate->servers);
        // ceil(3 / 2) = 2 rounds of 8 minutes.
        $this->assertSame(2 * 8 * 60, $estimate->seconds);
    }

    public function test_nobody_ahead_means_no_wait(): void
    {
        $this->assertSame(0, $this->estimator()->estimate($this->queue, 0)->seconds);
    }
}
