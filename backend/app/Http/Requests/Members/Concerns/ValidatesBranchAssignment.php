<?php

namespace App\Http\Requests\Members\Concerns;

use App\Enums\BranchAccess;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Branch scoping of a membership or invitation: branch_access "all", or
 * "selected" with a list of branch ids that must all belong to the business.
 */
trait ValidatesBranchAssignment
{
    /** @var Collection<int, Branch>|null */
    private ?Collection $resolvedBranches = null;

    /**
     * @return array<string, list<mixed>>
     */
    protected function branchAssignmentRules(): array
    {
        return [
            // Optional: invitations default to "all"; updates keep the current value.
            'branch_access' => ['sometimes', 'required', Rule::enum(BranchAccess::class)],
            'branches' => ['exclude_unless:branch_access,selected', 'required', 'array', 'min:1', 'max:100'],
            'branches.*' => ['string', 'size:26', 'distinct'],
        ];
    }

    protected function validateBranchesBelongTo(Business $business, Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('branch_access') !== BranchAccess::Selected->value) {
            return;
        }

        $ids = array_values(array_unique((array) $this->input('branches', [])));
        $this->resolvedBranches = $business->branches()->whereIn('public_id', $ids)->get();

        if ($this->resolvedBranches->count() !== count($ids)) {
            // Same message whether a branch is missing or belongs to another tenant.
            $validator->errors()->add('branches', __('api.invitation.branches_invalid'));
        }
    }

    public function branchAccess(): ?BranchAccess
    {
        $access = $this->validated('branch_access');

        return $access === null ? null : BranchAccess::from($access);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function assignedBranches(): Collection
    {
        return $this->resolvedBranches ?? collect();
    }
}
