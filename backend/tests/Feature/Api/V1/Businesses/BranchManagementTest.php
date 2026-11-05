<?php

namespace Tests\Feature\Api\V1\Businesses;

use App\Enums\BranchAccess;
use App\Enums\BranchStatus;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $mazzeh;

    private Branch $malki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic', 'timezone' => 'Asia/Damascus']);
        $this->mazzeh = $this->branchOf($this->business, ['name' => 'Mazzeh']);
        $this->malki = $this->branchOf($this->business, ['name' => 'Malki']);
    }

    private function branchesUri(?Business $business = null): string
    {
        return '/api/v1/businesses/'.($business ?? $this->business)->public_id.'/branches';
    }

    private function branchUri(Branch $branch): string
    {
        return '/api/v1/branches/'.$branch->public_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Abu Rummaneh',
            'address' => 'Al-Jalaa Street 5',
            'country' => 'SY',
            'city' => 'Damascus',
            'region' => 'Damascus Governorate',
            'latitude' => 33.5167,
            'longitude' => 36.2833,
            'phone' => '+963 11 333 4444',
            'email' => 'abu.rummaneh@shifa.example',
        ], $overrides);
    }

    // ----- Creating ------------------------------------------------------

    public function test_the_owner_creates_a_branch(): void
    {
        $response = $this->actingAsUser($this->owner, 'POST', $this->branchesUri(), $this->branchPayload());

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.name', 'Abu Rummaneh')
            ->assertJsonPath('data.business_id', $this->business->public_id)
            ->assertJsonPath('data.country', 'SY')
            ->assertJsonPath('data.latitude', 33.5167)
            ->assertJsonPath('data.longitude', 36.2833)
            ->assertJsonPath('data.phone', '+963113334444')
            ->assertJsonPath('data.timezone', 'Asia/Damascus')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.services_count', 0)
            ->assertJsonPath('data.permissions', ['update' => true, 'delete' => true, 'manage_services' => true]);

        $branch = Branch::query()->where('public_id', $response->json('data.id'))->sole();
        $this->assertTrue($branch->business->is($this->business));
    }

    public function test_a_manager_with_all_branch_access_creates_branches(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->actingAsUser($manager, 'POST', $this->branchesUri(), $this->branchPayload())->assertCreated();
    }

    public function test_branch_managers_employees_and_outsiders_cannot_create_branches(): void
    {
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $outsider = User::factory()->create();

        $this->assertApiError($this->actingAsUser($branchManager, 'POST', $this->branchesUri(), $this->branchPayload()), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->branchesUri(), $this->branchPayload()), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($outsider, 'POST', $this->branchesUri(), $this->branchPayload()), 404, 'business_not_found');
        $this->assertSame(2, $this->business->branches()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidBranches(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'latitude above 90' => [['latitude' => 90.0001], 'latitude'],
            'latitude below -90' => [['latitude' => -91], 'latitude'],
            'longitude above 180' => [['longitude' => 180.5], 'longitude'],
            'non-numeric latitude' => [['latitude' => 'north'], 'latitude'],
            'latitude without longitude' => [['longitude' => null], 'longitude'],
            'country name instead of code' => [['country' => 'Syria'], 'country'],
            'invalid timezone' => [['timezone' => 'Damascus'], 'timezone'],
            'invalid status' => [['status' => 'closed'], 'status'],
            'invalid email' => [['email' => 'branch@'], 'email'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidBranches')]
    public function test_invalid_branch_input_is_rejected(array $overrides, string $field): void
    {
        $response = $this->actingAsUser($this->owner, 'POST', $this->branchesUri(), $this->branchPayload($overrides));

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors($field);
    }

    public function test_a_branch_cannot_be_attached_to_another_business_through_input(): void
    {
        $other = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Other Clinic']);

        $response = $this->actingAsUser($this->owner, 'POST', $this->branchesUri(), $this->branchPayload([
            'business_id' => $other->id,
        ]));

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('business_id');
        $this->assertSame(0, $other->branches()->count());
    }

    // ----- Listing and viewing ------------------------------------------

    public function test_the_owner_lists_every_branch_with_service_counts(): void
    {
        $this->serviceOf($this->mazzeh);
        $this->serviceOf($this->mazzeh);

        $response = $this->actingAsUser($this->owner, 'GET', $this->branchesUri());

        $this->assertApiSuccess($response)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.name', 'Malki')
            ->assertJsonPath('data.1.name', 'Mazzeh')
            ->assertJsonPath('data.1.services_count', 2);
    }

    public function test_a_branch_manager_only_sees_assigned_branches(): void
    {
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);

        $response = $this->actingAsUser($branchManager, 'GET', $this->branchesUri());

        $this->assertSame(['Mazzeh'], $response->json('data.*.name'));
        $this->actingAsUser($branchManager, 'GET', $this->branchUri($this->mazzeh))->assertOk();
        $this->assertApiError($this->actingAsUser($branchManager, 'GET', $this->branchUri($this->malki)), 404, 'branch_not_found');
    }

    public function test_selected_access_without_branches_reaches_nothing(): void
    {
        $member = $this->memberOf($this->business, BusinessRole::Employee, BranchAccess::Selected, [$this->mazzeh]);
        $this->actingAsUser($this->owner, 'DELETE', $this->branchUri($this->mazzeh))->assertOk();

        $this->actingAsUser($member, 'GET', $this->branchesUri())->assertOk()->assertJsonPath('meta.pagination.total', 0);
        $this->assertApiError($this->actingAsUser($member, 'GET', $this->branchUri($this->malki)), 404, 'branch_not_found');
    }

    public function test_branches_can_be_filtered_by_status(): void
    {
        $this->malki->update(['status' => BranchStatus::Inactive]);

        $this->assertSame(['Malki'], $this->actingAsUser($this->owner, 'GET', $this->branchesUri().'?status=inactive')->json('data.*.name'));
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->branchesUri().'?status=closed'), 422, 'validation_failed');
    }

    public function test_another_tenants_branch_looks_exactly_like_a_missing_one(): void
    {
        $outsider = User::factory()->create();
        $this->businessOwnedBy($outsider, ['name' => 'Outsider Repairs']);

        $forbidden = $this->actingAsUser($outsider, 'GET', $this->branchUri($this->mazzeh));
        $missing = $this->actingAsUser($outsider, 'GET', '/api/v1/branches/01jzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertApiError($forbidden, 404, 'branch_not_found');
        $this->assertSame($missing->json(), $forbidden->json());
        $this->assertApiError($this->actingAsUser($outsider, 'GET', $this->branchesUri()), 404, 'business_not_found');
    }

    public function test_an_employee_views_branches_read_only(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->actingAsUser($employee, 'GET', $this->branchUri($this->mazzeh))->assertOk()
            ->assertJsonPath('data.permissions', ['update' => false, 'delete' => false, 'manage_services' => false]);
    }

    // ----- Updating ------------------------------------------------------

    public function test_the_owner_updates_and_deactivates_a_branch(): void
    {
        $response = $this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), [
            'name' => 'Mazzeh Main',
            'status' => 'inactive',
            'latitude' => -33.8688,
            'longitude' => 151.2093,
        ]);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.name', 'Mazzeh Main')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.latitude', -33.8688);

        $this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_a_branch_manager_updates_only_assigned_branches(): void
    {
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);

        $this->actingAsUser($branchManager, 'PUT', $this->branchUri($this->mazzeh), ['name' => 'Mazzeh Renamed'])->assertOk();
        $this->assertApiError($this->actingAsUser($branchManager, 'PUT', $this->branchUri($this->malki), ['name' => 'Hijacked']), 404, 'branch_not_found');
        $this->assertSame('Malki', $this->malki->fresh()->name);
    }

    public function test_employees_cannot_change_branch_settings(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($employee, 'PUT', $this->branchUri($this->mazzeh), ['status' => 'inactive']), 403, 'forbidden');
        $this->assertSame(BranchStatus::Active, $this->mazzeh->fresh()->status);
    }

    public function test_updating_one_coordinate_alone_is_rejected(): void
    {
        $response = $this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['latitude' => 10]);

        $this->assertApiError($response, 422, 'validation_failed');
    }

    public function test_coordinates_can_be_cleared_together(): void
    {
        $this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['latitude' => null, 'longitude' => null])
            ->assertOk()->assertJsonPath('data.latitude', null);
    }

    public function test_a_branch_cannot_be_moved_to_another_business(): void
    {
        $other = $this->businessOwnedBy($this->owner, ['name' => 'Second Clinic']);

        $response = $this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['business_id' => $other->id]);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('business_id');
        $this->assertTrue($this->mazzeh->fresh()->business->is($this->business));
    }

    public function test_an_empty_update_is_rejected(): void
    {
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), []), 422, 'validation_failed')
            ->assertJsonValidationErrors('branch');
    }

    public function test_branches_of_a_suspended_business_are_read_only(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->actingAsUser($this->owner, 'GET', $this->branchUri($this->mazzeh))->assertOk();
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['name' => 'X1']), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->branchesUri(), $this->branchPayload()), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $this->branchUri($this->mazzeh)), 403, 'business_suspended');
    }

    // ----- Deleting ------------------------------------------------------

    public function test_deleting_a_branch_archives_it_and_its_services(): void
    {
        $service = $this->serviceOf($this->mazzeh);

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $this->branchUri($this->mazzeh)));

        $this->assertSoftDeleted($this->mazzeh);
        $this->assertSoftDeleted($service);
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->branchUri($this->mazzeh)), 404, 'branch_not_found');
        $this->assertSame(['Malki'], $this->actingAsUser($this->owner, 'GET', $this->branchesUri())->json('data.*.name'));
    }

    public function test_branch_managers_cannot_delete_even_their_own_branch(): void
    {
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->assertApiError($this->actingAsUser($branchManager, 'DELETE', $this->branchUri($this->mazzeh)), 403, 'forbidden');
        $this->actingAsUser($manager, 'DELETE', $this->branchUri($this->mazzeh))->assertOk();
    }

    public function test_branches_of_a_deleted_business_are_unreachable(): void
    {
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/businesses/'.$this->business->public_id)->assertOk();

        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->branchUri($this->mazzeh)), 404, 'branch_not_found');
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->branchUri($this->mazzeh), ['name' => 'X1']), 404, 'branch_not_found');
        $this->assertSame(0, Service::query()->count());
    }
}
