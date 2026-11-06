<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\BusinessRole;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class QueueOperationsTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private User $employee;

    private Business $business;

    private Branch $branch;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->business = $this->businessOwnedBy($this->owner);
        $this->branch = $this->branchOf($this->business);
        $this->queue = $this->queueFor($this->serviceOf($this->branch), [
            'ticket_prefix' => 'A',
            'grace_period_seconds' => 120,
        ]);
        $this->employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $this->employee, $this->owner);
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function callNext(User $staff): TestResponse
    {
        return $this->actingAsUser($staff, 'POST', '/api/v1/queue-sessions/'.$this->session->public_id.'/call-next');
    }

    private function act(User $staff, QueueTicket $ticket, string $action): TestResponse
    {
        return $this->actingAsUser($staff, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/'.$action);
    }

    /**
     * @return list<QueueTicket>
     */
    private function waiting(int $count): array
    {
        return array_map(fn () => $this->ticketIn($this->session), range(1, $count));
    }

    // ----- Calling ------------------------------------------------------

    public function test_call_next_serves_customers_first_in_first_out(): void
    {
        [$first, $second, $third] = $this->waiting(3);

        $called = $this->callNext($this->employee);

        $this->assertApiSuccess($called)
            ->assertJsonPath('data.id', $first->public_id)
            ->assertJsonPath('data.ticket_number', 'A-001')
            ->assertJsonPath('data.status', 'called')
            ->assertJsonPath('data.served_by.name', $this->employee->name)
            ->assertJsonPath('data.served_by.is_you', true);
        $this->assertNotNull($called->json('data.no_show_allowed_at'));

        $this->act($this->employee, $first, 'start')->assertOk()->assertJsonPath('data.status', 'serving');
        $this->act($this->employee, $first, 'complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->callNext($this->employee)->assertJsonPath('data.id', $second->public_id);

        $this->assertSame(TicketStatus::Waiting, $third->fresh()->status);
        $this->assertSame(
            ['session_opened', 'called', 'service_started', 'service_completed', 'called'],
            QueueEvent::query()->orderBy('id')->pluck('event_type')->map->value->all(),
        );
        $completed = QueueEvent::query()->where('event_type', QueueEventType::ServiceCompleted->value)->sole();
        $this->assertSame($this->employee->getKey(), $completed->actor_id);
        $this->assertArrayHasKey('service_seconds', $completed->metadata);
    }

    public function test_two_staff_members_never_get_the_same_customer(): void
    {
        [$first, $second] = $this->waiting(2);

        $a = $this->callNext($this->employee)->json('data.id');
        $b = $this->callNext($this->owner)->json('data.id');

        $this->assertSame([$first->public_id, $second->public_id], [$a, $b]);
        $this->assertSame($this->employee->getKey(), $first->fresh()->served_by);
        $this->assertSame($this->owner->getKey(), $second->fresh()->served_by);
    }

    public function test_a_staff_member_serves_one_customer_at_a_time(): void
    {
        $this->waiting(2);
        $this->callNext($this->employee)->assertOk();

        $this->assertApiError($this->callNext($this->employee), 409, 'staff_busy');
        $this->assertSame(1, QueueTicket::query()->where('status', TicketStatus::Called->value)->count());
    }

    public function test_the_database_rejects_a_second_customer_for_the_same_staff_member(): void
    {
        [$first, $second] = $this->waiting(2);
        $this->callNext($this->employee)->assertOk();

        $this->expectException(UniqueConstraintViolationException::class);

        $second->forceFill(['status' => TicketStatus::Called, 'served_by' => $this->employee->getKey()])->save();
    }

    public function test_calling_with_nobody_waiting_or_on_a_closed_session_fails(): void
    {
        $this->assertApiError($this->callNext($this->employee), 409, 'no_waiting_tickets');

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $this->assertApiError($this->callNext($this->employee), 409, 'queue_closed');
    }

    public function test_a_paused_session_keeps_serving_its_waiting_list(): void
    {
        [$first] = $this->waiting(1);
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'))->assertOk();

        $this->callNext($this->employee)->assertOk()->assertJsonPath('data.id', $first->public_id);
    }

    public function test_managers_call_a_specific_customer_out_of_order(): void
    {
        [, , $third] = $this->waiting(3);

        $this->assertApiError($this->act($this->employee, $third, 'call'), 403, 'forbidden');
        $this->act($this->owner, $third, 'call')->assertOk()->assertJsonPath('data.status', 'called');
        $this->assertSame('specific', QueueEvent::query()->where('event_type', 'called')->sole()->metadata['method']);
    }

    // ----- Serving ------------------------------------------------------

    public function test_only_the_caller_or_a_manager_continues_a_called_ticket(): void
    {
        [$first] = $this->waiting(1);
        $colleague = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $colleague);
        $this->callNext($this->employee);

        $this->assertApiError($this->act($colleague, $first, 'start'), 403, 'ticket_handled_by_other_staff');
        $this->act($this->owner, $first, 'start')->assertOk();
        $this->act($this->owner, $first, 'complete')->assertOk();

        $this->assertSame($this->employee->getKey(), $first->fresh()->served_by);
        $this->assertSame($this->owner->getKey(), QueueEvent::query()->where('event_type', 'service_completed')->sole()->actor_id);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        [$first] = $this->waiting(1);

        $this->assertApiError($this->act($this->employee, $first, 'complete'), 409, 'invalid_ticket_transition');
        $this->assertApiError($this->act($this->employee, $first, 'start'), 409, 'invalid_ticket_transition');
        $this->assertApiError($this->act($this->employee, $first, 'no-show'), 409, 'invalid_ticket_transition');
        $this->assertApiError($this->act($this->employee, $first, 'recall'), 409, 'invalid_ticket_transition');

        $this->callNext($this->employee);
        $this->assertApiError($this->act($this->employee, $first, 'skip'), 409, 'invalid_ticket_transition');
        $this->assertApiError($this->act($this->employee, $first, 'complete'), 409, 'invalid_ticket_transition');

        $this->act($this->employee, $first, 'start');
        $this->act($this->employee, $first, 'complete');
        foreach (['call', 'start', 'complete', 'skip', 'no-show', 'recall'] as $action) {
            $this->assertApiError($this->act($this->owner, $first, $action), 409, 'invalid_ticket_transition');
        }
        $this->assertSame(TicketStatus::Completed, $first->fresh()->status);
    }

    public function test_skipping_takes_a_waiting_customer_off_the_line(): void
    {
        [$first, $second] = $this->waiting(2);

        $this->act($this->employee, $first, 'skip')->assertOk()->assertJsonPath('data.status', 'skipped');

        $this->assertNotNull($first->fresh()->ended_at);
        $this->callNext($this->employee)->assertJsonPath('data.id', $second->public_id);
    }

    public function test_no_show_is_allowed_only_after_the_grace_period(): void
    {
        [$first] = $this->waiting(1);
        $this->callNext($this->employee);

        $early = $this->act($this->employee, $first, 'no-show');
        $this->assertApiError($early, 409, 'grace_period_active');

        $this->travel(121)->seconds();
        $this->act($this->employee, $first, 'no-show')->assertOk()->assertJsonPath('data.status', 'no_show');

        $this->assertNull($first->fresh()->active_staff_key);
        $this->ticketIn($this->session);
        $this->callNext($this->employee)->assertOk();
    }

    public function test_recall_returns_the_customer_to_their_original_place(): void
    {
        [$first, $second] = $this->waiting(2);
        $this->callNext($this->employee);

        $recalled = $this->act($this->employee, $first, 'recall');

        $this->assertApiSuccess($recalled)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.recall_count', 1)
            ->assertJsonPath('data.served_by', null);
        $this->callNext($this->owner)->assertJsonPath('data.id', $first->public_id);
        $this->assertSame(TicketStatus::Waiting, $second->fresh()->status);
    }

    public function test_customers_in_service_can_be_finished_after_the_session_closes(): void
    {
        [$first] = $this->waiting(1);
        $this->callNext($this->employee);
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();

        $this->act($this->employee, $first, 'start')->assertOk();
        $this->act($this->employee, $first, 'complete')->assertOk();
    }

    // ----- Permissions --------------------------------------------------

    public function test_unassigned_employees_cannot_serve(): void
    {
        [$first] = $this->waiting(1);
        $unassigned = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->callNext($unassigned), 403, 'forbidden');
        $this->assertApiError($this->act($unassigned, $first, 'skip'), 403, 'forbidden');
        $this->actingAsUser($unassigned, 'GET', '/api/v1/queue-sessions/'.$this->session->public_id.'/dashboard')->assertOk();
    }

    public function test_other_tenants_cannot_reach_sessions_or_tickets(): void
    {
        [$first] = $this->waiting(1);
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);

        $this->assertApiError($this->callNext($stranger), 404, 'session_not_found');
        $this->assertApiError($this->act($stranger, $first, 'skip'), 404, 'ticket_not_found');
        $this->assertApiError($this->actingAsUser($stranger, 'GET', '/api/v1/tickets/'.$first->public_id), 404, 'ticket_not_found');
        $this->assertApiError(
            $this->actingAsUser($stranger, 'GET', '/api/v1/queue-sessions/'.$this->session->public_id.'/dashboard'),
            404,
            'session_not_found',
        );
        $this->assertSame(TicketStatus::Waiting, $first->fresh()->status);
    }

    public function test_queue_events_are_append_only(): void
    {
        $event = QueueEvent::query()->firstOrFail();

        try {
            $event->forceFill(['event_type' => QueueEventType::Called])->save();
            $this->fail('Updating a queue event must fail.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    // ----- Dashboard and ticket details ---------------------------------

    public function test_the_dashboard_summarises_the_session(): void
    {
        [$first, $second, $third] = $this->waiting(3);
        $this->ticketIn($this->session, TicketStatus::Completed);
        $this->callNext($this->employee);

        $response = $this->actingAsUser($this->employee, 'GET', '/api/v1/queue-sessions/'.$this->session->public_id.'/dashboard');

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.session.id', $this->session->public_id)
            ->assertJsonPath('data.is_current_session', true)
            ->assertJsonPath('data.counts.waiting', 2)
            ->assertJsonPath('data.counts.called', 1)
            ->assertJsonPath('data.counts.completed', 1)
            ->assertJsonPath('data.counts.total', 4)
            ->assertJsonPath('data.next_up.0.id', $second->public_id)
            ->assertJsonPath('data.next_up.0.position', 1)
            ->assertJsonPath('data.next_up.1.id', $third->public_id)
            ->assertJsonPath('data.in_service.0.id', $first->public_id)
            ->assertJsonPath('data.my_ticket.id', $first->public_id)
            ->assertJsonPath('data.estimated_wait.servers', 1)
            ->assertJsonPath('data.permissions', ['manage' => false, 'operate' => true]);
    }

    public function test_session_tickets_are_filtered_by_status(): void
    {
        [$first, $second] = $this->waiting(2);
        $this->ticketIn($this->session, TicketStatus::Cancelled);
        $uri = '/api/v1/queue-sessions/'.$this->session->public_id.'/tickets';

        $this->actingAsUser($this->owner, 'GET', $uri.'?status=waiting')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->public_id)
            ->assertJsonPath('data.1.position', 2);
        $this->actingAsUser($this->owner, 'GET', $uri)->assertOk()->assertJsonCount(3, 'data');
        $this->actingAsUser($this->owner, 'GET', $uri.'?status=cancelled')->assertOk()->assertJsonCount(1, 'data');
        $this->assertApiError($this->actingAsUser($this->owner, 'GET', $uri.'?status=lost'), 422, 'validation_failed');
    }

    public function test_ticket_details_show_history_and_mask_contact_details_for_viewers(): void
    {
        $guest = $this->ticketIn($this->session, TicketStatus::Waiting, ['guest_name' => 'Rami', 'guest_phone' => '+963944123456']);
        $this->callNext($this->employee);
        $viewer = $this->memberOf($this->business, BusinessRole::Employee);

        $operator = $this->actingAsUser($this->employee, 'GET', '/api/v1/tickets/'.$guest->public_id);
        $onlyViewer = $this->actingAsUser($viewer, 'GET', '/api/v1/tickets/'.$guest->public_id);
        $list = $this->actingAsUser($this->employee, 'GET', '/api/v1/queue-sessions/'.$this->session->public_id.'/tickets');

        $this->assertApiSuccess($operator)
            ->assertJsonPath('data.customer', ['type' => 'guest', 'name' => 'Rami', 'phone' => '+963944123456'])
            ->assertJsonPath('data.events.0.type', 'called')
            ->assertJsonPath('data.events.0.from', 'waiting')
            ->assertJsonPath('data.events.0.actor', $this->employee->name);
        $onlyViewer->assertOk()->assertJsonPath('data.customer.phone', '+963••••••456');
        $this->assertStringNotContainsString('944123456', $list->getContent());
    }
}
