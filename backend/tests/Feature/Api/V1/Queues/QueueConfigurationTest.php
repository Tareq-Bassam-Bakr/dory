<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class QueueConfigurationTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $mazzeh;

    private Branch $malki;

    private Service $consultation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
        $this->mazzeh = $this->branchOf($this->business, ['name' => 'Mazzeh']);
        $this->malki = $this->branchOf($this->business, ['name' => 'Malki']);
        $this->consultation = $this->serviceOf($this->mazzeh, ['name' => 'Consultation', 'estimated_duration_minutes' => 20]);
    }

    private function queuesUri(Branch $branch): string
    {
        return '/api/v1/branches/'.$branch->public_id.'/queues';
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'service' => $this->consultation->public_id,
            'name' => 'Consultation queue',
            'ticket_prefix' => 'MED',
        ];
    }

    // ----- Creating ------------------------------------------------------

    public function test_the_owner_creates_a_queue_for_a_service_with_safe_defaults(): void
    {
        $response = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload([
            'ticket_prefix' => ' med ',
        ]));

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.name', 'Consultation queue')
            ->assertJsonPath('data.ticket_prefix', 'MED')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.branch.id', $this->mazzeh->public_id)
            ->assertJsonPath('data.service.id', $this->consultation->public_id)
            ->assertJsonPath('data.capacity', null)
            ->assertJsonPath('data.allow_guest_join', true)
            ->assertJsonPath('data.require_guest_name', true)
            ->assertJsonPath('data.require_guest_phone', false)
            ->assertJsonPath('data.estimated_service_duration', null)
            ->assertJsonPath('data.effective_service_minutes', 20)
            ->assertJsonPath('data.grace_period_seconds', 120)
            ->assertJsonPath('data.current_session', null)
            ->assertJsonPath('data.permissions', ['manage' => true, 'operate' => true]);

        $queue = Queue::query()->sole();
        $this->assertSame($response->json('data.id'), $queue->public_id);
        $this->assertSame(64, strlen((string) $queue->public_token_hash));

        // No internal ids or link secrets in the configuration payload.
        $body = $response->getContent();
        $this->assertStringNotContainsString((string) $queue->public_token_encrypted, $body);
        $this->assertStringNotContainsString($queue->public_token_hash, $body);
        $response->assertJsonMissingPath('data.branch_id')->assertJsonMissingPath('data.public_token');
    }

    public function test_a_manager_with_access_to_the_branch_creates_queues(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->mazzeh]);

        $this->actingAsUser($manager, 'POST', $this->queuesUri($this->mazzeh), $this->payload())->assertCreated();
    }

    public function test_a_ticket_prefix_is_unique_within_the_branch_only(): void
    {
        $this->queueFor($this->consultation, ['ticket_prefix' => 'MED']);
        $vaccination = $this->serviceOf($this->mazzeh);
        $malkiService = $this->serviceOf($this->malki);

        $taken = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload([
            'service' => $vaccination->public_id,
            'ticket_prefix' => 'med',
        ]));
        $otherBranch = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->malki), $this->payload([
            'service' => $malkiService->public_id,
        ]));

        $this->assertApiError($taken, 422, 'validation_failed')->assertJsonValidationErrors('ticket_prefix');
        $otherBranch->assertCreated();
    }

    public function test_each_service_has_exactly_one_queue(): void
    {
        $this->queueFor($this->consultation, ['ticket_prefix' => 'MED']);

        $response = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload([
            'ticket_prefix' => 'OTH',
        ]));

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('service');
        $this->assertSame(1, Queue::query()->count());
    }

    public function test_the_service_must_belong_to_the_branch(): void
    {
        $malkiService = $this->serviceOf($this->malki);
        $foreign = $this->serviceOf($this->branchOf($this->businessOwnedBy(User::factory()->create())));

        foreach ([$malkiService, $foreign] as $service) {
            $response = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload([
                'service' => $service->public_id,
            ]));

            $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('service');
        }
        $this->assertSame(0, Queue::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidQueues(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'missing prefix' => [['ticket_prefix' => null], 'ticket_prefix'],
            'prefix starting with a digit' => [['ticket_prefix' => '1AB'], 'ticket_prefix'],
            'prefix too long' => [['ticket_prefix' => 'ABCDEFG'], 'ticket_prefix'],
            'prefix with a dash' => [['ticket_prefix' => 'A-B'], 'ticket_prefix'],
            'zero capacity' => [['capacity' => 0], 'capacity'],
            'negative grace period' => [['grace_period_seconds' => -1], 'grace_period_seconds'],
            'grace period over an hour' => [['grace_period_seconds' => 3601], 'grace_period_seconds'],
            'zero service duration' => [['estimated_service_duration' => 0], 'estimated_service_duration'],
            'unknown status' => [['status' => 'archived'], 'status'],
            'branch id' => [['branch_id' => 1], 'branch_id'],
            'service id' => [['service_id' => 1], 'service_id'],
            'public token' => [['public_token' => str_repeat('a', 40)], 'public_token'],
            'missing service' => [['service' => null], 'service'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidQueues')]
    public function test_invalid_queue_input_is_rejected(array $overrides, string $field): void
    {
        // A null override removes the field.
        $payload = array_filter(array_merge($this->payload(), $overrides), fn ($value) => $value !== null);

        $response = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $payload);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors($field);
        $this->assertSame(0, Queue::query()->count());
    }

    public function test_employees_cannot_configure_queues(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $response = $this->actingAsUser($employee, 'POST', $this->queuesUri($this->mazzeh), $this->payload());

        $this->assertApiError($response, 403, 'forbidden');
    }

    public function test_other_tenants_and_other_branches_cannot_see_the_branch(): void
    {
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);
        $malkiManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->malki]);

        foreach ([$stranger, $malkiManager] as $user) {
            $response = $this->actingAsUser($user, 'POST', $this->queuesUri($this->mazzeh), $this->payload());
            $this->assertApiError($response, 404, 'branch_not_found');
        }
    }

    public function test_a_suspended_business_cannot_create_queues(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $response = $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload());

        $this->assertApiError($response, 403, 'business_suspended');
    }

    // ----- Listing and viewing ------------------------------------------

    public function test_branch_queues_are_listed_with_their_current_session(): void
    {
        $open = $this->queueFor($this->consultation, ['name' => 'A queue', 'ticket_prefix' => 'AAA']);
        $this->queueFor($this->serviceOf($this->mazzeh), ['name' => 'B queue', 'ticket_prefix' => 'BBB']);
        $this->queueFor($this->serviceOf($this->malki), ['name' => 'Malki queue']);
        $session = $this->openSession($open, $this->owner);

        $response = $this->actingAsUser($this->owner, 'GET', $this->queuesUri($this->mazzeh));

        $this->assertApiSuccess($response)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A queue')
            ->assertJsonPath('data.0.current_session.id', $session->public_id)
            ->assertJsonPath('data.0.current_session.status', 'open')
            ->assertJsonPath('data.1.current_session', null)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_the_business_overview_lists_only_queues_of_reachable_branches(): void
    {
        $this->queueFor($this->consultation, ['name' => 'Mazzeh queue']);
        $this->queueFor($this->serviceOf($this->malki), ['name' => 'Malki queue']);
        $malkiEmployee = $this->memberOf($this->business, BusinessRole::Employee, BranchAccess::Selected, [$this->malki]);
        $uri = '/api/v1/businesses/'.$this->business->public_id.'/queues';

        $owner = $this->actingAsUser($this->owner, 'GET', $uri);
        $employee = $this->actingAsUser($malkiEmployee, 'GET', $uri);

        $owner->assertOk()->assertJsonCount(2, 'data');
        $employee->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Malki queue')
            ->assertJsonPath('data.0.branch.id', $this->malki->public_id)
            ->assertJsonPath('data.0.permissions', ['manage' => false, 'operate' => false]);
    }

    public function test_employees_see_queues_but_only_assigned_ones_are_operable(): void
    {
        $queue = $this->queueFor($this->consultation);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $response = $this->actingAsUser($employee, 'GET', $this->queueUri($queue));

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.id', $queue->public_id)
            ->assertJsonPath('data.permissions', ['manage' => false, 'operate' => false]);
    }

    public function test_a_queue_of_another_tenant_or_branch_is_not_found(): void
    {
        $queue = $this->queueFor($this->consultation);
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);
        $malkiManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->malki]);

        foreach ([$stranger, $malkiManager] as $user) {
            foreach (['GET', 'PUT', 'DELETE'] as $method) {
                $response = $this->actingAsUser($user, $method, $this->queueUri($queue), ['name' => 'Taken over']);
                $this->assertApiError($response, 404, 'queue_not_found');
            }
        }
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', '/api/v1/queues/'.str_repeat('0', 26)), 404, 'queue_not_found');
        $this->actingAsUser($this->owner, 'GET', '/api/v1/queues/'.$queue->getKey())->assertNotFound();
        $this->assertNotSame('Taken over', $queue->fresh()->name);
        $this->assertNotSoftDeleted($queue);
    }

    // ----- Updating -----------------------------------------------------

    public function test_the_configuration_is_updated_partially(): void
    {
        $queue = $this->queueFor($this->consultation, ['ticket_prefix' => 'MED', 'capacity' => null]);

        $response = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), [
            'capacity' => 30,
            'require_guest_phone' => true,
            'grace_period_seconds' => 300,
            'status' => 'inactive',
        ]);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.ticket_prefix', 'MED')
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.require_guest_phone', true)
            ->assertJsonPath('data.grace_period_seconds', 300)
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_an_update_cannot_take_another_queues_prefix_or_move_the_queue(): void
    {
        $queue = $this->queueFor($this->consultation, ['ticket_prefix' => 'MED']);
        $this->queueFor($this->serviceOf($this->mazzeh), ['ticket_prefix' => 'VAC']);

        $prefix = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), ['ticket_prefix' => 'vac']);
        $service = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), ['service' => $this->serviceOf($this->mazzeh)->public_id]);
        $branch = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), ['branch_id' => $this->malki->getKey()]);
        $empty = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), []);
        $same = $this->actingAsUser($this->owner, 'PUT', $this->queueUri($queue), ['ticket_prefix' => 'MED']);

        $this->assertApiError($prefix, 422, 'validation_failed')->assertJsonValidationErrors('ticket_prefix');
        $this->assertApiError($service, 422, 'validation_failed')->assertJsonValidationErrors('service');
        $this->assertApiError($branch, 422, 'validation_failed')->assertJsonValidationErrors('branch_id');
        $this->assertApiError($empty, 422, 'validation_failed')->assertJsonValidationErrors('queue');
        $same->assertOk();
        $this->assertSame($this->mazzeh->getKey(), $queue->fresh()->branch_id);
    }

    public function test_employees_cannot_update_queues(): void
    {
        $queue = $this->queueFor($this->consultation);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($employee, 'PUT', $this->queueUri($queue), ['capacity' => 5]), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'DELETE', $this->queueUri($queue)), 403, 'forbidden');
    }

    // ----- Deleting -----------------------------------------------------

    public function test_deleting_a_queue_keeps_history_and_frees_its_prefix_and_service(): void
    {
        $queue = $this->queueFor($this->consultation, ['ticket_prefix' => 'MED']);

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $this->queueUri($queue)));

        $this->assertSoftDeleted($queue);
        $this->assertNull($queue->fresh()->public_token_hash);
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $this->queueUri($queue)), 404, 'queue_not_found');
        $this->actingAsUser($this->owner, 'POST', $this->queuesUri($this->mazzeh), $this->payload())->assertCreated();
    }

    public function test_a_queue_with_an_active_session_cannot_be_deleted(): void
    {
        $queue = $this->queueFor($this->consultation);
        $this->openSession($queue, $this->owner);

        $response = $this->actingAsUser($this->owner, 'DELETE', $this->queueUri($queue));

        $this->assertApiError($response, 409, 'queue_has_active_session');
        $this->assertNotSoftDeleted($queue);
    }

    // ----- Public link --------------------------------------------------

    public function test_managers_get_the_public_link_and_qr_payload(): void
    {
        $queue = $this->queueFor($this->consultation);
        $token = $queue->public_token_encrypted;

        $response = $this->actingAsUser($this->owner, 'GET', $this->queueUri($queue, 'public-link'));

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.url', rtrim((string) config('app.url'), '/').'/join/'.$token)
            ->assertJsonPath('data.qr_payload', $response->json('data.url'))
            ->assertJsonPath('data.allow_guest_join', true);
        $this->assertSame(40, strlen((string) $token));
    }

    public function test_rotating_the_public_token_invalidates_the_old_link(): void
    {
        $queue = $this->queueFor($this->consultation);
        $oldToken = $queue->public_token_encrypted;

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($queue, 'rotate-public-token'));

        $this->assertApiSuccess($response);
        $queue->refresh();
        $this->assertNotSame($oldToken, $queue->public_token_encrypted);
        $this->assertStringEndsWith('/join/'.$queue->public_token_encrypted, $response->json('data.url'));
        $this->assertSame(hash('sha256', $queue->public_token_encrypted), $queue->public_token_hash);
        $this->assertFalse(Queue::query()->where('public_token_hash', hash('sha256', $oldToken))->exists());
    }

    public function test_only_managers_see_or_rotate_the_public_link(): void
    {
        $queue = $this->queueFor($this->consultation);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($employee, 'GET', $this->queueUri($queue, 'public-link')), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->queueUri($queue, 'rotate-public-token')), 403, 'forbidden');
    }

    public function test_queue_endpoints_require_authentication(): void
    {
        $queue = $this->queueFor($this->consultation);

        $this->assertApiError($this->getJson($this->queueUri($queue)), 401, 'unauthenticated');
        $this->assertApiError($this->postJson($this->queuesUri($this->mazzeh), $this->payload()), 401, 'unauthenticated');
    }
}
