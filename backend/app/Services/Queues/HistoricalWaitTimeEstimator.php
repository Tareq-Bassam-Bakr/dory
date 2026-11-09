<?php

namespace App\Services\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueStaffAssignment;
use App\Models\QueueTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Per-request cache: queue id → service profile.
     *
     * @var array<int, ServiceProfile>
     */
    private array $rates = [];

    public function estimate(Queue $queue, int $peopleAhead): WaitEstimate
    {
        $profile = $this->profile($queue);
        $rounds = (int) ceil(max(0, $peopleAhead) / $profile->assignedServers);

        return new WaitEstimate(
            $rounds * $profile->secondsPerCustomer,
            $profile->basis,
            $profile->secondsPerCustomer,
            $profile->assignedServers,
        );
    }

    /**
     * The queue's service profile (cached for the request), for estimators
     * that build on this one (SmartWaitTimeEstimator).
     */
    public function profile(Queue $queue): ServiceProfile
    {
        return $this->rates[$queue->getKey()] ??= $this->rate($queue);
    }

    /**
     * Loads the rates of many queues in two queries: staff counts, and the
     * last N completed tickets of each queue (window function, available on
     * MySQL 8, MariaDB 10.2+ and SQLite 3.25+).
     *
     * @param  iterable<Queue>  $queues
     */
    public function warm(iterable $queues): void
    {
        $missing = collect($queues)
            ->reject(fn (Queue $queue): bool => isset($this->rates[$queue->getKey()]))
            ->keyBy(fn (Queue $queue): int => $queue->getKey());

        if ($missing->isEmpty()) {
            return;
        }

        $ids = $missing->keys()->all();
        $servers = QueueStaffAssignment::query()
            ->whereIn('queue_id', $ids)
            ->whereNull('unassigned_at')
            ->groupBy('queue_id')
            ->selectRaw('queue_id, count(*) as total')
            ->pluck('total', 'queue_id');

        $ranked = QueueTicket::query()
            ->select(['queue_id', 'started_at', 'completed_at'])
            ->selectRaw('row_number() over (partition by queue_id order by completed_at desc, id desc) as recency')
            ->whereIn('queue_id', $ids)
            ->where('status', TicketStatus::Completed->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at');

        $recent = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('recency', '<=', $this->historySize())
            ->get()
            ->groupBy('queue_id');

        foreach ($missing as $id => $queue) {
            $durations = collect($recent->get($id, []))->map(
                fn (object $row): int => (int) Carbon::parse($row->started_at)->diffInSeconds(Carbon::parse($row->completed_at), true),
            );

            $this->rates[$id] = $this->rateFrom($queue, $durations, max(1, (int) ($servers[$id] ?? 0)));
        }
    }

    /**
     * Forget cached rates (after a ticket completes in a long-running process).
     */
    public function flush(): void
    {
        $this->rates = [];
    }

    private function rate(Queue $queue): ServiceProfile
    {
        $servers = max(1, $queue->activeStaffAssignments()->count());

        $recent = QueueTicket::query()
            ->where('queue_id', $queue->getKey())
            ->where('status', TicketStatus::Completed->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($this->historySize())
            ->get(['started_at', 'completed_at']);

        return $this->rateFrom(
            $queue,
            $recent->map(fn (QueueTicket $ticket): int => (int) $ticket->started_at->diffInSeconds($ticket->completed_at, true)),
            $servers,
        );
    }

    /**
     * @param  Collection<int, int>  $durations  Recent service times, in seconds.
     */
    private function rateFrom(Queue $queue, Collection $durations, int $servers): ServiceProfile
    {
        if ($durations->count() < $this->historyMinimum()) {
            return new ServiceProfile(
                $queue->configuredServiceMinutes() * 60,
                WaitEstimate::BASIS_CONFIGURED,
                $servers,
                $durations->count(),
                null,
                null,
            );
        }

        $clamped = $durations
            ->map(fn (int $seconds): int => min(self::MAX_SAMPLE_SECONDS, max(self::MIN_SAMPLE_SECONDS, $seconds)))
            ->sort()
            ->values();

        return new ServiceProfile(
            (int) round($clamped->avg()),
            WaitEstimate::BASIS_HISTORY,
            $servers,
            $clamped->count(),
            self::percentile($clamped, 0.25),
            self::percentile($clamped, 0.75),
        );
    }

    /**
     * Linear interpolation between the closest ranks.
     *
     * @param  Collection<int, int>  $sorted
     */
    private static function percentile(Collection $sorted, float $fraction): int
    {
        $position = ($sorted->count() - 1) * $fraction;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return (int) round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower));
    }

    private function historyMinimum(): int
    {
        return max(1, (int) config('dory.queues.wait_history_minimum'));
    }

    private function historySize(): int
    {
        return max($this->historyMinimum(), (int) config('dory.queues.wait_history_size'));
    }
}
