<?php

namespace App\Http\Requests\Branches;

use App\Http\Requests\Concerns\BusinessFieldRules;
use App\Models\Business;
use App\Support\ContactInput;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /businesses/{business}/branches. Owners and managers with access to
 * all branches may create branches.
 */
class StoreBranchRequest extends FormRequest
{
    use BusinessFieldRules;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('createBranch', $this->business());
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
            ...$this->branchRules(partial: false),
            ...$this->protectedBranchFieldRules(),
        ];
    }
}
