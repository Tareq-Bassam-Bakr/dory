<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service offered by a branch.
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'branch_id' => $this->whenLoaded('branch', fn () => $this->branch->public_id),
            'name' => $this->name,
            'description' => $this->description,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'display_order' => $this->display_order,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
