<?php

namespace App\Http\Requests\Businesses;

use App\Enums\BusinessStatus;
use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Support\ContactInput;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /businesses/{business}: partial update of the profile and, for the
 * owner only, the status (active or inactive). Authorization: controller.
 */
class UpdateBusinessRequest extends FormRequest
{
    use BusinessFieldRules;

    private const FIELDS = ['name', 'category', 'description', 'email', 'phone', 'website', 'timezone', 'status'];

    /**
     * Runs before validation: profile fields need `update` (owner, or manager
     * with all-branch access); the status needs `changeStatus` (owner).
     */
    public function authorize(): Response
    {
        $gate = Gate::forUser($this->user());
        $business = $this->business();

        if ($this->has('status')) {
            $response = $gate->inspect('changeStatus', $business);
            if ($response->denied()) {
                return $response;
            }
        }

        return $this->hasAny(array_diff(self::FIELDS, ['status'])) || ! $this->has('status')
            ? $gate->inspect('update', $business)
            : Response::allow();
    }

    public function business(): Business
    {
        /** @var Business */
        return $this->route('business');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ContactInput::normalize($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->businessProfileRules(partial: true),
            ...$this->protectedBusinessFieldRules(),
            'status' => ['sometimes', 'required', Rule::in(BusinessStatus::ownerSettable())],
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
                    $validator->errors()->add('business', __('api.business.fields_required'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function businessAttributes(): array
    {
        return Arr::only($this->validated(), ['name', 'description', 'email', 'phone', 'website', 'timezone']);
    }

    public function category(): ?BusinessCategory
    {
        $slug = $this->validated('category');

        return $slug === null ? null : BusinessCategory::query()->where('slug', $slug)->firstOrFail();
    }

    public function status(): ?BusinessStatus
    {
        $status = $this->validated('status');

        return $status === null ? null : BusinessStatus::from($status);
    }
}
