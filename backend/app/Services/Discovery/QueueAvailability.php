<?php

namespace App\Services\Discovery;

use App\Contracts\WaitTimeEstimator;
use App\Enums\QueueSessionStatus;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueTicket;
use Illuminate\Support\Collection;

/**
 * Live availability of many queues in a constant number of queries, so list
 * endpoints do not grow one query per queue. Always read from the current
 * session; a past session never makes a queue look open.
 */
final class QueueAvailability
{
    public function __construct(private readonly WaitTimeEstimator $estimator) {}

    /**
     * Expects each queue to have activeSession, branch.business and service.
     *
     * @param  Collection<int, Queue>  $queues
     * @return array<int, QueueAvailabilityView> Keyed by internal queue id.
     */
    public function for(Collection $queues): array
    {
        if ($queues->isEmpty()) {
            return [];
        }

        $this->estimator->warm($queues);
        $waiting = $this->waitingCounts($queues);

        $views = [];
        foreach ($queues as $queue) {
            $views[$queue->getKey()] = $this->view($queue, (int) ($waiting[$queue->activeSession?->getKey()] ?? 0));
        }

        return $views;
    }

    public function forQueue(Queue $queue): QueueAvailabilityView
    {
        return $this->for(new Collection([$queue]))[$queue->getKey()];
    }

    private function view(Queue $queue, int $waiting): QueueAvailabilityView
    {
        $session = $queue->activeSession;
        $reason = $queue->isActive() ? $queue->unavailabilityReason() : 'queue_inactive';
        $status = $reason === null ? ($session?->status->value ?? 'closed') : 'inactive';
        $isFull = $queue->capacity !== null && $waiting >= $queue->capacity;

        return new QueueAvailabilityView(
            queue: $queue,
            status: $status,
            unavailableReason: $reason,
            acceptingJoins: $status === QueueSessionStatus::Open->value && ! $isFull,
            isFull: $isFull,
            waitingCount: $waiting,
            estimate: $this->estimator->estimate($queue, $waiting),
        );
    }

    /**
     * @param  Collection<int, Queue>  $queues
     * @return Collection<int|string, mixed> Waiting tickets by session id.
     */
    private function waitingCounts(Collection $queues): Collection
    {
        $sessions = $queues->map(fn (Queue $queue) => $queue->activeSession?->getKey())->filter()->values();

        return $sessions->isEmpty() ? collect() : QueueTicket::query()
            ->whereIn('queue_session_id', $sessions)
            ->where('status', TicketStatus::Waiting->value)
            ->groupBy('queue_session_id')
            ->selectRaw('queue_session_id, count(*) as total')
            ->pluck('total', 'queue_session_id');
    }
}
