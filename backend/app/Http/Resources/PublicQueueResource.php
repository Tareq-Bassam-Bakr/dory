<?php

namespace App\Http\Resources;

use App\Enums\QueueSessionStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Services\Queues\TicketPositions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What anyone with the queue's public link may see before joining. No
 * customer data: only counts, the numbers being served and the rules for
 * joining. Expects branch.business, service and activeSession.
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

        return [
            'id' => $queue->public_id,
            'name' => $queue->name,
            'business' => ['id' => $queue->branch->business->public_id, 'name' => $queue->branch->business->name],
            'branch' => [
                'id' => $queue->branch->public_id,
                'name' => $queue->branch->name,
                'city' => $queue->branch->city,
                'address' => $queue->branch->address,
            ],
            'service' => $queue->service === null ? null : ['name' => $queue->service->name],
            'status' => $this->status($queue, $session),
            'accepting_joins' => $queue->isActive() && $session?->status === QueueSessionStatus::Open,
            'waiting_count' => $session === null ? 0 : $positions->waitingCount($session),
            'now_serving' => $session === null ? [] : $positions->nowServing($session)->values()->all(),
            'estimated_wait' => $positions->estimateForNewcomer($queue, $session)->toArray(),
            'allow_guest_join' => $queue->allow_guest_join,
            'require_guest_name' => $queue->require_guest_name,
            'require_guest_phone' => $queue->require_guest_phone,
        ];
    }

    /**
     * inactive (disabled by the business), closed (no session), paused, open.
     */
    private function status(Queue $queue, ?QueueSession $session): string
    {
        if (! $queue->isActive()) {
            return 'inactive';
        }

        return $session?->status->value ?? 'closed';
    }
}
