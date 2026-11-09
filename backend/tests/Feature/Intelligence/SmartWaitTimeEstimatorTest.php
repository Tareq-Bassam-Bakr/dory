<?php

namespace Tests\Feature\Intelligence;

use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\User;
use App\Services\Intelligence\SmartWaitTimeEstimator;
use App\Services\Queues\HistoricalWaitTimeEstimator;
use App\Services\Queues\WaitEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Wait-time prediction from history, the customers at the counter and the
 * staff actually serving (docs/WAIT_TIME_ESTIMATION.md).
 */
class SmartWaitTimeEstimatorTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dory.queues.wait_history_minimum' => 5, 'dory.queues.wait_history_size' => 40]);
        $this->owner = User::factory()->create();
        $business = $this->businessOwnedBy($this->owner);
        $this->queue = $this->queueFor($this->serviceOf($this->branchOf($business)), ['estimated_service_duration' => 8]);
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function estimator(): SmartWaitTimeEstimator
    {
        return new SmartWaitTimeEstimator(new HistoricalWaitTimeEstimator);
    }

    /**
     * Completed tickets served long ago (not "active" staff).
     *
     * @param  list<int>  $serviceSeconds
     */
    private function history(array $serviceSeconds): void
    {
        foreach ($serviceSeconds as $i => $seconds) {
            $start = now()->subDays(2)->addMinutes($i * 20);
            $this->ticketIn($this->session, TicketStatus::Completed, [
                'called_at' => $start, 'started_at' => $start, 'completed_at' => $start->copy()->addSeconds($seconds),
            ]);
        }
    }

    public function test_without_live_activity_it_matches_the_historical_estimate(): void
    {
        $smart = $this->estimator()->estimate($this->queue, 3);
        $historical = (new HistoricalWaitTimeEstimator)->estimate($this->queue, 3);

        $this->assertSame($historical->seconds, $smart->seconds);
        $this->assertSame(3 * 8 * 60, $smart->seconds);
        $this->assertSame(WaitEstimate::CONFIDENCE_LOW, $smart->confidence);
        $this->assertNull($smart->toArray()['range'], 'No range without history.');
    }

    public function test_the_customer_at_the_counter_keeps_the_server_busy(): void
    {
        $this->ticketIn($this->session, TicketStatus::Serving, [
            'served_by' => $this->owner->getKey(),
            'called_at' => now()->subMinutes(6),
            'started_at' => now()->subMinutes(5),
        ]);

        // 8-minute service, 5 done: free in 3 minutes, then one customer ahead.
        $this->assertSame(3 * 60, $this->estimator()->estimate($this->queue, 0)->seconds);
        $this->assertSame((3 + 8) * 60, $this->estimator()->estimate($this->queue, 1)->seconds);
    }

    public function test_a_called_customer_who_has_not_started_takes_a_whole_service(): void
    {
        $this->ticketIn($this->session, TicketStatus::Called, [
            'served_by' => $this->owner->getKey(),
            'called_at' => now()->subMinute(),
        ]);

        $this->assertSame(8 * 60, $this->estimator()->estimate($this->queue, 0)->seconds);
    }

    public function test_staff_serving_now_count_even_when_not_assigned(): void
    {
        $manager = $this->memberOf($this->queue->branch->business, BusinessRole::Manager);
        foreach ([$this->owner, $manager] as $i => $staff) {
            $this->ticketIn($this->session, TicketStatus::Completed, [
                'served_by' => $staff->getKey(),
                'called_at' => now()->subMinutes(20 + $i),
                'started_at' => now()->subMinutes(19 + $i),
                'completed_at' => now()->subMinutes(12 + $i),
            ]);
        }

        $estimate = $this->estimator()->estimate($this->queue, 4);

        $this->assertSame(2, $estimate->servers);
        $this->assertSame(2 * 8 * 60, $estimate->seconds);
    }

    public function test_activity_older_than_the_window_does_not_count(): void
    {
        config(['dory.intelligence.active_staff_minutes' => 45]);
        $manager = $this->memberOf($this->queue->branch->business, BusinessRole::Manager);
        $this->ticketIn($this->session, TicketStatus::Completed, [
            'served_by' => $manager->getKey(),
            'called_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2)->addMinutes(8),
        ]);

        // Nobody active: the assigned staff (none, so 1) serve.
        $this->assertSame(1, $this->estimator()->estimate($this->queue, 2)->servers);
    }

    public function test_history_gives_a_likely_range_and_a_confidence(): void
    {
        $this->history([240, 300, 360, 420, 480]);

        $estimate = $this->estimator()->estimate($this->queue, 2);

        // Average 6 minutes; 25th and 75th percentile 5 and 7 minutes.
        $this->assertSame(WaitEstimate::BASIS_HISTORY, $estimate->basis);
        $this->assertSame(2 * 360, $estimate->seconds);
        $this->assertSame(2 * 300, $estimate->lowSeconds);
        $this->assertSame(2 * 420, $estimate->highSeconds);
        $this->assertSame(WaitEstimate::CONFIDENCE_MEDIUM, $estimate->confidence);
        $this->assertSame(['low_minutes' => 10, 'high_minutes' => 14], $estimate->toArray()['range']);
    }

    public function test_plenty_of_history_gives_high_confidence(): void
    {
        config(['dory.intelligence.high_confidence_samples' => 30]);
        $this->history(array_fill(0, 30, 300));

        $this->assertSame(WaitEstimate::CONFIDENCE_HIGH, $this->estimator()->estimate($this->queue, 1)->confidence);
    }

    public function test_a_full_history_window_gives_high_confidence(): void
    {
        // Found with real data: the default window holds 20 tickets, fewer
        // than high_confidence_samples, so "high" was unreachable.
        config(['dory.queues.wait_history_size' => 20, 'dory.intelligence.high_confidence_samples' => 30]);
        $this->history(array_fill(0, 20, 300));

        $this->assertSame(WaitEstimate::CONFIDENCE_HIGH, $this->estimator()->estimate($this->queue, 1)->confidence);
    }

    public function test_warming_gives_the_same_estimates_with_no_further_queries(): void
    {
        $this->history([240, 300, 360, 420, 480]);
        $this->ticketIn($this->session, TicketStatus::Serving, [
            'served_by' => $this->owner->getKey(),
            'called_at' => now()->subMinutes(3),
            'started_at' => now()->subMinutes(2),
        ]);
        $other = $this->queueFor($this->serviceOf($this->queue->branch), ['estimated_service_duration' => 5]);
        $queues = collect([$this->queue, $other]);

        $warmed = $this->estimator();
        $warmed->warm($queues);
        DB::enableQueryLog();
        $estimates = $queues->map(fn (Queue $queue): WaitEstimate => $warmed->estimate($queue, 3));
        $this->assertCount(0, DB::getQueryLog());

        foreach ($queues as $i => $queue) {
            $this->assertEquals($this->estimator()->estimate($queue, 3), $estimates[$i]);
        }
    }

    public function test_customers_see_the_range_and_confidence(): void
    {
        $this->history([240, 300, 360, 420, 480]);
        $this->ticketIn($this->session, TicketStatus::Waiting);

        $response = $this->getJson('/api/v1/discovery/queues');

        $estimate = collect($response->json('data'))->firstWhere('id', $this->queue->public_id)['estimated_wait'];
        $this->assertSame('medium', $estimate['confidence']);
        $this->assertSame(['low_minutes' => 5, 'high_minutes' => 7], $estimate['range']);
    }
}
