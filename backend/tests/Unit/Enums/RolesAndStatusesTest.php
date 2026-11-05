<?php

namespace Tests\Unit\Enums;

use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class RolesAndStatusesTest extends TestCase
{
    public function test_business_role_permissions(): void
    {
        $this->assertTrue(BusinessRole::Owner->canManageBusiness());
        $this->assertTrue(BusinessRole::Owner->canManageMembers());

        $this->assertTrue(BusinessRole::Manager->canManageBusiness());
        $this->assertFalse(BusinessRole::Manager->canManageMembers());

        $this->assertFalse(BusinessRole::Employee->canManageBusiness());
        $this->assertFalse(BusinessRole::Employee->canManageMembers());
    }

    public function test_only_active_memberships_grant_access(): void
    {
        $this->assertTrue(MembershipStatus::Active->grantsAccess());
        $this->assertFalse(MembershipStatus::Invited->grantsAccess());
        $this->assertFalse(MembershipStatus::Suspended->grantsAccess());
    }

    public function test_only_active_users_can_authenticate(): void
    {
        $this->assertTrue(UserStatus::Active->canAuthenticate());
        $this->assertFalse(UserStatus::Suspended->canAuthenticate());
        $this->assertFalse(UserStatus::Deactivated->canAuthenticate());
    }
}
