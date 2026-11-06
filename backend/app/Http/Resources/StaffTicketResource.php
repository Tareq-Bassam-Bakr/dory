<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Services\Queues\TicketPositions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket as the business's staff see it. Guest phone numbers are masked
 * in lists and shown in full only on the ticket details endpoint
 * (withContactDetails), to staff who operate the queue. Expects user,
 * server and queue to be loaded; events for the details view.
 *
 * @mixin QueueTicket
 */
class StaffTicketResource extends JsonResource
{
    private bool $contactDetails = false;

    private bool $timeline = false;

    public function withContactDetails(bool $show = true): static
    {
        $this->contactDetails = $show;

        return $this;
    }

    public function withTimeline(bool $show = true): static
    {
        $this->timeline = $show;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QueueTicket $ticket */
        $ticket = $this->resource;
        $viewerId = $request->user()?->getKey();
        $ahead = app(TicketPositions::class)->peopleAhead($ticket);
        $grace = $ticket->queue->grace_period_seconds;

        return [
            'id' => $ticket->public_id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority,
            'join_source' => $ticket->join_source->value,
            'customer' => $this->customer($ticket),
            'position' => $ahead === null ? null : $ahead + 1,
            'served_by' => $ticket->server === null ? null : [
                'id' => $ticket->server->public_id,
                'name' => $ticket->server->name,
                'is_you' => $ticket->served_by === $viewerId,
            ],
            'recall_count' => $ticket->recall_count,
            'cancel_reason' => $ticket->cancel_reason,
            'waited_seconds' => (int) $ticket->joined_at->diffInSeconds($ticket->called_at ?? $ticket->ended_at ?? now(), true),
            'service_seconds' => $ticket->started_at === null ? null
                : (int) $ticket->started_at->diffInSeconds($ticket->completed_at ?? $ticket->ended_at ?? now(), true),
            'no_show_allowed_at' => $ticket->status === TicketStatus::Called && $ticket->called_at !== null
                ? $ticket->called_at->copy()->addSeconds($grace)->toIso8601String()
                : null,
            'joined_at' => $ticket->joined_at->toIso8601String(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'started_at' => $ticket->started_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            'ended_at' => $ticket->ended_at?->toIso8601String(),
            'events' => $this->when($this->timeline, fn () => $ticket->events->map(fn (QueueEvent $event) => [
                'type' => $event->event_type->value,
                'from' => $event->previous_status?->value,
                'to' => $event->new_status?->value,
                'actor' => $event->actor?->name,
                'reason' => $event->metadata['reason'] ?? null,
                'at' => $event->created_at->toIso8601String(),
            ])->values()),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function customer(QueueTicket $ticket): array
    {
        if ($ticket->user_id !== null) {
            return ['type' => 'customer', 'name' => $ticket->user?->name, 'phone' => null];
        }

        $phone = $ticket->guest_phone;

        return [
            'type' => 'guest',
            'name' => $ticket->guest_name,
            'phone' => $phone === null ? null : ($this->contactDetails ? $phone : self::mask($phone)),
        ];
    }

    /**
     * "+963944123456" → "+963••••••456".
     */
    public static function mask(string $phone): string
    {
        $length = mb_strlen($phone);
        if ($length <= 7) {
            return str_repeat('•', $length);
        }

        return mb_substr($phone, 0, 4).str_repeat('•', $length - 7).mb_substr($phone, -3);
    }
}
