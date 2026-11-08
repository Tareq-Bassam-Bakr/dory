<?php

namespace Tests\Feature\Realtime;

use App\Enums\BusinessRole;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Events\Realtime\QueueSignal;
use App\Events\Realtime\StaffQueueUpdated;
use App\Events\Realtime\TicketUpdated;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queues\TicketStateMachine;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Committed queue changes become broadcasts (QueueActivityRelay): the right
 * event, on the right channels, with nothing personal in the payload, and
 * only after the transaction commits.
 */
class QueueBroadcastsTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private User $employee;

    private Business $business;

    private Queue $queue;

    private QueueSession $session;

    /**
     * @var list<object>
     */
    private array $broadcasts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic']);
        $this->queue = $this->queueFor($this->serviceOf($this->branchOf($this->business)), ['ticket_prefix' => 'A']);
        $this->employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $this->employee, $this->owner);
        $this->session = $this->openSession($this->queue, $this->owner);

        // Record every broadcast the relay dispatches, in order.
        Event::listen(
            [QueueSignal::class, TicketUpdated::class, StaffQueueUpdated::class],
            fn (object $event) => $this->broadcasts[] = $event,
        );
        $this->broadcasts = [];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    private function sent(string $class): array
    {
        return array_values(array_filter($this->broadcasts, fn (object $event) => $event instanceof $class));
    }

    /**
     * @return list<string>
     */
    private function channelNames(object $event): array
    {
        return array_map(fn (Channel $channel) => $channel->name, $event->broadcastOn());
    }

    private function customerJoins(User $customer): QueueTicket
    {
        $response = $this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join'));
        $response->assertCreated();

        return QueueTicket::query()->where('public_id', $response->json('data.id'))->sole();
    }

    private function guestJoins(string $name = 'Rami', string $phone = '+963933111222'): array
    {
        $this->queue->forceFill(['require_guest_phone' => true])->save();
        $response = $this->postJson('/api/v1/public/queues/'.$this->queue->public_token_encrypted.'/join', [
            'name' => $name,
            'phone' => $phone,
            'consent' => true,
        ]);
        $response->assertCreated();

        return [$response->json('data.id'), $response->json('data.access_token')];
    }

    // ----- What is sent ---------------------------------------------------

    public function test_a_customer_joining_signals_the_session_the_ticket_and_the_staff(): void
    {
        $ticket = $this->customerJoins(User::factory()->create());

        $signal = $this->sent(QueueSignal::class)[0];
        $this->assertSame(['queue-updates.'.$this->session->public_id], $this->channelNames($signal));
        $this->assertNotInstanceOf(PrivateChannel::class, $signal->broadcastOn()[0]);
        $this->assertSame('queue.changed', $signal->broadcastAs());

        $update = $this->sent(TicketUpdated::class)[0];
        $this->assertSame(['private-tickets.'.$ticket->public_id], $this->channelNames($update));
        $this->assertSame('joined', $update->broadcastWith()['event']);
        $this->assertSame('waiting', $update->broadcastWith()['status']);

        $staff = $this->sent(StaffQueueUpdated::class)[0];
        $this->assertSame('ticket_joined', $staff->broadcastWith()['kind']);
        $this->assertEqualsCanonicalizing([
            'private-businesses.'.$this->business->public_id,
            'private-queue-sessions.'.$this->session->public_id,
        ], $this->channelNames($staff));
    }

    public function test_every_staff_transition_reaches_the_customer_and_the_staff(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->customerJoins($customer);
        $steps = [
            ['call', 'called', 'called'],
            ['start', 'serving', 'service_started'],
            ['complete', 'completed', 'service_completed'],
        ];

        foreach ($steps as [$action, $status, $event]) {
            $this->broadcasts = [];
            $this->actingAsUser($this->owner, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/'.$action)->assertOk();

            $update = $this->sent(TicketUpdated::class);
            $this->assertCount(1, $update, $action);
            $this->assertSame(['ticket' => $ticket->public_id, 'status' => $status, 'event' => $event], array_intersect_key(
                $update[0]->broadcastWith(),
                array_flip(['ticket', 'status', 'event']),
            ));
            $this->assertSame('ticket_status_changed', $this->sent(StaffQueueUpdated::class)[0]->broadcastWith()['kind']);
            $this->assertCount(1, $this->sent(QueueSignal::class));
        }
    }

    public function test_skip_and_no_show_are_broadcast_too(): void
    {
        $skipped = $this->customerJoins(User::factory()->create());
        $missed = $this->customerJoins(User::factory()->create());
        $this->broadcasts = [];

        $this->actingAsUser($this->owner, 'POST', '/api/v1/tickets/'.$skipped->public_id.'/skip')->assertOk();
        $this->assertSame('skipped', $this->sent(TicketUpdated::class)[0]->broadcastWith()['event']);

        $this->broadcasts = [];
        $this->actingAsUser($this->owner, 'POST', '/api/v1/tickets/'.$missed->public_id.'/call')->assertOk();
        $this->broadcasts = [];
        // No-show is allowed only after the arrival grace period.
        $this->travel(1)->hours();
        $this->actingAsUser($this->owner, 'POST', '/api/v1/tickets/'.$missed->public_id.'/no-show')->assertOk();
        $this->assertSame('no_show', $this->sent(TicketUpdated::class)[0]->broadcastWith()['event']);
    }

    public function test_a_customer_cancelling_tells_the_staff_it_was_the_customer(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->customerJoins($customer);
        $this->broadcasts = [];

        $this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel')->assertOk();

        $this->assertSame('customer_cancelled', $this->sent(StaffQueueUpdated::class)[0]->broadcastWith()['kind']);
        $this->assertSame('cancelled', $this->sent(TicketUpdated::class)[0]->broadcastWith()['status']);
    }

    public function test_a_guest_gets_only_the_public_signal_and_it_carries_nothing_personal(): void
    {
        [$ticketId, $token] = $this->guestJoins('Rami Haddad', '+963933111222');
        $this->getJson('/api/v1/public/tickets/'.$ticketId, ['X-Ticket-Token' => $token])->assertOk();
        $this->broadcasts = [];

        $this->postJson('/api/v1/public/tickets/'.$ticketId.'/cancel', [], ['X-Ticket-Token' => $token])->assertOk();

        $this->assertSame([], $this->sent(TicketUpdated::class), 'Guests have no private channel.');
        $this->assertSame('customer_cancelled', $this->sent(StaffQueueUpdated::class)[0]->broadcastWith()['kind']);

        $signal = $this->sent(QueueSignal::class)[0];
        $this->assertSame(['session', 'session_status', 'changed_at'], array_keys($signal->broadcastWith()));
        $everything = json_encode(array_map(fn (object $event) => $event->broadcastWith(), $this->broadcasts));
        foreach (['Rami', 'Haddad', '963933111222', $token, $ticketId] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($signal->broadcastWith()), 'Public signal leaks '.$secret);
        }
        foreach (['Rami', 'Haddad', '963933111222', $token] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $everything, 'A broadcast leaks '.$secret);
        }
    }

    public function test_staff_payloads_hold_ids_and_statuses_only(): void
    {
        $customer = User::factory()->create(['name' => 'Layla Customer', 'phone' => '+963944555666']);
        $this->customerJoins($customer);

        $payload = $this->sent(StaffQueueUpdated::class)[0]->broadcastWith();
        $this->assertSame(['kind', 'queue', 'session', 'ticket', 'status', 'occurred_at'], array_keys($payload));
        $this->assertStringNotContainsString('Layla', (string) json_encode($payload));
        $this->assertStringNotContainsString('963944555666', (string) json_encode($payload));
        $this->assertSame(['ticket', 'status', 'event', 'occurred_at'], array_keys($this->sent(TicketUpdated::class)[0]->broadcastWith()));
    }

    public function test_pausing_and_closing_a_session_is_announced(): void
    {
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'))->assertOk();

        $this->assertSame('paused', $this->sent(QueueSignal::class)[0]->broadcastWith()['session_status']);
        $staff = $this->sent(StaffQueueUpdated::class)[0]->broadcastWith();
        $this->assertSame('session_changed', $staff['kind']);
        $this->assertSame('paused', $staff['status']);

        $this->broadcasts = [];
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $this->assertSame('closed', $this->sent(QueueSignal::class)[0]->broadcastWith()['session_status']);
    }

    public function test_queue_settings_changes_reach_the_staff(): void
    {
        $this->actingAsUser($this->owner, 'PUT', $this->queueUri($this->queue), ['capacity' => 25])->assertOk();
        $this->assertSame('capacity_changed', $this->sent(StaffQueueUpdated::class)[0]->broadcastWith()['kind']);

        $this->broadcasts = [];
        $this->actingAsUser($this->owner, 'PUT', $this->queueUri($this->queue), ['name' => 'Morning queue'])->assertOk();
        $this->assertSame('settings_changed', $this->sent(StaffQueueUpdated::class)[0]->broadcastWith()['kind']);
    }

    // ----- When it is sent ------------------------------------------------

    public function test_nothing_is_broadcast_before_the_transaction_commits(): void
    {
        $ticket = $this->ticketIn($this->session);
        $this->broadcasts = [];

        DB::beginTransaction();
        $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->sole();
        app(TicketStateMachine::class)->transition($locked, TicketStatus::Called, QueueEventType::Called, $this->owner);
        $this->assertSame([], $this->broadcasts, 'Broadcast inside the transaction.');
        DB::commit();

        $this->assertCount(1, $this->sent(QueueSignal::class));
    }

    public function test_a_rolled_back_change_is_never_broadcast(): void
    {
        $ticket = $this->ticketIn($this->session);
        $this->broadcasts = [];

        try {
            DB::transaction(function () use ($ticket): void {
                $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->sole();
                app(TicketStateMachine::class)->transition($locked, TicketStatus::Called, QueueEventType::Called, $this->owner);
                throw new RuntimeException('Something failed after the transition.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->broadcasts);
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);
    }

    public function test_broadcasts_go_to_the_realtime_queue(): void
    {
        $this->customerJoins(User::factory()->create());

        foreach ($this->broadcasts as $event) {
            $this->assertSame('realtime', $event->broadcastQueue());
        }
    }
}
