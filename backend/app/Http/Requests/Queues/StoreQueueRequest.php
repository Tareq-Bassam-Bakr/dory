<?php

namespace App\Http\Requests\Queues;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * POST /branches/{branch}/queues: one queue per service of the branch.
 */
class StoreQueueRequest extends FormRequest
{
    use QueueConfigurationRules;

    private ?Service $resolvedService = null;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manageQueues', $this->branch());
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
            ...$this->configurationRules(partial: false),
            'service' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branch = $this->branch();
                $this->validatePrefixIsFree($validator, $branch->getKey());

                if ($validator->errors()->has('service')) {
                    return;
                }

                // The service must belong to this branch (hence the same business).
                $this->resolvedService = $branch->services()->where('public_id', $this->input('service'))->first();
                if ($this->resolvedService === null) {
                    $validator->errors()->add('service', __('api.queue.service_not_in_branch'));

                    return;
                }

                if (Queue::query()->where('active_slot', $this->resolvedService->getKey().':1')->exists()) {
                    $validator->errors()->add('service', __('api.queue.service_has_queue'));
                }
            },
        ];
    }

    public function branch(): Branch
    {
        /** @var Branch */
        return $this->route('branch');
    }

    public function service(): Service
    {
        /** @var Service */
        return $this->resolvedService;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return collect($this->validated())->except('service')->all();
    }
}
