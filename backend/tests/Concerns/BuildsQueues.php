<?php

namespace Tests\Concerns;

use App\Enums\JoinSource;
use App\Enums\TicketStatus;
use App\Models\BusinessMember;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueStaffAssignment;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Services\Queues\QueueSessionService;
use App\Services\Queues\TicketNumberAllocator;
use Illuminate\Support\Facades\DB;

/**
 * Builds queues, sessions, tickets and staff assignments the way the
 * engine does. Use together with BuildsTenants.
 */
trait BuildsQueues
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function queueFor(Service $service, array $attributes = []): Queue
    {
        return Queue::factory()->forService($service)->create($attributes)->load(['branch.business', 'service']);
    }

    protected function openSession(Queue $queue, User $actor): QueueSession
    {
        return app(QueueSessionService::class)->open($queue->loadMissing('branch'), $actor);
    }

    /**
     * A ticket numbered by the real allocator, in any state.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function ticketIn(QueueSession $session, TicketStatus $status = TicketStatus::Waiting, array $attributes = []): QueueTicket
    {
        return DB::transaction(function () use ($session, $status, $attributes): QueueTicket {
            $queue = $session->queue;
            $number = app(TicketNumberAllocator::class)->next($queue, $session->business_date);
            $occupied = in_array($status, [TicketStatus::Called, TicketStatus::Serving], true);

            $ticket = new QueueTicket;
            $ticket->forceFill($attributes + [
                'queue_session_id' => $session->getKey(),
                'queue_id' => $queue->getKey(),
                'ticket_number' => TicketNumberAllocator::format($queue->ticket_prefix, $number),
                'sequence_number' => $number,
                'business_date' => $session->business_date->toDateString(),
                'status' => $status,
                'join_source' => JoinSource::App,
                'joined_at' => now(),
                'called_at' => $occupied ? now() : null,
                'started_at' => $status === TicketStatus::Serving ? now() : null,
            ])->save();

            return $ticket;
        });
    }

    protected function assignToQueue(Queue $queue, User $user, ?User $by = null): QueueStaffAssignment
    {
        $member = BusinessMember::query()
            ->where('user_id', $user->getKey())
            ->where('business_id', $queue->branch->business_id)
            ->sole();

        $assignment = new QueueStaffAssignment;
        $assignment->forceFill([
            'queue_id' => $queue->getKey(),
            'business_member_id' => $member->getKey(),
            'assigned_by' => $by?->getKey(),
            'assigned_at' => now(),
        ])->save();

        return $assignment;
    }

    protected function queueUri(Queue $queue, string $suffix = ''): string
    {
        return '/api/v1/queues/'.$queue->public_id.($suffix === '' ? '' : '/'.$suffix);
    }
}
