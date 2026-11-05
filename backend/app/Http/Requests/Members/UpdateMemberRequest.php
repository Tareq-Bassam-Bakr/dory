<?php

namespace App\Http\Requests\Members;

use App\Enums\BusinessRole;
use App\Http\Requests\Members\Concerns\ValidatesBranchAssignment;
use App\Models\Business;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /businesses/{business}/members/{member}: owner only. Changes the
 * role (manager or employee) and branch access of another member.
 */
class UpdateMemberRequest extends FormRequest
{
    use ValidatesBranchAssignment;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manageMembers', $this->business());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::in([BusinessRole::Manager->value, BusinessRole::Employee->value])],
            ...$this->branchAssignmentRules(),
            'user_id' => ['prohibited'],
            'business_id' => ['prohibited'],
            'status' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->hasAny(['role', 'branch_access'])) {
                    $validator->errors()->add('member', __('api.member.fields_required'));
                }

                $this->validateBranchesBelongTo($this->business(), $validator);
            },
        ];
    }

    public function business(): Business
    {
        /** @var Business */
        return $this->route('business');
    }

    public function role(): ?BusinessRole
    {
        $role = $this->validated('role');

        return $role === null ? null : BusinessRole::from($role);
    }
}
