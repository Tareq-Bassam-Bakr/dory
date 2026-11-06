<?php

namespace App\Http\Resources;

use App\Models\Queue;
use App\Models\QueueSession;
use App\Services\Discovery\QueueAvailability;
use App\Services\Queues\TicketPositions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What anyone with the queue's public link may see before joining. No
 * customer data: only counts, the numbers being served and the rules for
 * joining. Expects branch.business, service and activeSession.
 *
 * status: inactive (disabled, or its business, branch or service is not
 * available; see unavailable_reason), closed (no session), paused, open.
 *
 * @mixin Queue
 */
class PublicQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Queue $queue */
        $queue = $this->resource;
        /** @var QueueSession|null $session */
        $session = $queue->activeSession;
        $positions = app(TicketPositions::class);
        $availability = app(QueueAvailability::class)->forQueue($queue);

        return [
            'id' => $queue->public_id,
            'name' => $queue->name,
            'business' => [
                'id' => $queue->branch->business->public_id,
                'slug' => $queue->branch->business->slug,
                'name' => $queue->branch->business->name,
            ],
            'branch' => [
                'id' => $queue->branch->public_id,
                'name' => $queue->branch->name,
                'city' => $queue->branch->city,
                'address' => $queue->branch->address,
            ],
            'service' => $queue->service === null ? null : ['name' => $queue->service->name],
            'status' => $availability->status,
            'unavailable_reason' => $availability->unavailableReason,
            'accepting_joins' => $availability->acceptingJoins,
            'is_full' => $availability->isFull,
            'waiting_count' => $availability->waitingCount,
            'capacity' => $queue->capacity,
            'capacity_remaining' => $availability->toArray()['capacity_remaining'],
            'now_serving' => $session === null ? [] : $positions->nowServing($session)->values()->all(),
            'estimated_wait' => $availability->estimate->toArray(),
            'allow_guest_join' => $queue->allow_guest_join,
            'require_guest_name' => $queue->require_guest_name,
            'require_guest_phone' => $queue->require_guest_phone,
        ];
    }
}
