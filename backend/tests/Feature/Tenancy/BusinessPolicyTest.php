<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BusinessPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = app(BusinessMembershipService::class)
            ->createBusinessWithOwner(User::factory()->create(), 'Shifa Clinic');
    }

    private function memberWithRole(BusinessRole $role): User
    {
        $user = User::factory()->create();
        app(BusinessMembershipService::class)->addMember($this->business, $user, $role);

        return $user;
    }

    /**
     * @return array{view: bool, update: bool, manageMembers: bool}
     */
    private function abilitiesOf(User $user): array
    {
        return [
            'view' => Gate::forUser($user)->allows('view', $this->business),
            'update' => Gate::forUser($user)->allows('update', $this->business),
            'manageMembers' => Gate::forUser($user)->allows('manageMembers', $this->business),
        ];
    }

    public function test_owner_has_full_control(): void
    {
        $owner = $this->business->members()->sole()->user;

        $this->assertSame(['view' => true, 'update' => true, 'manageMembers' => true], $this->abilitiesOf($owner));
    }

    public function test_manager_can_manage_the_business_but_not_its_members(): void
    {
        $manager = $this->memberWithRole(BusinessRole::Manager);

        $this->assertSame(['view' => true, 'update' => true, 'manageMembers' => false], $this->abilitiesOf($manager));
    }

    public function test_employee_can_only_view(): void
    {
        $employee = $this->memberWithRole(BusinessRole::Employee);

        $this->assertSame(['view' => true, 'update' => false, 'manageMembers' => false], $this->abilitiesOf($employee));
    }

    public function test_a_user_from_another_tenant_has_no_access(): void
    {
        $outsider = User::factory()->create();
        app(BusinessMembershipService::class)->createBusinessWithOwner($outsider, 'Outsider Repairs');

        $this->assertSame(['view' => false, 'update' => false, 'manageMembers' => false], $this->abilitiesOf($outsider));
    }

    public function test_a_suspended_member_loses_access(): void
    {
        $member = User::factory()->create();
        BusinessMember::factory()->manager()->suspended()->create([
            'business_id' => $this->business->id,
            'user_id' => $member->id,
        ]);

        $this->assertSame(['view' => false, 'update' => false, 'manageMembers' => false], $this->abilitiesOf($member));
    }

    public function test_platform_admin_can_view_but_not_manage_without_membership(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->assertSame(['view' => true, 'update' => false, 'manageMembers' => false], $this->abilitiesOf($admin));
    }
}
