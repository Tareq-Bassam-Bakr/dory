<?php

namespace Tests\Feature\Api\V1\Businesses;

use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\InvitationStatus;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\BusinessCategory;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic']);
    }

    private function uri(?Business $business = null): string
    {
        return '/api/v1/businesses/'.($business ?? $this->business)->public_id;
    }

    // ----- Listing -------------------------------------------------------

    public function test_the_manage_list_contains_only_the_users_active_memberships(): void
    {
        $employer = $this->businessOwnedBy(User::factory()->create(), ['name' => 'City Bank']);
        app(BusinessMembershipService::class)
            ->addMember($employer, $this->owner, BusinessRole::Employee);
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'Unrelated Salon']);
        BusinessMember::factory()->suspended()->create(['user_id' => $this->owner->id]);

        $response = $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/manage');

        $this->assertApiSuccess($response)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.name', 'City Bank')
            ->assertJsonPath('data.0.membership.role', 'employee')
            ->assertJsonPath('data.0.permissions.update', false)
            ->assertJsonPath('data.1.name', 'Shifa Clinic')
            ->assertJsonPath('data.1.membership.role', 'owner')
            ->assertJsonPath('data.1.permissions.update', true);
    }

    public function test_the_manage_list_is_paginated(): void
    {
        foreach (range(1, 3) as $i) {
            $this->businessOwnedBy($this->owner, ['name' => "Branch Co {$i}"]);
        }

        $response = $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/manage?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.pagination', ['current_page' => 2, 'per_page' => 2, 'total' => 4, 'last_page' => 2])
            ->assertJsonCount(2, 'data');
    }

    public function test_the_manage_list_requires_authentication(): void
    {
        $this->assertApiError($this->getJson('/api/v1/businesses/manage'), 401, 'unauthenticated');
    }

    // ----- Viewing -------------------------------------------------------

    public function test_a_member_views_the_business(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiSuccess($this->actingAsUser($employee, 'GET', $this->uri()))
            ->assertJsonPath('data.id', $this->business->public_id)
            ->assertJsonPath('data.membership.role', 'employee')
            ->assertJsonPath('data.permissions.view_members', false);
    }

    public function test_an_outsider_gets_the_same_404_as_for_a_missing_business(): void
    {
        $outsider = User::factory()->create();
        $this->businessOwnedBy($outsider, ['name' => 'Outsider Repairs']);

        $forbidden = $this->actingAsUser($outsider, 'GET', $this->uri());
        $missing = $this->actingAsUser($outsider, 'GET', '/api/v1/businesses/01jzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertApiError($forbidden, 404, 'business_not_found');
        $this->assertApiError($missing, 404, 'business_not_found');
        $this->assertSame($missing->json(), $forbidden->json());
    }

    public function test_a_suspended_member_cannot_view_the_business(): void
    {
        $member = User::factory()->create();
        BusinessMember::factory()->manager()->suspended()->create([
            'business_id' => $this->business->id,
            'user_id' => $member->id,
        ]);

        $this->assertApiError($this->actingAsUser($member, 'GET', $this->uri()), 404, 'business_not_found');
    }

    // ----- Updating ------------------------------------------------------

    public function test_the_owner_updates_the_profile(): void
    {
        $category = BusinessCategory::factory()->create(['slug' => 'beauty-salons']);

        $response = $this->actingAsUser($this->owner, 'PUT', $this->uri(), [
            'name' => 'Shifa Medical Center',
            'category' => 'beauty-salons',
            'website' => 'https://shifa.example',
            'phone' => '+963-944-123-456',
        ]);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.name', 'Shifa Medical Center')
            ->assertJsonPath('data.category.slug', 'beauty-salons')
            ->assertJsonPath('data.phone', '+963944123456');
        $this->assertTrue($this->business->fresh()->category->is($category));

        $entry = BusinessAuditLog::query()->where('action', AuditAction::BusinessUpdated->value)->sole();
        $this->assertEqualsCanonicalizing(['name', 'category_id', 'website', 'phone'], $entry->properties['fields']);
    }

    public function test_a_manager_with_all_branch_access_updates_the_profile(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->actingAsUser($manager, 'PUT', $this->uri(), ['description' => 'Open daily'])->assertOk();

        $this->assertSame('Open daily', $this->business->fresh()->description);
    }

    public function test_employees_and_branch_managers_cannot_update_the_profile(): void
    {
        $branch = $this->branchOf($this->business);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$branch]);

        $this->assertApiError($this->actingAsUser($employee, 'PUT', $this->uri(), ['name' => 'Hacked']), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($branchManager, 'PUT', $this->uri(), ['name' => 'Hacked']), 403, 'forbidden');
        $this->assertSame('Shifa Clinic', $this->business->fresh()->name);
    }

    public function test_an_outsider_cannot_update_and_learns_nothing(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAsUser($outsider, 'PUT', $this->uri(), ['name' => 'Hacked']);

        $this->assertApiError($response, 404, 'business_not_found');
        $this->assertSame('Shifa Clinic', $this->business->fresh()->name);
    }

    public function test_an_empty_update_is_rejected(): void
    {
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->uri(), []), 422, 'validation_failed')
            ->assertJsonValidationErrors('business');
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function protectedFields(): array
    {
        return [
            'slug' => ['slug', 'taken-over'],
            'creator' => ['created_by', 999],
            'owner' => ['owner_id', 999],
            'category id' => ['category_id', 1],
            'cover path' => ['cover_path', '/etc/passwd'],
        ];
    }

    #[DataProvider('protectedFields')]
    public function test_identity_and_ownership_fields_cannot_be_updated(string $field, mixed $value): void
    {
        $response = $this->actingAsUser($this->owner, 'PUT', $this->uri(), [$field => $value]);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors($field);
    }

    // ----- Status --------------------------------------------------------

    public function test_the_owner_deactivates_and_reactivates_the_business(): void
    {
        $this->actingAsUser($this->owner, 'PUT', $this->uri(), ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->actingAsUser($this->owner, 'PUT', $this->uri(), ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');

        $changes = BusinessAuditLog::query()->where('action', AuditAction::BusinessStatusChanged->value)->orderBy('id')->get();
        $this->assertSame([['from' => 'active', 'to' => 'inactive'], ['from' => 'inactive', 'to' => 'active']], $changes->pluck('properties')->all());
    }

    public function test_a_manager_cannot_change_the_status(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->assertApiError($this->actingAsUser($manager, 'PUT', $this->uri(), ['status' => 'inactive']), 403, 'forbidden');
        $this->assertSame(BusinessStatus::Active, $this->business->fresh()->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function platformStatuses(): array
    {
        return ['suspended' => ['suspended'], 'archived' => ['archived'], 'unknown' => ['closed']];
    }

    #[DataProvider('platformStatuses')]
    public function test_the_owner_cannot_set_platform_statuses(string $status): void
    {
        $response = $this->actingAsUser($this->owner, 'PUT', $this->uri(), ['status' => $status]);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('status');
    }

    public function test_a_suspended_business_is_read_only_for_everyone(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->actingAsUser($this->owner, 'GET', $this->uri())->assertOk()
            ->assertJsonPath('data.permissions.update', false);
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->uri(), ['name' => 'Renamed']), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->uri(), ['status' => 'active']), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $this->uri()), 403, 'business_suspended');
    }

    // ----- Deleting ------------------------------------------------------

    public function test_the_owner_deletes_the_business_and_everyone_loses_access(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $invitation = BusinessInvitation::factory()->for($this->business)->create();

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $this->uri()));

        $archived = Business::withTrashed()->find($this->business->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame(BusinessStatus::Archived, $archived->status);
        $this->assertSame(InvitationStatus::Revoked, $invitation->fresh()->status);
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->uri()), 404, 'business_not_found');
        $this->assertApiError($this->actingAsUser($employee, 'GET', $this->uri()), 404, 'business_not_found');
        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/manage')->assertJsonPath('meta.pagination.total', 0);
        $this->assertSame(1, BusinessAuditLog::query()->where('action', AuditAction::BusinessDeleted->value)->count());
    }

    public function test_only_the_owner_can_delete_the_business(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($manager, 'DELETE', $this->uri()), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'DELETE', $this->uri()), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser(User::factory()->create(), 'DELETE', $this->uri()), 404, 'business_not_found');
        $this->assertFalse($this->business->fresh()->trashed());
    }

    public function test_a_suspended_account_is_rejected_before_any_tenant_lookup(): void
    {
        $this->owner->forceFill(['status' => 'suspended'])->save();

        $response = $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/01jzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertApiError($response, 403, 'account_inactive');
    }
}
