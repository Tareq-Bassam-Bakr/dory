<?php

namespace App\Http\Resources;

use App\Models\QueueSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QueueSession
 */
class QueueSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'business_date' => $this->business_date->toDateString(),
            'session_number' => $this->session_number,
            'status' => $this->status->value,
            'opened_at' => $this->opened_at->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'close_reason' => $this->close_reason,
            'tickets_count' => $this->whenCounted('tickets'),
        ];
    }
}
