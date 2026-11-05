<?php

namespace Tests\Feature\Api\V1\Businesses;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\ServiceStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
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
        $this->business = $this->businessOwnedBy($this->owner);
        $this->mazzeh = $this->branchOf($this->business, ['name' => 'Mazzeh']);
        $this->malki = $this->branchOf($this->business, ['name' => 'Malki']);
    }

    private function servicesUri(Branch $branch): string
    {
        return '/api/v1/branches/'.$branch->public_id.'/services';
    }

    private function serviceUri(Service $service): string
    {
        return '/api/v1/services/'.$service->public_id;
    }

    // ----- Creating ------------------------------------------------------

    public function test_the_owner_creates_services_with_sensible_defaults(): void
    {
        $first = $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), [
            'name' => 'General consultation',
            'description' => 'Walk-in visit with a general practitioner',
            'estimated_duration_minutes' => 20,
        ]);
        $second = $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Vaccination']);

        $this->assertApiSuccess($first, 201)
            ->assertJsonPath('data.name', 'General consultation')
            ->assertJsonPath('data.branch_id', $this->mazzeh->public_id)
            ->assertJsonPath('data.estimated_duration_minutes', 20)
            ->assertJsonPath('data.status', 'active');
        $second->assertCreated()
            ->assertJsonPath('data.estimated_duration_minutes', 15)
            ->assertJsonPath('data.display_order', $first->json('data.display_order') + 1);

        $this->assertSame(2, $this->mazzeh->services()->count());
    }

    public function test_the_same_service_name_is_allowed_in_different_branches(): void
    {
        $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Haircut'])->assertCreated();
        $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->malki), ['name' => 'Haircut'])->assertCreated();
    }

    public function test_a_service_name_is_unique_within_its_branch_ignoring_case(): void
    {
        $this->serviceOf($this->mazzeh, ['name' => 'Haircut']);

        $response = $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'HAIRCUT']);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('name');
    }

    public function test_an_archived_services_name_can_be_reused(): void
    {
        $this->serviceOf($this->mazzeh, ['name' => 'Haircut'])->delete();

        $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Haircut'])->assertCreated();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidServices(): array
    {
        return [
            'missing name' => [[], 'name'],
            'one-letter name' => [['name' => 'X'], 'name'],
            'zero duration' => [['name' => 'Cut', 'estimated_duration_minutes' => 0], 'estimated_duration_minutes'],
            'duration over a day' => [['name' => 'Cut', 'estimated_duration_minutes' => 1441], 'estimated_duration_minutes'],
            'fractional duration' => [['name' => 'Cut', 'estimated_duration_minutes' => 12.5], 'estimated_duration_minutes'],
            'negative order' => [['name' => 'Cut', 'display_order' => -1], 'display_order'],
            'invalid status' => [['name' => 'Cut', 'status' => 'archived'], 'status'],
            'branch id' => [['name' => 'Cut', 'branch_id' => 1], 'branch_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidServices')]
    public function test_invalid_service_input_is_rejected(array $payload, string $field): void
    {
        $response = $this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), $payload);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors($field);
        $this->assertSame(0, Service::query()->count());
    }

    public function test_a_branch_manager_manages_services_only_in_assigned_branches(): void
    {
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);

        $this->actingAsUser($branchManager, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Cleaning'])->assertCreated();
        $this->assertApiError(
            $this->actingAsUser($branchManager, 'POST', $this->servicesUri($this->malki), ['name' => 'Cleaning']),
            404,
            'branch_not_found',
        );
        $this->assertSame(0, $this->malki->services()->count());
    }

    public function test_employees_and_outsiders_cannot_create_services(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $outsider = User::factory()->create();

        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Cut']), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($outsider, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'Cut']), 404, 'branch_not_found');
        $this->assertSame(0, Service::query()->count());
    }

    // ----- Listing and viewing ------------------------------------------

    public function test_services_are_listed_in_display_order_and_scoped_to_the_branch(): void
    {
        $this->serviceOf($this->mazzeh, ['name' => 'Second', 'display_order' => 2]);
        $this->serviceOf($this->mazzeh, ['name' => 'First', 'display_order' => 1]);
        $this->serviceOf($this->mazzeh, ['name' => 'Paused', 'display_order' => 3, 'status' => ServiceStatus::Inactive]);
        $this->serviceOf($this->malki, ['name' => 'Elsewhere', 'display_order' => 0]);

        $response = $this->actingAsUser($this->owner, 'GET', $this->servicesUri($this->mazzeh));

        $this->assertApiSuccess($response)->assertJsonPath('meta.pagination.total', 3);
        $this->assertSame(['First', 'Second', 'Paused'], $response->json('data.*.name'));
        $this->assertSame(
            ['Paused'],
            $this->actingAsUser($this->owner, 'GET', $this->servicesUri($this->mazzeh).'?status=inactive')->json('data.*.name'),
        );
    }

    public function test_an_employee_can_read_services(): void
    {
        $service = $this->serviceOf($this->mazzeh);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->actingAsUser($employee, 'GET', $this->servicesUri($this->mazzeh))->assertOk();
        $this->actingAsUser($employee, 'GET', $this->serviceUri($service))->assertOk()
            ->assertJsonPath('data.id', $service->public_id)
            ->assertJsonPath('data.branch_id', $this->mazzeh->public_id);
    }

    public function test_another_tenants_service_looks_exactly_like_a_missing_one(): void
    {
        $service = $this->serviceOf($this->mazzeh);
        $outsider = User::factory()->create();
        $this->businessOwnedBy($outsider, ['name' => 'Outsider Bank']);

        $forbidden = $this->actingAsUser($outsider, 'GET', $this->serviceUri($service));
        $missing = $this->actingAsUser($outsider, 'GET', '/api/v1/services/01jzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertApiError($forbidden, 404, 'service_not_found');
        $this->assertSame($missing->json(), $forbidden->json());
        $this->assertApiError($this->actingAsUser($outsider, 'GET', $this->servicesUri($this->mazzeh)), 404, 'branch_not_found');
    }

    public function test_services_of_unassigned_branches_are_hidden_from_branch_managers(): void
    {
        $hidden = $this->serviceOf($this->malki);
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);

        $this->assertApiError($this->actingAsUser($branchManager, 'GET', $this->serviceUri($hidden)), 404, 'service_not_found');
        $this->assertApiError($this->actingAsUser($branchManager, 'PUT', $this->serviceUri($hidden), ['name' => 'Hijacked']), 404, 'service_not_found');
        $this->assertApiError($this->actingAsUser($branchManager, 'DELETE', $this->serviceUri($hidden)), 404, 'service_not_found');
        $this->assertNotSoftDeleted($hidden);
    }

    // ----- Updating ------------------------------------------------------

    public function test_the_owner_edits_deactivates_and_reactivates_a_service(): void
    {
        $service = $this->serviceOf($this->mazzeh, ['name' => 'Haircut']);

        $this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), [
            'name' => 'Haircut and styling',
            'estimated_duration_minutes' => 45,
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Haircut and styling')
            ->assertJsonPath('data.estimated_duration_minutes', 45)
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_keeping_the_same_name_is_not_a_duplicate(): void
    {
        $service = $this->serviceOf($this->mazzeh, ['name' => 'Haircut']);

        $this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), ['name' => 'Haircut', 'display_order' => 9])->assertOk();
    }

    public function test_renaming_to_another_services_name_is_rejected(): void
    {
        $this->serviceOf($this->mazzeh, ['name' => 'Haircut']);
        $beard = $this->serviceOf($this->mazzeh, ['name' => 'Beard trim']);

        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->serviceUri($beard), ['name' => 'haircut']), 422, 'validation_failed')
            ->assertJsonValidationErrors('name');
    }

    public function test_a_service_cannot_be_moved_to_another_branch(): void
    {
        $service = $this->serviceOf($this->mazzeh);

        $response = $this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), ['branch_id' => $this->malki->id]);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('branch_id');
        $this->assertTrue($service->fresh()->branch->is($this->mazzeh));
    }

    public function test_employees_cannot_edit_services(): void
    {
        $service = $this->serviceOf($this->mazzeh, ['name' => 'Haircut']);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($employee, 'PUT', $this->serviceUri($service), ['name' => 'Free']), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'DELETE', $this->serviceUri($service)), 403, 'forbidden');
        $this->assertSame('Haircut', $service->fresh()->name);
    }

    public function test_an_empty_update_is_rejected(): void
    {
        $service = $this->serviceOf($this->mazzeh);

        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), []), 422, 'validation_failed')
            ->assertJsonValidationErrors('service');
    }

    // ----- Archiving -----------------------------------------------------

    public function test_deleting_a_service_archives_it(): void
    {
        $service = $this->serviceOf($this->mazzeh);

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $this->serviceUri($service)));

        $this->assertSoftDeleted($service);
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->serviceUri($service)), 404, 'service_not_found');
        $this->actingAsUser($this->owner, 'GET', $this->servicesUri($this->mazzeh))->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_services_of_a_suspended_business_are_read_only(): void
    {
        $service = $this->serviceOf($this->mazzeh);
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->actingAsUser($this->owner, 'GET', $this->serviceUri($service))->assertOk();
        $this->assertApiError($this->actingAsUser($this->owner, 'PUT', $this->serviceUri($service), ['name' => 'New name']), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->servicesUri($this->mazzeh), ['name' => 'New']), 403, 'business_suspended');
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $this->serviceUri($service)), 403, 'business_suspended');
    }

    public function test_services_of_a_deleted_branch_are_unreachable(): void
    {
        $service = $this->serviceOf($this->mazzeh);
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/branches/'.$this->mazzeh->public_id)->assertOk();

        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->serviceUri($service)), 404, 'service_not_found');
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->servicesUri($this->mazzeh)), 404, 'branch_not_found');
    }
}
