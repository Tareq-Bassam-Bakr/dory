<?php

namespace App\Http\Resources;

use App\Models\BusinessCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business category. The slug is its public identifier; names are given
 * in every supported language so clients can switch language offline.
 *
 * @mixin BusinessCategory
 */
class BusinessCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => [
                'en' => $this->name['en'] ?? null,
                'ar' => $this->name['ar'] ?? $this->name['en'] ?? null,
            ],
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
