<?php

namespace App\Http\Resources;

use App\Models\PushDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registered device. Never includes the token or its hash.
 */
class PushDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PushDevice $device */
        $device = $this->resource;

        return [
            'id' => $device->public_id,
            'platform' => $device->platform->value,
            'locale' => $device->locale,
            'app_version' => $device->app_version,
            'registered_at' => $device->created_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
