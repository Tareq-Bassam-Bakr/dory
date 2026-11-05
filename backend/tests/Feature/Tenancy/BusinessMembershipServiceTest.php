<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Exceptions\DuplicateMembershipException;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessMembershipService $memberships;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberships = app(BusinessMembershipService::class);
    }

    public function test_creating_a_business_makes_the_creator_its_owner(): void
    {
        $owner = User::factory()->create();

        $business = $this->memberships->createBusinessWithOwner($owner, 'Shifa Clinic', 'Family medicine');

        $this->assertSame('shifa-clinic', $business->slug);
        $this->assertTrue($business->creator->is($owner));
        $this->assertSame(BusinessRole::Owner, $this->memberships->roleOf($owner, $business));
        $this->assertNotNull($this->memberships->findMembership($owner, $business)->joined_at);
    }

    public function test_a_user_can_own_one_business_and_work_in_another(): void
    {
        $user = User::factory()->create();
        $ownBusiness = $this->memberships->createBusinessWithOwner($user, 'Rami Barbers');
        $employer = $this->memberships->createBusinessWithOwner(User::factory()->create(), 'City Bank');
        $this->memberships->addMember($employer, $user, BusinessRole::Employee);

        $this->assertSame(BusinessRole::Owner, $this->memberships->roleOf($user, $ownBusiness));
        $this->assertSame(BusinessRole::Employee, $this->memberships->roleOf($user, $employer));
        $this->assertEqualsCanonicalizing(
            [$ownBusiness->id, $employer->id],
            $user->businesses()->pluck('businesses.id')->all(),
        );
    }

    public function test_duplicate_memberships_are_rejected_by_the_service(): void
    {
        $owner = User::factory()->create();
        $business = $this->memberships->createBusinessWithOwner($owner, 'Shifa Clinic');

        $this->expectException(DuplicateMembershipException::class);

        $this->memberships->addMember($business, $owner, BusinessRole::Employee);
    }

    public function test_duplicate_memberships_are_rejected_by_the_database(): void
    {
        $membership = BusinessMember::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        BusinessMember::factory()->create([
            'business_id' => $membership->business_id,
            'user_id' => $membership->user_id,
        ]);
    }

    public function test_a_membership_belongs_to_the_correct_user_and_business(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $business = $this->memberships->createBusinessWithOwner($alice, 'Alice Salon');
        $this->memberships->addMember($business, $bob, BusinessRole::Manager);

        $aliceMembership = $alice->memberships()->sole();
        $this->assertSame($alice->id, $aliceMembership->user_id);
        $this->assertSame($business->id, $aliceMembership->business_id);
        $this->assertSame(BusinessRole::Owner, $aliceMembership->role);

        $this->assertSame(BusinessRole::Manager, $bob->memberships()->sole()->role);
        $this->assertSame(2, $business->members()->count());
    }

    public function test_inactive_memberships_grant_no_access(): void
    {
        $user = User::factory()->create();
        $suspended = BusinessMember::factory()->suspended()->create(['user_id' => $user->id]);
        $invited = BusinessMember::factory()->invited()->create(['user_id' => $user->id]);

        foreach ([$suspended, $invited] as $membership) {
            $this->assertFalse($this->memberships->hasActiveMembership($user, $membership->business));
            $this->assertNull($this->memberships->roleOf($user, $membership->business));
        }
        $this->assertSame(0, $user->businesses()->count());
    }

    public function test_another_tenants_business_cannot_be_resolved_by_its_public_id(): void
    {
        $alice = User::factory()->create();
        $mallory = User::factory()->create();
        $aliceBusiness = $this->memberships->createBusinessWithOwner($alice, 'Alice Salon');
        $this->memberships->createBusinessWithOwner($mallory, 'Mallory Repairs');

        $this->assertNull(
            $this->memberships->activeMembershipByBusinessPublicId($mallory, $aliceBusiness->public_id),
        );
        $this->assertNotNull(
            $this->memberships->activeMembershipByBusinessPublicId($alice, $aliceBusiness->public_id),
        );
    }

    public function test_accessible_by_scope_returns_only_the_users_businesses(): void
    {
        $alice = User::factory()->create();
        $aliceBusiness = $this->memberships->createBusinessWithOwner($alice, 'Alice Salon');
        $this->memberships->createBusinessWithOwner(User::factory()->create(), 'Other Clinic');
        BusinessMember::factory()->suspended()->create(['user_id' => $alice->id]);

        $this->assertSame(
            [$aliceBusiness->id],
            Business::query()->accessibleBy($alice)->pluck('id')->all(),
        );
    }

    public function test_slugs_are_unique_and_never_empty_for_non_latin_names(): void
    {
        $owner = User::factory()->create();

        $first = $this->memberships->createBusinessWithOwner($owner, 'عيادة الشفاء');
        $second = $this->memberships->createBusinessWithOwner($owner, 'عيادة الشفاء');

        $this->assertNotSame('', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_platform_admin_privilege_is_independent_of_membership(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = $this->memberships->createBusinessWithOwner(User::factory()->create(), 'Shifa Clinic');

        $this->assertFalse($this->memberships->hasActiveMembership($admin, $business));
        $this->assertNull($this->memberships->roleOf($admin, $business));
        $this->assertSame(MembershipStatus::Active, $business->members()->sole()->status);
    }
}
