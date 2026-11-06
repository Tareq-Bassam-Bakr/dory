<?php

namespace App\Http\Requests\Queues;

use App\Enums\QueueStatus;
use App\Models\Queue;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation shared by queue create and update.
 */
trait QueueConfigurationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function configurationRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'min:2', 'max:150'],
            // 1–6 upper-case letters or digits, starting with a letter: BAR, MED1.
            'ticket_prefix' => [...$required, 'string', 'regex:/^[A-Z][A-Z0-9]{0,5}$/'],
            'status' => ['sometimes', 'required', Rule::enum(QueueStatus::class)],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'allow_guest_join' => ['sometimes', 'required', 'boolean'],
            'require_guest_name' => ['sometimes', 'required', 'boolean'],
            'require_guest_phone' => ['sometimes', 'required', 'boolean'],
            'estimated_service_duration' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'grace_period_seconds' => ['sometimes', 'required', 'integer', 'min:0', 'max:3600'],
            'settings' => ['sometimes', 'nullable', 'array', 'max:20'],
            // Identity, tenancy and link secrets are never client input.
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'business_id' => ['prohibited'],
            'service_id' => ['prohibited'],
            'public_token' => ['prohibited'],
            'public_token_hash' => ['prohibited'],
            'lane' => ['prohibited'],
        ];
    }

    protected function normalizePrefix(): void
    {
        if (is_string($this->input('ticket_prefix'))) {
            $this->merge(['ticket_prefix' => mb_strtoupper(trim($this->input('ticket_prefix')))]);
        }
    }

    /**
     * One prefix per branch among live queues (also enforced by a unique index).
     */
    protected function validatePrefixIsFree(Validator $validator, int $branchId, ?Queue $ignore = null): void
    {
        $prefix = $this->input('ticket_prefix');
        if (! is_string($prefix) || $validator->errors()->has('ticket_prefix')) {
            return;
        }

        $taken = Queue::query()
            ->where('active_prefix', $branchId.':'.$prefix)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken) {
            $validator->errors()->add('ticket_prefix', __('api.queue.prefix_taken'));
        }
    }
}
