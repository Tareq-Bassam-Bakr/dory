<?php

namespace App\Http\Requests\Queues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /queues/{queue}/join: a signed-in customer joins. The ticket is
 * always the caller's own; no identity fields are accepted.
 */
class JoinQueueRequest extends FormRequest
{
    use ReadsIdempotencyKey;

    public function authorize(): bool
    {
        // Any active, signed-in user may join a live queue.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'priority' => ['prohibited'],
            'ticket_number' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateIdempotencyKey($validator)];
    }
}
