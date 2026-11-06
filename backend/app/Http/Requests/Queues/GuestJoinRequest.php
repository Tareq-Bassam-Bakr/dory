<?php

namespace App\Http\Requests\Queues;

use App\Models\Queue;
use App\Support\ContactInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /public/queues/{token}/join: a guest joins with the minimum the
 * queue asks for. A phone number is optional unless the queue requires
 * it; when given, the guest must accept how it is used ("consent"), and it
 * is stored encrypted and only shown to the business's staff.
 */
class GuestJoinRequest extends FormRequest
{
    use ReadsIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::rulesFor($this->queue(), $this->filled('phone'));
    }

    /**
     * Trims and collapses the name; normalizes the phone number. Shared
     * with the guest web page (GuestQueueController).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $data = ContactInput::normalize(array_intersect_key($input, ['phone' => true]));
        if (is_string($input['name'] ?? null)) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $input['name']));
            $data['name'] = $name === '' ? null : $name;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(Queue $queue, bool $phoneGiven): array
    {
        return [
            'name' => [$queue->require_guest_name ? 'required' : 'nullable', 'string', 'min:2', 'max:80'],
            'phone' => [$queue->require_guest_phone ? 'required' : 'nullable', 'string', ContactInput::PHONE_RULE],
            // Asked only when a phone number is given.
            'consent' => $phoneGiven ? ['required', 'accepted'] : ['exclude'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'priority' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateIdempotencyKey($validator)];
    }

    public function queue(): Queue
    {
        /** @var Queue */
        return $this->route('publicQueueToken');
    }

    public function guestName(): ?string
    {
        return $this->validated('name');
    }

    public function guestPhone(): ?string
    {
        return $this->validated('phone');
    }
}
