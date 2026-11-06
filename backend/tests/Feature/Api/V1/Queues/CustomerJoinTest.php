<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class CustomerJoinTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $branch;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
        $this->branch = $this->branchOf($this->business, ['timezone' => 'Asia/Damascus']);
        $this->queue = $this->queueFor($this->serviceOf($this->branch), [
            'ticket_prefix' => 'BAR',
            'estimated_service_duration' => 10,
        ]);
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function join(User $user, ?Queue $queue = null, array $headers = []): TestResponse
    {
        return $this->actingAsUser($user, 'POST', $this->queueUri($queue ?? $this->queue, 'join'), [], $headers);
    }

    // ----- Joining ------------------------------------------------------

    public function test_a_customer_joins_and_gets_the_next_ticket_number(): void
    {
        $customer = User::factory()->create();

        $response = $this->join($customer);

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.ticket_number', 'BAR-001')
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.people_ahead', 0)
            ->assertJsonPath('data.estimated_wait.seconds', 0)
            ->assertJsonPath('data.estimated_wait.basis', 'configured')
            ->assertJsonPath('data.queue.id', $this->queue->public_id)
            ->assertJsonPath('data.queue.session_status', 'open')
            ->assertJsonPath('data.business.name', $this->business->name)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.user_id');

        $ticket = QueueTicket::query()->sole();
        $this->assertSame($customer->getKey(), $ticket->user_id);
        $this->assertNull($ticket->public_access_token_hash);
        $this->assertSame(26, strlen($ticket->public_id));
        $this->assertNotSame($ticket->ticket_number, $ticket->public_id);
        $this->assertTrue(QueueEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', QueueEventType::Joined->value)
            ->where('actor_id', $customer->getKey())
            ->exists());
    }

    public function test_numbers_increase_per_queue_and_estimates_follow_the_line(): void
    {
        $numbers = [];
        foreach (range(1, 3) as $i) {
            $numbers[] = $this->join(User::factory()->create())->json('data.ticket_number');
        }
        $other = $this->queueFor($this->serviceOf($this->branch), ['ticket_prefix' => 'HC']);
        $this->openSession($other, $this->owner);
        $fourth = $this->join(User::factory()->create());
        $otherQueue = $this->join(User::factory()->create(), $other);

        $this->assertSame(['BAR-001', 'BAR-002', 'BAR-003'], $numbers);
        $fourth->assertJsonPath('data.ticket_number', 'BAR-004')
            ->assertJsonPath('data.position', 4)
            ->assertJsonPath('data.estimated_wait.seconds', 3 * 10 * 60);
        $otherQueue->assertJsonPath('data.ticket_number', 'HC-001');
    }

    public function test_numbers_are_never_reused_after_cancellation_or_a_new_session(): void
    {
        $first = User::factory()->create();
        $this->join($first)->assertJsonPath('data.ticket_number', 'BAR-001');
        $ticket = QueueTicket::query()->sole();
        $this->actingAsUser($first, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel')->assertOk();
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();

        $this->join(User::factory()->create())->assertJsonPath('data.ticket_number', 'BAR-002');
    }

    public function test_numbering_restarts_on_the_next_business_day_of_the_branch(): void
    {
        // setUp opened a session at the real current time. Close it so both
        // business days below are set by the test, whatever today's date is.
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 20:00:00', 'Asia/Damascus'));
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'))
            ->assertJsonPath('data.business_date', '2026-11-06');
        $this->join(User::factory()->create())->assertJsonPath('data.ticket_number', 'BAR-001');
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();

        // 00:10 in Damascus is still 21:10 UTC on the previous day.
        $this->travelTo(CarbonImmutable::parse('2026-11-07 00:10:00', 'Asia/Damascus'));
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'))
            ->assertJsonPath('data.business_date', '2026-11-07');
        $response = $this->join(User::factory()->create());

        $response->assertJsonPath('data.ticket_number', 'BAR-001')->assertJsonPath('data.business_date', '2026-11-07');
        $this->assertSame(2, DB::table('queue_daily_counters')->count());
    }

    public function test_prefixes_with_three_digit_padding_grow_past_999(): void
    {
        DB::table('queue_daily_counters')->insert([
            'queue_id' => $this->queue->getKey(),
            'business_date' => $this->session->business_date->toDateString(),
            'last_number' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->join(User::factory()->create())->assertJsonPath('data.ticket_number', 'BAR-1000');
    }

    public function test_the_database_rejects_a_duplicate_sequence_number(): void
    {
        $this->join(User::factory()->create())->assertCreated();
        $ticket = QueueTicket::query()->sole();

        $this->expectException(UniqueConstraintViolationException::class);

        $copy = $ticket->replicate(['public_id', 'active_user_key', 'idempotency_key_hash']);
        $copy->user_id = null;
        $copy->save();
    }

    // ----- Rules --------------------------------------------------------

    public function test_a_customer_cannot_hold_two_active_tickets_in_one_queue(): void
    {
        $customer = User::factory()->create();
        $this->join($customer)->assertCreated();

        $this->assertApiError($this->join($customer), 409, 'duplicate_active_ticket');
        $this->assertSame(1, QueueTicket::query()->count());
    }

    public function test_the_database_rejects_a_second_active_ticket_for_a_user(): void
    {
        $customer = User::factory()->create();
        $this->join($customer)->assertCreated();
        $ticket = QueueTicket::query()->sole();

        $this->expectException(UniqueConstraintViolationException::class);

        $copy = $ticket->replicate(['public_id', 'idempotency_key_hash']);
        $copy->sequence_number = 99;
        $copy->ticket_number = 'BAR-099';
        $copy->save();
    }

    public function test_after_leaving_a_customer_can_join_again(): void
    {
        $customer = User::factory()->create();
        $this->join($customer)->assertCreated();
        $ticket = QueueTicket::query()->sole();
        $this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel')->assertOk();

        $this->join($customer)->assertCreated()->assertJsonPath('data.ticket_number', 'BAR-002');
    }

    public function test_closed_paused_inactive_and_full_queues_reject_joins(): void
    {
        $customer = User::factory()->create();

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'))->assertOk();
        $this->assertApiError($this->join($customer), 409, 'queue_paused');

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $this->assertApiError($this->join($customer), 409, 'queue_closed');

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();
        $this->queue->update(['capacity' => 1]);
        $this->join(User::factory()->create())->assertCreated();
        $this->assertApiError($this->join($customer), 409, 'queue_full');

        $this->queue->update(['status' => 'inactive']);
        $this->assertApiError($this->join($customer), 409, 'queue_inactive');
    }

    public function test_capacity_counts_only_waiting_customers(): void
    {
        $this->queue->update(['capacity' => 1]);
        $this->ticketIn($this->session, TicketStatus::Serving, ['served_by' => $this->owner->getKey()]);
        $this->ticketIn($this->session, TicketStatus::Completed);

        $this->join(User::factory()->create())->assertCreated();
    }

    public function test_a_deleted_queue_or_business_cannot_be_joined(): void
    {
        $customer = User::factory()->create();
        $other = $this->queueFor($this->serviceOf($this->branch));
        $other->delete();

        $this->assertApiError($this->join($customer, $other), 404, 'queue_not_found');

        $this->business->delete();
        $this->assertApiError($this->join($customer), 404, 'queue_not_found');
    }

    public function test_joining_requires_authentication_and_rejects_identity_fields(): void
    {
        $this->assertApiError($this->postJson($this->queueUri($this->queue, 'join')), 401, 'unauthenticated');

        $customer = User::factory()->create();
        $response = $this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join'), ['user_id' => $this->owner->getKey()]);
        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('user_id');
    }

    // ----- Idempotency --------------------------------------------------

    public function test_a_retried_join_with_the_same_key_returns_the_same_ticket(): void
    {
        $customer = User::factory()->create();
        $key = ['Idempotency-Key' => 'join-retry-0001-abcdef'];

        $first = $this->join($customer, headers: $key);
        $retry = $this->join($customer, headers: $key);

        $first->assertCreated();
        $this->assertApiSuccess($retry)->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, QueueTicket::query()->count());
        $this->assertSame(1, (int) DB::table('queue_daily_counters')->value('last_number'));
    }

    public function test_idempotency_keys_are_scoped_to_the_customer(): void
    {
        $key = ['Idempotency-Key' => 'same-key-for-both-users'];

        $this->join(User::factory()->create(), headers: $key)->assertCreated();
        $this->join(User::factory()->create(), headers: $key)->assertCreated()->assertJsonPath('data.ticket_number', 'BAR-002');
    }

    public function test_a_malformed_idempotency_key_is_rejected(): void
    {
        $response = $this->join(User::factory()->create(), headers: ['Idempotency-Key' => 'short']);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('idempotency_key');
    }

    // ----- Following and cancelling -------------------------------------

    public function test_a_customer_follows_their_position_as_the_line_moves(): void
    {
        $this->join(User::factory()->create());
        $customer = User::factory()->create();
        $this->join($customer);
        $ticket = QueueTicket::query()->where('user_id', $customer->getKey())->sole();
        $uri = '/api/v1/tickets/'.$ticket->public_id.'/position';

        $this->actingAsUser($customer, 'GET', $uri)
            ->assertOk()
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.estimated_wait.seconds', 600)
            ->assertJsonPath('data.now_serving', []);

        $first = QueueTicket::query()->where('sequence_number', 1)->sole();
        $first->forceFill(['status' => TicketStatus::Called, 'called_at' => now(), 'served_by' => $this->owner->getKey()])->save();

        $this->actingAsUser($customer, 'GET', $uri)
            ->assertOk()
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.people_ahead', 0)
            ->assertJsonPath('data.now_serving', ['BAR-001']);
    }

    public function test_customers_list_their_tickets_with_active_ones_first(): void
    {
        $customer = User::factory()->create();
        $other = $this->queueFor($this->serviceOf($this->branch), ['ticket_prefix' => 'HC']);
        $this->openSession($other, $this->owner);
        $this->join($customer, $other)->assertCreated();
        $old = QueueTicket::query()->sole();
        $this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$old->public_id.'/cancel')->assertOk();
        $this->join($customer)->assertCreated();
        $this->join(User::factory()->create())->assertCreated();

        $response = $this->actingAsUser($customer, 'GET', '/api/v1/me/tickets');

        $this->assertApiSuccess($response)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.ticket_number', 'BAR-001')
            ->assertJsonPath('data.0.status', 'waiting')
            ->assertJsonPath('data.1.ticket_number', 'HC-001')
            ->assertJsonPath('data.1.status', 'cancelled')
            ->assertJsonPath('data.1.cancel_reason', 'customer_cancelled')
            ->assertJsonPath('data.1.position', null);
    }

    public function test_a_customer_cancels_a_waiting_ticket_only(): void
    {
        $customer = User::factory()->create();
        $this->join($customer);
        $ticket = QueueTicket::query()->sole();
        $uri = '/api/v1/tickets/'.$ticket->public_id.'/cancel';

        $this->assertApiSuccess($this->actingAsUser($customer, 'POST', $uri))
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false);
        $this->assertApiError($this->actingAsUser($customer, 'POST', $uri), 409, 'cancellation_not_allowed');

        $called = $this->ticketIn($this->session, TicketStatus::Called, [
            'user_id' => $customer->getKey(),
            'served_by' => $this->owner->getKey(),
        ]);
        $this->assertApiError(
            $this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$called->public_id.'/cancel'),
            409,
            'cancellation_not_allowed',
        );
        $this->assertSame(TicketStatus::Called, $called->fresh()->status);
    }

    public function test_nobody_else_can_read_or_cancel_a_customers_ticket(): void
    {
        $customer = User::factory()->create();
        $this->join($customer);
        $ticket = QueueTicket::query()->sole();

        foreach ([User::factory()->create(), $this->owner] as $intruder) {
            $this->assertApiError($this->actingAsUser($intruder, 'GET', '/api/v1/tickets/'.$ticket->public_id.'/position'), 404, 'ticket_not_found');
            $this->assertApiError($this->actingAsUser($intruder, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel'), 404, 'ticket_not_found');
        }
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);
    }
}
