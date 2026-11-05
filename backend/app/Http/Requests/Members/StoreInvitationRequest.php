<?php

namespace App\Http\Requests\Members;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Http\Requests\Members\Concerns\ValidatesBranchAssignment;
use App\Models\Business;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /businesses/{business}/invitations: owner only.
 *
 * Invitations grant "manager" or "employee". "owner" is rejected: ownership
 * cannot be transferred or shared through invitations.
 */
class StoreInvitationRequest extends FormRequest
{
    use ValidatesBranchAssignment;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manageMembers', $this->business());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in([BusinessRole::Manager->value, BusinessRole::Employee->value])],
            ...$this->branchAssignmentRules(),
            'business_id' => ['prohibited'],
            'status' => ['prohibited'],
            'token' => ['prohibited'],
            'expires_at' => ['prohibited'],
            'invited_by' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->input('email') === mb_strtolower($this->user()->email)) {
                    $validator->errors()->add('email', __('api.invitation.self_invite'));
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

    public function role(): BusinessRole
    {
        return BusinessRole::from($this->validated('role'));
    }

    public function access(): BranchAccess
    {
        return $this->branchAccess() ?? BranchAccess::All;
    }
}
