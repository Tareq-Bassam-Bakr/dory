<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueStaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class QueueStaffTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $branch;

    private Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
        $this->branch = $this->branchOf($this->business);
        $this->queue = $this->queueFor($this->serviceOf($this->branch));
    }

    private function staffUri(?Queue $queue = null): string
    {
        return $this->queueUri($queue ?? $this->queue, 'staff');
    }

    private function memberId(User $user): string
    {
        return $this->membershipOf($user, $this->business)->public_id;
    }

    public function test_a_manager_assigns_an_employee_who_can_then_operate_the_queue(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->queueUri($this->queue, 'open')), 403, 'forbidden');

        $response = $this->actingAsUser($manager, 'POST', $this->staffUri(), ['member' => $this->memberId($employee)]);

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.member.id', $this->memberId($employee))
            ->assertJsonPath('data.member.name', $employee->name)
            ->assertJsonPath('data.member.role', 'employee')
            ->assertJsonPath('data.assigned_by', $manager->name)
            ->assertJsonPath('data.active', true);
        $this->actingAsUser($employee, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();
        $this->actingAsUser($employee, 'GET', $this->queueUri($this->queue))
            ->assertJsonPath('data.permissions', ['manage' => false, 'operate' => true]);
    }

    public function test_assigning_twice_returns_the_existing_assignment(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $first = $this->actingAsUser($this->owner, 'POST', $this->staffUri(), ['member' => $this->memberId($employee)]);
        $second = $this->actingAsUser($this->owner, 'POST', $this->staffUri(), ['member' => $this->memberId($employee)]);

        $first->assertCreated();
        $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, QueueStaffAssignment::query()->count());
    }

    public function test_assignments_are_listed_and_removed_keeping_history(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $assignment = $this->assignToQueue($this->queue, $employee, $this->owner);

        $this->actingAsUser($this->owner, 'GET', $this->staffUri())->assertOk()->assertJsonCount(1, 'data');
        $removed = $this->actingAsUser($this->owner, 'DELETE', $this->staffUri().'/'.$assignment->public_id);

        $this->assertApiSuccess($removed);
        $assignment->refresh();
        $this->assertNotNull($assignment->unassigned_at);
        $this->assertSame($this->owner->getKey(), $assignment->unassigned_by);
        $this->actingAsUser($this->owner, 'GET', $this->staffUri())->assertOk()->assertJsonCount(0, 'data');
        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->queueUri($this->queue, 'open')), 403, 'forbidden');

        // Assigning again creates a new active assignment.
        $this->actingAsUser($this->owner, 'POST', $this->staffUri(), ['member' => $this->memberId($employee)])->assertCreated();
        $this->assertSame(2, QueueStaffAssignment::query()->count());
    }

    public function test_only_active_managers_and_employees_of_the_branch_are_eligible(): void
    {
        $otherBranch = $this->branchOf($this->business);
        $elsewhere = $this->memberOf($this->business, BusinessRole::Employee, BranchAccess::Selected, [$otherBranch]);
        $suspended = $this->memberOf($this->business, BusinessRole::Employee);
        $this->membershipOf($suspended, $this->business)->forceFill(['status' => MembershipStatus::Suspended])->save();
        $foreignOwner = User::factory()->create();
        $foreign = $this->businessOwnedBy($foreignOwner);

        $candidates = [
            $this->memberId($this->owner),
            $this->memberId($elsewhere),
            $this->memberId($suspended),
            $this->membershipOf($foreignOwner, $foreign)->public_id,
            str_repeat('0', 26),
        ];

        foreach ($candidates as $member) {
            $response = $this->actingAsUser($this->owner, 'POST', $this->staffUri(), ['member' => $member]);
            $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('member');
        }
        $this->assertSame(0, QueueStaffAssignment::query()->count());
    }

    public function test_employees_cannot_manage_assignments(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $assignment = $this->assignToQueue($this->queue, $employee, $this->owner);

        $this->assertApiError(
            $this->actingAsUser($employee, 'POST', $this->staffUri(), ['member' => $this->memberId($employee)]),
            403,
            'forbidden',
        );
        $this->assertApiError($this->actingAsUser($employee, 'DELETE', $this->staffUri().'/'.$assignment->public_id), 403, 'forbidden');
        $this->actingAsUser($employee, 'GET', $this->staffUri())->assertOk();
    }

    public function test_an_assignment_is_only_reachable_through_its_own_queue(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $assignment = $this->assignToQueue($this->queue, $employee, $this->owner);
        $other = $this->queueFor($this->serviceOf($this->branch));

        $response = $this->actingAsUser($this->owner, 'DELETE', $this->staffUri($other).'/'.$assignment->public_id);

        $this->assertApiError($response, 404, 'assignment_not_found');
        $this->assertTrue($assignment->fresh()->isActive());
    }

    public function test_other_tenants_cannot_see_the_queue_staff(): void
    {
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);

        $this->assertApiError($this->actingAsUser($stranger, 'GET', $this->staffUri()), 404, 'queue_not_found');
    }
}
