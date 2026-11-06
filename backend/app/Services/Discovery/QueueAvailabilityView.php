<?php

namespace App\Services\Discovery;

use App\Models\Queue;
use App\Services\Queues\WaitEstimate;

/**
 * Whether a queue can take a customer right now, as the public may see it:
 * counts and rules only, never anything about the people waiting.
 */
final readonly class QueueAvailabilityView
{
    /**
     * @param  string  $status  open, paused, closed (no session) or inactive.
     * @param  string|null  $unavailableReason  Why it is inactive:
     *                                          queue_inactive, business_unavailable, branch_inactive or service_unavailable.
     */
    public function __construct(
        public Queue $queue,
        public string $status,
        public ?string $unavailableReason,
        public bool $acceptingJoins,
        public bool $isFull,
        public int $waitingCount,
        public WaitEstimate $estimate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $capacity = $this->queue->capacity;

        return [
            'id' => $this->queue->public_id,
            'name' => $this->queue->name,
            'status' => $this->status,
            'unavailable_reason' => $this->unavailableReason,
            'accepting_joins' => $this->acceptingJoins,
            'is_full' => $this->isFull,
            'waiting_count' => $this->waitingCount,
            'capacity' => $capacity,
            'capacity_remaining' => $capacity === null ? null : max(0, $capacity - $this->waitingCount),
            'estimated_wait' => $this->estimate->toArray(),
            'allow_guest_join' => $this->queue->allow_guest_join,
            'require_guest_name' => $this->queue->require_guest_name,
            'require_guest_phone' => $this->queue->require_guest_phone,
        ];
    }
}
