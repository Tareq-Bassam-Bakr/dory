<?php

namespace App\Http\Requests\Queues;

use App\Models\Queue;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PUT /queues/{queue}: partial update, including activation and
 * deactivation. The service and branch of a queue never change.
 */
class UpdateQueueRequest extends FormRequest
{
    use QueueConfigurationRules;

    private const FIELDS = [
        'name', 'ticket_prefix', 'status', 'capacity', 'allow_guest_join', 'require_guest_name',
        'require_guest_phone', 'estimated_service_duration', 'grace_period_seconds', 'settings',
    ];

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manage', $this->queue());
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePrefix();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->configurationRules(partial: true),
            'service' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->hasAny(self::FIELDS)) {
                    $validator->errors()->add('queue', __('api.business.fields_required'));
                }

                $queue = $this->queue();
                $this->validatePrefixIsFree($validator, $queue->branch_id, $queue);
            },
        ];
    }

    public function queue(): Queue
    {
        /** @var Queue */
        return $this->route('queue');
    }
}
