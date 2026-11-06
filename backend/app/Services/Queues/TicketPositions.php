<?php

namespace App\Services\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where a ticket stands: queue order is priority (higher first) then join
 * order (id), the same order call-next uses. In Phase 04 every ticket has
 * priority 0, so the order is first in, first out.
 */
final class TicketPositions
{
    public function __construct(private readonly WaitTimeEstimator $estimator) {}

    /**
     * Waiting tickets before this one; null when the ticket is not waiting.
     */
    public function peopleAhead(QueueTicket $ticket): ?int
    {
        if ($ticket->status !== TicketStatus::Waiting) {
            return null;
        }

        return $this->waiting($ticket->queue_session_id)
            ->where(fn (Builder $query) => $query
                ->where('priority', '>', $ticket->priority)
                ->orWhere(fn (Builder $same) => $same
                    ->where('priority', $ticket->priority)
                    ->where('id', '<', $ticket->getKey())))
            ->count();
    }

    /**
     * @return array{position: int|null, people_ahead: int|null, estimated_wait: array<string, int|string>|null}
     */
    public function describe(QueueTicket $ticket, Queue $queue): array
    {
        $ahead = $this->peopleAhead($ticket);

        return [
            'position' => $ahead === null ? null : $ahead + 1,
            'people_ahead' => $ahead,
            'estimated_wait' => $ahead === null ? null : $this->estimator->estimate($queue, $ahead)->toArray(),
        ];
    }

    public function waitingCount(QueueSession $session): int
    {
        return $this->waiting($session->getKey())->count();
    }

    /**
     * The estimate for someone joining now (behind everyone waiting).
     */
    public function estimateForNewcomer(Queue $queue, ?QueueSession $session): WaitEstimate
    {
        return $this->estimator->estimate($queue, $session === null ? 0 : $this->waitingCount($session));
    }

    /**
     * Ticket numbers being called or served right now.
     *
     * @return Collection<int, string>
     */
    public function nowServing(QueueSession $session): Collection
    {
        return QueueTicket::query()
            ->where('queue_session_id', $session->getKey())
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])
            ->orderBy('called_at')
            ->orderBy('id')
            ->pluck('ticket_number');
    }

    /**
     * @return Builder<QueueTicket>
     */
    private function waiting(int $sessionId): Builder
    {
        return QueueTicket::query()
            ->where('queue_session_id', $sessionId)
            ->where('status', TicketStatus::Waiting->value);
    }
}
