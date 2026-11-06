<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Services\Queues\TicketPositions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket as its holder (customer or guest) sees it: number, status,
 * position and estimate. No staff identities, no guest contact details,
 * no internal ids. Expects queue.branch.business, queue.service and session.
 *
 * @mixin QueueTicket
 */
class CustomerTicketResource extends JsonResource
{
    private ?string $accessToken = null;

    /**
     * Adds the guest access token to this one response (right after joining).
     */
    public function withAccessToken(?string $token): static
    {
        $this->accessToken = $token;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QueueTicket $ticket */
        $ticket = $this->resource;
        $queue = $ticket->queue;
        $session = $ticket->session;
        $positions = app(TicketPositions::class);

        return [
            'id' => $ticket->public_id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'business_date' => $ticket->business_date->toDateString(),
            'queue' => [
                'id' => $queue->public_id,
                'name' => $queue->name,
                'session_status' => $session->status->value,
            ],
            'business' => ['id' => $queue->branch->business->public_id, 'name' => $queue->branch->business->name],
            'branch' => ['id' => $queue->branch->public_id, 'name' => $queue->branch->name],
            'service' => $queue->service === null ? null : ['name' => $queue->service->name],
            ...$positions->describe($ticket, $queue),
            'now_serving' => $ticket->status->isActive() ? $positions->nowServing($session)->values()->all() : [],
            // Called customers have this long to reach the counter.
            'arrive_by' => $ticket->status === TicketStatus::Called
                ? $ticket->called_at?->copy()->addSeconds($queue->grace_period_seconds)->toIso8601String()
                : null,
            'can_cancel' => $ticket->status === TicketStatus::Waiting,
            'cancel_reason' => $ticket->cancel_reason,
            'joined_at' => $ticket->joined_at->toIso8601String(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'started_at' => $ticket->started_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            'ended_at' => $ticket->ended_at?->toIso8601String(),
            'access_token' => $this->when($this->accessToken !== null, $this->accessToken),
        ];
    }
}
