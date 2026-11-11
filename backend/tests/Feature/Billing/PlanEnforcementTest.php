<?php

namespace Tests\Feature\Billing;

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessInvitation;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Plan limits and features, enforced by the server (docs/SUBSCRIPTIONS.md,
 * "Entitlements"). Limits stop adding, never running: a business over its
 * limit keeps every branch, queue and member working, and customers are
 * never affected by any plan.
 */
class PlanEnforcementTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
    }

    private function freeBusiness(): Business
    {
        config(['billing.trial.days' => 0]);
        $business = $this->businessOwnedBy($this->owner);
        config(['billing.trial.days' => 14]);

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(string $name): array
    {
        return [
            'name' => $name,
            'address' => 'Al-Jalaa Street 5',
            'country' => 'SY',
            'city' => 'Damascus',
            'latitude' => 33.5167,
            'longitude' => 36.2833,
        ];
    }

    private function addBranch(Business $business, string $name): TestResponse
    {
        return $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses/'.$business->public_id.'/branches', $this->branchPayload($name));
    }

    private function addQueue(Branch $branch, string $prefix): TestResponse
    {
        $service = $this->serviceOf($branch, ['name' => 'Service '.$prefix]);

        return $this->actingAsUser($this->owner, 'POST', '/api/v1/branches/'.$branch->public_id.'/queues', [
            'service' => $service->public_id,
            'name' => 'Queue '.$prefix,
            'ticket_prefix' => $prefix,
        ]);
    }

    private function invite(Business $business, string $email): TestResponse
    {
        return $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses/'.$business->public_id.'/invitations', [
            'email' => $email,
            'role' => 'employee',
        ]);
    }

    private function assertLimit(TestResponse $response, string $limit, int $max, int $used, string $plan): void
    {
        $this->assertApiError($response, 403, 'plan_limit_reached')
            ->assertJsonPath('details.limit', $limit)
            ->assertJsonPath('details.max', $max)
            ->assertJsonPath('details.used', $used)
            ->assertJsonPath('details.plan', $plan);
    }

    // ----- Limits ------------------------------------------------------------

    public function test_the_free_plan_has_one_branch(): void
    {
        $business = $this->freeBusiness();

        $this->addBranch($business, 'Mazzeh')->assertCreated();
        $this->assertLimit($this->addBranch($business, 'Malki'), 'branches', 1, 1, 'free');
        $this->assertSame(1, Branch::query()->where('business_id', $business->getKey())->count());
    }

    public function test_the_pro_trial_has_three_branches(): void
    {
        $business = $this->businessOwnedBy($this->owner);

        foreach (['Mazzeh', 'Malki', 'Kafr Souseh'] as $name) {
            $this->addBranch($business, $name)->assertCreated();
        }
        $this->assertLimit($this->addBranch($business, 'Jaramana'), 'branches', 3, 3, 'pro');
    }

    public function test_a_deleted_branch_frees_its_place(): void
    {
        $business = $this->freeBusiness();
        $branch = $this->branchOf($business);

        $this->assertLimit($this->addBranch($business, 'Malki'), 'branches', 1, 1, 'free');
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/branches/'.$branch->public_id)->assertOk();
        $this->addBranch($business, 'Malki')->assertCreated();
    }

    public function test_the_first_branch_of_a_new_free_business_is_created_with_it(): void
    {
        config(['billing.trial.days' => 0]);

        $response = $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses', [
            'name' => 'Shifa Clinic',
            'category' => BusinessCategory::factory()->create()->slug,
            'timezone' => 'Asia/Damascus',
            'branch' => $this->branchPayload('Mazzeh'),
        ]);

        $response->assertCreated();
        $business = Business::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame(1, Branch::query()->where('business_id', $business->getKey())->count());
    }

    public function test_the_free_plan_has_three_queues(): void
    {
        $business = $this->freeBusiness();
        $branch = $this->branchOf($business);

        foreach (['A', 'B', 'C'] as $prefix) {
            $this->addQueue($branch, $prefix)->assertCreated();
        }
        $this->assertLimit($this->addQueue($branch, 'D'), 'queues', 3, 3, 'free');
    }

    public function test_pending_invitations_hold_team_seats(): void
    {
        $business = $this->freeBusiness();

        // The owner is one of the three seats.
        $this->invite($business, 'sara@example.com')->assertCreated();
        $this->invite($business, 'omar@example.com')->assertCreated();
        $this->assertLimit($this->invite($business, 'lina@example.com'), 'members', 3, 3, 'free');
    }

    public function test_accepting_needs_a_free_seat(): void
    {
        $business = $this->freeBusiness();
        $this->memberOf($business, BusinessRole::Employee);
        $this->memberOf($business, BusinessRole::Employee);
        $invitee = User::factory()->create(['email' => 'sara@example.com']);
        $token = Str::random(64);
        BusinessInvitation::factory()->for($business)->withToken($token)
            ->create(['email' => 'sara@example.com', 'role' => BusinessRole::Employee, 'invited_by' => $this->owner->getKey()]);

        $this->assertLimit($this->actingAsUser($invitee, 'POST', '/api/v1/invitations/'.$token.'/accept'), 'members', 3, 3, 'free');
        $this->assertNull($this->membershipOf($invitee, $business));
        $this->assertSame(InvitationStatus::Pending, BusinessInvitation::query()->where('email', 'sara@example.com')->value('status'));
    }

    public function test_the_business_plan_has_no_limits(): void
    {
        $business = $this->onPlan($this->businessOwnedBy($this->owner), 'business');

        foreach (range(1, 4) as $i) {
            $this->addBranch($business, 'Branch '.$i)->assertCreated();
        }
    }

    public function test_with_billing_switched_off_nothing_is_limited(): void
    {
        $business = $this->freeBusiness();
        config(['billing.enabled' => false]);

        $this->addBranch($business, 'Mazzeh')->assertCreated();
        $this->addBranch($business, 'Malki')->assertCreated();
    }

    // ----- Over a limit: everything keeps working ---------------------------------

    public function test_a_business_over_its_limit_keeps_operating_and_serving_customers(): void
    {
        // Built during the Pro trial: three branches, four queues.
        [$business, $branch] = $this->publicBusiness('Shifa Clinic');
        $this->branchOf($business, ['name' => 'Malki']);
        $this->branchOf($business, ['name' => 'Kafr Souseh']);
        $queues = [];
        foreach (['A', 'B', 'C', 'D'] as $prefix) {
            $queues[] = $this->queueFor($this->serviceOf($branch, ['name' => 'Service '.$prefix]), ['ticket_prefix' => $prefix]);
        }
        $owner = $business->members()->where('role', BusinessRole::Owner->value)->firstOrFail()->user;

        // The trial ends: the free plan allows one branch and three queues.
        $this->travel(15)->days();
        $subscription = $this->actingAsUser($owner, 'GET', '/api/v1/businesses/'.$business->public_id.'/subscription');
        $subscription->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.usage.branches', 3)
            ->assertJsonPath('data.usage.queues', 4)
            ->assertJsonPath('data.limits.branches', 1);

        // Running the existing queues is never limited.
        /** @var Queue $queue */
        $queue = $queues[3];
        $this->actingAsUser($owner, 'POST', $this->queueUri($queue, 'open'))->assertCreated();
        $this->actingAsUser($owner, 'PUT', $this->queueUri($queue), ['name' => 'Renamed'])->assertOk();
        $this->actingAsUser($owner, 'PUT', '/api/v1/branches/'.$branch->public_id, ['name' => 'Main branch'])->assertOk();

        // Customers: discovery, signed-in and guest joining.
        $this->getJson($this->discovery('businesses'))->assertOk()->assertJsonPath('data.0.name', 'Shifa Clinic');
        $customer = User::factory()->create();
        $this->actingAsUser($customer, 'POST', $this->queueUri($queue, 'join'))->assertCreated();
        $this->forgetAuthenticatedUser();
        $this->postJson('/api/v1/public/queues/'.$queue->public_token_encrypted.'/join', ['name' => 'Rami'])->assertCreated();

        // Staff serve them.
        $session = $queue->sessions()->latest('id')->firstOrFail();
        $this->actingAsUser($owner, 'POST', '/api/v1/queue-sessions/'.$session->public_id.'/call-next')->assertOk();

        // Only adding more is refused.
        $this->assertApiError(
            $this->actingAsUser($owner, 'POST', '/api/v1/businesses/'.$business->public_id.'/branches', $this->branchPayload('Jaramana')),
            403,
            'plan_limit_reached',
        );
    }

    // ----- Features ------------------------------------------------------------

    public function test_insights_need_a_plan_with_insights(): void
    {
        $free = $this->freeBusiness();
        $uri = '/api/v1/businesses/'.$free->public_id.'/insights';

        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $uri), 403, 'feature_not_in_plan')
            ->assertJsonPath('details.feature', 'insights')
            ->assertJsonPath('details.plan', 'free');
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$free->public_id.'/peak-times'), 403, 'feature_not_in_plan');

        $this->paidPlan($free, 'pro');
        $this->actingAsUser($this->owner, 'GET', $uri)->assertOk();
    }

    public function test_one_branch_on_its_own_needs_the_business_plan(): void
    {
        $business = $this->businessOwnedBy($this->owner);
        $branch = $this->branchOf($business);
        $uri = '/api/v1/businesses/'.$business->public_id.'/insights?branch='.$branch->public_id;

        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $uri), 403, 'feature_not_in_plan')
            ->assertJsonPath('details.feature', 'branch_insights')
            ->assertJsonPath('details.plan', 'pro');

        $this->onPlan($business, 'business');
        $this->actingAsUser($this->owner, 'GET', $uri)->assertOk()->assertJsonPath('data.branches', [$branch->public_id]);
    }

    public function test_plan_checks_never_reveal_another_business(): void
    {
        $other = $this->freeBusiness();
        $stranger = User::factory()->create();

        $this->assertApiError($this->actingAsUser($stranger, 'GET', '/api/v1/businesses/'.$other->public_id.'/insights'), 404, 'business_not_found');
        $this->assertApiError(
            $this->actingAsUser($stranger, 'POST', '/api/v1/businesses/'.$other->public_id.'/branches', $this->branchPayload('X')),
            404,
            'business_not_found',
        );
    }
}
