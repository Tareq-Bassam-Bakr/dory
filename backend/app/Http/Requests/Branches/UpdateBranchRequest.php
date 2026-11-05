<?php

namespace App\Http\Requests\Branches;

use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Models\Branch;
use App\Support\ContactInput;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PUT /branches/{branch}: partial update by the owner or a manager with
 * access to the branch.
 */
class UpdateBranchRequest extends FormRequest
{
    use BusinessFieldRules;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->branch());
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
            ...$this->branchRules(partial: true),
            ...$this->protectedBranchFieldRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->isEmpty()) {
                    return;
                }

                if (! $this->hasAny(self::branchKeys())) {
                    $validator->errors()->add('branch', __('api.branch.fields_required'));

                    return;
                }

                // Coordinates come as a pair: updating one would pair it with
                // the stored value of the other.
                if ($this->has('latitude') !== $this->has('longitude')) {
                    $validator->errors()->add('latitude', __('api.branch.coordinates_pair'));
                }
            },
        ];
    }

    public function branch(): Branch
    {
        /** @var Branch */
        return $this->route('branch');
    }
}
