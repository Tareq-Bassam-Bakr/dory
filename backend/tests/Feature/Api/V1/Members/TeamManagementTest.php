<?php

namespace Tests\Feature\Api\V1\Members;

use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private User $manager;

    private User $employee;

    private Branch $mazzeh;

    private Branch $malki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Owner Olivia']);
        $this->business = $this->businessOwnedBy($this->owner);
        $this->mazzeh = $this->branchOf($this->business, ['name' => 'Mazzeh']);
        $this->malki = $this->branchOf($this->business, ['name' => 'Malki']);
        $this->employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->manager = $this->memberOf($this->business, BusinessRole::Manager);
    }

    private function membersUri(): string
    {
        return '/api/v1/businesses/'.$this->business->public_id.'/members';
    }

    private function memberUri(User $user): string
    {
        return $this->membersUri().'/'.$this->membershipOf($user, $this->business)->public_id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateMember(User $actor, User $target, array $data): TestResponse
    {
        return $this->actingAsUser($actor, 'PUT', $this->memberUri($target), $data);
    }

    // ----- Listing -------------------------------------------------------

    public function test_the_owner_lists_the_team_owner_first(): void
    {
        $response = $this->actingAsUser($this->owner, 'GET', $this->membersUri());

        $this->assertApiSuccess($response)->assertJsonPath('meta.pagination.total', 3);
        $this->assertSame(['owner', 'manager', 'employee'], $response->json('data.*.role'));
        $response->assertJsonPath('data.0.user.name', 'Owner Olivia')
            ->assertJsonPath('data.0.is_you', true)
            ->assertJsonPath('data.0.permissions', ['update' => false, 'remove' => false])
            ->assertJsonPath('data.1.permissions', ['update' => true, 'remove' => true]);
        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
    }

    public function test_managers_view_the_team_read_only_and_employees_cannot_view_it(): void
    {
        $this->actingAsUser($this->manager, 'GET', $this->membersUri())->assertOk()
            ->assertJsonPath('data.2.permissions', ['update' => false, 'remove' => false]);
        $this->assertApiError($this->actingAsUser($this->employee, 'GET', $this->membersUri()), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser(User::factory()->create(), 'GET', $this->membersUri()), 404, 'business_not_found');
    }

    // ----- Changing roles and branch access -----------------------------

    public function test_the_owner_promotes_an_employee_to_manager(): void
    {
        $response = $this->updateMember($this->owner, $this->employee, ['role' => 'manager']);

        $this->assertApiSuccess($response)->assertJsonPath('data.role', 'manager');
        $this->assertSame(BusinessRole::Manager, $this->membershipOf($this->employee, $this->business)->role);

        $entry = BusinessAuditLog::query()->where('action', AuditAction::MemberUpdated->value)->sole();
        $this->assertSame('employee', $entry->properties['before']['role']);
        $this->assertSame('manager', $entry->properties['after']['role']);
    }

    public function test_the_owner_limits_a_manager_to_selected_branches(): void
    {
        $this->updateMember($this->owner, $this->manager, [
            'branch_access' => 'selected',
            'branches' => [$this->mazzeh->public_id],
        ])->assertOk()
            ->assertJsonPath('data.branch_access', 'selected')
            ->assertJsonPath('data.branches', [['id' => $this->mazzeh->public_id, 'name' => 'Mazzeh']]);

        $this->actingAsUser($this->manager, 'GET', '/api/v1/branches/'.$this->mazzeh->public_id)->assertOk();
        $this->assertApiError($this->actingAsUser($this->manager, 'GET', '/api/v1/branches/'.$this->malki->public_id), 404, 'branch_not_found');

        $this->updateMember($this->owner, $this->manager, ['branch_access' => 'all'])->assertOk()->assertJsonPath('data.branches', []);
        $this->actingAsUser($this->manager, 'GET', '/api/v1/branches/'.$this->malki->public_id)->assertOk();
    }

    public function test_branches_of_another_business_cannot_be_assigned(): void
    {
        $foreign = $this->branchOf($this->businessOwnedBy(User::factory()->create(), ['name' => 'Other']));

        $this->assertApiError(
            $this->updateMember($this->owner, $this->manager, ['branch_access' => 'selected', 'branches' => [$foreign->public_id]]),
            422,
            'validation_failed',
        )->assertJsonValidationErrors('branches');
    }

    public function test_ownership_cannot_be_transferred_or_granted(): void
    {
        $this->assertApiError($this->updateMember($this->owner, $this->manager, ['role' => 'owner']), 422, 'validation_failed')
            ->assertJsonValidationErrors('role');
        $this->assertSame(BusinessRole::Manager, $this->membershipOf($this->manager, $this->business)->role);
        $this->assertSame(1, $this->business->members()->where('role', 'owner')->count());
    }

    public function test_the_owner_membership_is_protected(): void
    {
        $this->assertApiError($this->updateMember($this->owner, $this->owner, ['role' => 'employee']), 403, 'membership_protected');
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $this->memberUri($this->owner)), 403, 'membership_protected');
        $this->assertSame(BusinessRole::Owner, $this->membershipOf($this->owner, $this->business)->role);
    }

    public function test_members_cannot_promote_themselves_or_others(): void
    {
        $this->assertApiError($this->updateMember($this->employee, $this->employee, ['role' => 'manager']), 403, 'forbidden');
        $this->assertApiError($this->updateMember($this->manager, $this->manager, ['branch_access' => 'all']), 403, 'forbidden');
        $this->assertApiError($this->updateMember($this->manager, $this->employee, ['role' => 'manager']), 403, 'forbidden');
        $this->assertApiError($this->updateMember($this->manager, $this->owner, ['role' => 'employee']), 403, 'forbidden');

        $this->assertSame(BusinessRole::Employee, $this->membershipOf($this->employee, $this->business)->role);
        $this->assertSame(BusinessRole::Owner, $this->membershipOf($this->owner, $this->business)->role);
    }

    public function test_identity_fields_of_a_membership_cannot_be_changed(): void
    {
        $other = User::factory()->create();

        $this->assertApiError($this->updateMember($this->owner, $this->employee, ['user_id' => $other->id]), 422, 'validation_failed')
            ->assertJsonValidationErrors('user_id');
        $this->assertApiError($this->updateMember($this->owner, $this->employee, ['status' => 'suspended']), 422, 'validation_failed')
            ->assertJsonValidationErrors('status');
        $this->assertApiError($this->updateMember($this->owner, $this->employee, []), 422, 'validation_failed')
            ->assertJsonValidationErrors('member');
    }

    public function test_members_of_other_businesses_are_not_reachable(): void
    {
        $otherOwner = User::factory()->create();
        $other = $this->businessOwnedBy($otherOwner, ['name' => 'Other']);
        $foreignMember = $this->memberOf($other, BusinessRole::Employee);
        $foreignId = $this->membershipOf($foreignMember, $other)->public_id;

        $this->assertApiError(
            $this->actingAsUser($this->owner, 'PUT', $this->membersUri().'/'.$foreignId, ['role' => 'manager']),
            404,
            'member_not_found',
        );
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $this->membersUri().'/'.$foreignId), 404, 'member_not_found');
        $this->assertSame(BusinessRole::Employee, $this->membershipOf($foreignMember, $other)->role);
    }

    // ----- Removing ------------------------------------------------------

    public function test_the_owner_removes_a_member_who_then_loses_access(): void
    {
        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $this->memberUri($this->employee)));

        $this->assertNull($this->membershipOf($this->employee, $this->business));
        $this->assertApiError(
            $this->actingAsUser($this->employee, 'GET', '/api/v1/businesses/'.$this->business->public_id),
            404,
            'business_not_found',
        );
        $this->assertSame(1, BusinessAuditLog::query()->where('action', AuditAction::MemberRemoved->value)->count());
    }

    public function test_managers_and_employees_cannot_remove_members(): void
    {
        $this->assertApiError($this->actingAsUser($this->manager, 'DELETE', $this->memberUri($this->employee)), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($this->employee, 'DELETE', $this->memberUri($this->manager)), 403, 'forbidden');

        $this->assertSame(3, $this->business->members()->count());
    }

    public function test_a_selected_access_member_without_branches_stays_restricted(): void
    {
        $this->updateMember($this->owner, $this->employee, [
            'branch_access' => 'selected',
            'branches' => [$this->mazzeh->public_id],
        ])->assertOk();
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/branches/'.$this->mazzeh->public_id)->assertOk();

        $membership = $this->membershipOf($this->employee, $this->business);
        $this->assertSame(BranchAccess::Selected, $membership->branch_access);
        $this->actingAsUser($this->employee, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/branches')
            ->assertJsonPath('meta.pagination.total', 0);
    }
}
