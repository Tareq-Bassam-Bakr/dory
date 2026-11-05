<?php

namespace App\Http\Requests\Businesses;

use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Models\BusinessCategory;
use App\Support\ContactInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * POST /businesses: the business profile, plus an optional first branch
 * with its services (the onboarding flow), created atomically.
 *
 * An optional Idempotency-Key header makes the request safe to retry.
 */
class StoreBusinessRequest extends FormRequest
{
    use BusinessFieldRules;

    public const MAX_ONBOARDING_SERVICES = 50;

    private const IDEMPOTENCY_KEY_PATTERN = '/^[A-Za-z0-9_-]{16,64}$/';

    public function authorize(): bool
    {
        // Any active, authenticated user may create a business.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ContactInput::normalize($this->all()));

        if (is_array($this->input('branch'))) {
            $this->merge(['branch' => ContactInput::normalize($this->input('branch'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $whenBranch = ['exclude_without:branch'];

        return [
            ...$this->businessProfileRules(partial: false),
            ...$this->protectedBusinessFieldRules(),
            // New businesses are always active; suspension is a platform action.
            'status' => ['prohibited'],
            // Services always belong to a branch.
            'services' => ['prohibited'],

            'branch' => ['sometimes', 'array:'.implode(',', [...self::branchKeys(), 'services'])],
            ...$this->branchRules('branch.', partial: false, conditions: $whenBranch),

            'branch.services' => [...$whenBranch, 'sometimes', 'array', 'max:'.self::MAX_ONBOARDING_SERVICES],
            'branch.services.*' => ['array:'.implode(',', self::serviceKeys())],
            ...$this->serviceRules('branch.services.*.', nameRules: ['distinct:ignore_case']),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header('Idempotency-Key');

                if ($key !== null && preg_match(self::IDEMPOTENCY_KEY_PATTERN, $key) !== 1) {
                    $validator->errors()->add('idempotency_key', __('api.business.idempotency_key_invalid'));
                }
            },
        ];
    }

    /**
     * Profile fields for the Business model.
     *
     * @return array<string, mixed>
     */
    public function businessAttributes(): array
    {
        return Arr::only($this->validated(), ['name', 'description', 'email', 'phone', 'website', 'timezone']);
    }

    public function category(): BusinessCategory
    {
        return BusinessCategory::query()->where('slug', $this->validated('category'))->firstOrFail();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function branchAttributes(): ?array
    {
        $branch = $this->validated('branch');

        return is_array($branch) ? Arr::except($branch, ['services']) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serviceAttributes(): array
    {
        return array_values($this->validated('branch.services') ?? []);
    }

    public function idempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key');
    }
}
