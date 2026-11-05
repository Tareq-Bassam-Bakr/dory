<?php

namespace App\Http\Resources;

use App\Services\Auth\AuthResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Login/registration payload: the user plus the bearer token. The plain
 * token appears only in this one response and is never stored or logged.
 *
 * @property AuthResult $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource->user),
            'token' => [
                'access_token' => $this->resource->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $this->resource->expiresAt?->toIso8601String(),
            ],
        ];
    }
}
