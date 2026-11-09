<?php

namespace App\Services\Intelligence;

use App\Contracts\WaitTimeEstimator;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Services\Queues\HistoricalWaitTimeEstimator;
use App\Services\Queues\ServiceProfile;
use App\Services\Queues\WaitEstimate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The default estimator since Phase 07 (docs/AI_ARCHITECTURE.md,
 * docs/WAIT_TIME_ESTIMATION.md). It builds on the historical service profile
 * and adds what is happening now:
 *
 * - Active staff: staff who called or served a ticket of the open session in
 *   the last N minutes (dory.intelligence.active_staff_minutes). Without such
 *   activity, the staff assigned to the queue (at least 1).
 * - Current workload: customers already at the counter keep their server busy
 *   for the rest of a typical service time.
 * - The wait is simulated: every server becomes free at some moment, and each
 *   customer ahead takes the first free server. Run with the typical, the
 *   quicker (25th percentile) and the slower (75th) service time, it gives
 *   the estimate and its likely range.
 *
 * Deterministic for the same data and moment; a fixed number of queries per
 * queue (two with warm() for any number of queues), so it stays cheap enough
 * for every position request.
 */
final class SmartWaitTimeEstimator implements WaitTimeEstimator
{
    /**
     * Per-request cache: queue id → live state.
     *
     * @var array<int, array{staff: int, busy: list<?Carbon>}>
     */
    private array $live = [];

    public function __construct(private readonly HistoricalWaitTimeEstimator $history) {}

    public function estimate(Queue $queue, int $peopleAhead): WaitEstimate
    {
        $profile = $this->history->profile($queue);
        $live = $this->live[$queue->getKey()] ??= $this->loadLive(collect([$queue]))[$queue->getKey()];

        $busy = $live['busy'];
        $servers = max($live['staff'] > 0 ? $live['staff'] : $profile->assignedServers, count($busy), 1);
        $ahead = max(0, $peopleAhead);
        $now = now();

        $seconds = $this->simulate($servers, $busy, $ahead, $profile->secondsPerCustomer, $now);
        $low = $profile->lowSeconds === null ? null : $this->simulate($servers, $busy, $ahead, $profile->lowSeconds, $now);
        $high = $profile->highSeconds === null ? null : $this->simulate($servers, $busy, $ahead, $profile->highSeconds, $now);

        return new WaitEstimate(
            $seconds,
            $profile->basis,
            $profile->secondsPerCustomer,
            $servers,
            $low === null ? null : min($low, $seconds),
            $high === null ? null : max($high, $seconds),
            $this->confidence($profile),
        );
    }

    public function warm(iterable $queues): void
    {
        $queues = collect($queues);
        $this->history->warm($queues);
        $missing = $queues->reject(fn (Queue $queue): bool => isset($this->live[$queue->getKey()]));
        if ($missing->isNotEmpty()) {
            $this->live = $this->loadLive($missing) + $this->live;
        }
    }

    /**
     * When the first server becomes free for the customer, in seconds.
     *
     * @param  list<?Carbon>  $busy
     */
    private function simulate(int $servers, array $busy, int $ahead, int $perCustomer, Carbon $now): int
    {
        $free = array_fill(0, $servers, 0);
        foreach (array_values($busy) as $i => $startedAt) {
            if ($i >= $servers) {
                break;
            }
            // Called but not started: the whole service is still ahead.
            $elapsed = $startedAt === null ? 0 : (int) $startedAt->diffInSeconds($now, true);
            $free[$i] = max(0, $perCustomer - $elapsed);
        }

        for ($i = 0; $i < $ahead; $i++) {
            sort($free);
            $free[0] += $perCustomer;
        }

        return (int) min($free);
    }

    private function confidence(ServiceProfile $profile): string
    {
        if (! $profile->fromHistory()) {
            return WaitEstimate::CONFIDENCE_LOW;
        }

        // The profile holds at most wait_history_size tickets: a full window
        // counts as plenty even when that is below high_confidence_samples.
        $plenty = min(
            (int) config('dory.intelligence.high_confidence_samples'),
            max(1, (int) config('dory.queues.wait_history_size')),
        );

        return $profile->samples >= $plenty ? WaitEstimate::CONFIDENCE_HIGH : WaitEstimate::CONFIDENCE_MEDIUM;
    }

    /**
     * One query: tickets of the queues' open sessions that are at the counter,
     * or were called recently (to know who is working).
     *
     * @param  Collection<int, Queue>  $queues
     * @return array<int, array{staff: int, busy: list<?Carbon>}>
     */
    private function loadLive(Collection $queues): array
    {
        $ids = $queues->map(fn (Queue $queue): int => $queue->getKey())->all();
        $since = now()->subMinutes(max(1, (int) config('dory.intelligence.active_staff_minutes')));
        $inService = [TicketStatus::Called->value, TicketStatus::Serving->value];

        $rows = QueueTicket::query()
            ->join('queue_sessions', 'queue_sessions.id', '=', 'queue_tickets.queue_session_id')
            ->whereNotNull('queue_sessions.active_key')
            ->whereIn('queue_tickets.queue_id', $ids)
            ->where(fn ($query) => $query
                ->whereIn('queue_tickets.status', $inService)
                ->orWhere('queue_tickets.called_at', '>=', $since))
            ->orderBy('queue_tickets.id')
            ->get([
                'queue_tickets.queue_id',
                'queue_tickets.status',
                'queue_tickets.served_by',
                'queue_tickets.started_at',
            ])
            ->groupBy('queue_id');

        $live = [];
        foreach ($ids as $id) {
            /** @var Collection<int, QueueTicket> $tickets */
            $tickets = $rows->get($id, collect());
            $live[$id] = [
                'staff' => $tickets->pluck('served_by')->filter()->unique()->count(),
                'busy' => $tickets
                    ->filter(fn (QueueTicket $ticket): bool => in_array($ticket->status->value, $inService, true))
                    ->map(fn (QueueTicket $ticket): ?Carbon => $ticket->started_at)
                    ->values()
                    ->all(),
            ];
        }

        return $live;
    }
}
