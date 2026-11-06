<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesViewerAbilities;
use App\Models\Queue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A queue configuration as seen by the business team. Never contains the
 * public link token (see GET /queues/{queue}/public-link).
 *
 * @mixin Queue
 */
class QueueResource extends JsonResource
{
    use ResolvesViewerAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Queue $queue */
        $queue = $this->resource;
        $abilities = $this->viewerAbilities($queue->branch->business_id);

        return [
            'id' => $queue->public_id,
            'name' => $queue->name,
            'branch' => ['id' => $queue->branch->public_id, 'name' => $queue->branch->name],
            'service' => $queue->service === null ? null : [
                'id' => $queue->service->public_id,
                'name' => $queue->service->name,
            ],
            'ticket_prefix' => $queue->ticket_prefix,
            'status' => $queue->status->value,
            'capacity' => $queue->capacity,
            'allow_guest_join' => $queue->allow_guest_join,
            'require_guest_name' => $queue->require_guest_name,
            'require_guest_phone' => $queue->require_guest_phone,
            'estimated_service_duration' => $queue->estimated_service_duration,
            'effective_service_minutes' => $queue->configuredServiceMinutes(),
            'grace_period_seconds' => $queue->grace_period_seconds,
            'settings' => $queue->settings ?? (object) [],
            'current_session' => $this->whenLoaded(
                'activeSession',
                fn () => $queue->activeSession === null ? null : new QueueSessionResource($queue->activeSession),
            ),
            'permissions' => $abilities?->queuePermissions($queue),
            'created_at' => $queue->created_at?->toIso8601String(),
            'updated_at' => $queue->updated_at?->toIso8601String(),
        ];
    }
}
