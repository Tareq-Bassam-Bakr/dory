<?php

namespace App\Services\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueTicket;

/**
 * Default, deterministic estimator (docs/WAIT_TIME_ESTIMATION.md):
 *
 *   seconds per customer = average service time (started_at → completed_at)
 *                          of the last N completed tickets of the queue, when
 *                          at least M exist (N, M: config dory.queues);
 *                          otherwise the configured duration
 *                          (queue → service → 15 minutes).
 *   servers              = staff assigned to the queue, at least 1.
 *   wait                 = ceil(people ahead / servers) × seconds per customer.
 *
 * Service times are clamped to 30 seconds … 4 hours so one forgotten ticket
 * cannot distort the average.
 */
final class HistoricalWaitTimeEstimator implements WaitTimeEstimator
{
    private const MIN_SAMPLE_SECONDS = 30;

    private const MAX_SAMPLE_SECONDS = 4 * 3600;

    /**
     * Per-request cache: queue id → [seconds per customer, basis, servers].
     *
     * @var array<int, array{0: int, 1: string, 2: int}>
     */
    private array $rates = [];

    public function estimate(Queue $queue, int $peopleAhead): WaitEstimate
    {
        [$perCustomer, $basis, $servers] = $this->rates[$queue->getKey()] ??= $this->rate($queue);
        $rounds = (int) ceil(max(0, $peopleAhead) / $servers);

        return new WaitEstimate($rounds * $perCustomer, $basis, $perCustomer, $servers);
    }

    /**
     * Forget cached rates (after a ticket completes in a long-running process).
     */
    public function flush(): void
    {
        $this->rates = [];
    }

    /**
     * @return array{0: int, 1: string, 2: int}
     */
    private function rate(Queue $queue): array
    {
        $servers = max(1, $queue->activeStaffAssignments()->count());
        $minimum = max(1, (int) config('dory.queues.wait_history_minimum'));
        $size = max($minimum, (int) config('dory.queues.wait_history_size'));

        $recent = QueueTicket::query()
            ->where('queue_id', $queue->getKey())
            ->where('status', TicketStatus::Completed->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($size)
            ->get(['started_at', 'completed_at']);

        if ($recent->count() < $minimum) {
            return [$queue->configuredServiceMinutes() * 60, WaitEstimate::BASIS_CONFIGURED, $servers];
        }

        $average = (int) round($recent->avg(fn (QueueTicket $ticket): int => min(
            self::MAX_SAMPLE_SECONDS,
            max(self::MIN_SAMPLE_SECONDS, (int) $ticket->started_at->diffInSeconds($ticket->completed_at, true)),
        )));

        return [$average, WaitEstimate::BASIS_HISTORY, $servers];
    }
}
