<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\JoinSource;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queues\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class GuestJoinTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $branch;

    private Queue $queue;

    private QueueSession $session;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Ahmad Barber']);
        $this->branch = $this->branchOf($this->business, ['name' => 'Mazzeh', 'city' => 'Damascus']);
        $this->queue = $this->queueFor($this->serviceOf($this->branch, ['name' => 'Haircut']), [
            'name' => 'Haircuts',
            'ticket_prefix' => 'HC',
            'estimated_service_duration' => 12,
        ]);
        $this->token = $this->queue->public_token_encrypted;
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function publicUri(string $suffix = ''): string
    {
        return '/api/v1/public/queues/'.$this->token.($suffix === '' ? '' : '/'.$suffix);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function guestJoin(array $data = ['name' => 'Rami'], array $headers = []): TestResponse
    {
        return $this->postJson($this->publicUri('join'), $data, $headers);
    }

    private function ticketUri(string $publicId, string $suffix = ''): string
    {
        return '/api/v1/public/tickets/'.$publicId.($suffix === '' ? '' : '/'.$suffix);
    }

    // ----- Public queue page --------------------------------------------

    public function test_the_public_link_shows_the_queue_without_customer_data(): void
    {
        $this->ticketIn($this->session, TicketStatus::Waiting, ['guest_name' => 'Secret Name', 'guest_phone' => '+963944000111']);
        $this->ticketIn($this->session, TicketStatus::Serving, ['served_by' => $this->owner->getKey()]);

        $response = $this->getJson($this->publicUri());

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.id', $this->queue->public_id)
            ->assertJsonPath('data.name', 'Haircuts')
            ->assertJsonPath('data.business.name', 'Ahmad Barber')
            ->assertJsonPath('data.branch.name', 'Mazzeh')
            ->assertJsonPath('data.service.name', 'Haircut')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.accepting_joins', true)
            ->assertJsonPath('data.waiting_count', 1)
            ->assertJsonPath('data.now_serving', ['HC-002'])
            ->assertJsonPath('data.estimated_wait.seconds', 12 * 60)
            ->assertJsonPath('data.require_guest_name', true)
            ->assertJsonPath('data.require_guest_phone', false);
        $this->assertStringNotContainsString('Secret Name', $response->getContent());
        $this->assertStringNotContainsString('944000111', $response->getContent());
    }

    public function test_paused_closed_and_inactive_queues_say_so(): void
    {
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'));
        $this->getJson($this->publicUri())->assertJsonPath('data.status', 'paused')->assertJsonPath('data.accepting_joins', false);

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'));
        $this->getJson($this->publicUri())->assertJsonPath('data.status', 'closed')->assertJsonPath('data.waiting_count', 0);

        $this->queue->update(['status' => 'inactive']);
        $this->getJson($this->publicUri())->assertJsonPath('data.status', 'inactive');
    }

    public function test_unknown_rotated_and_deleted_links_are_not_found(): void
    {
        $this->assertApiError($this->getJson('/api/v1/public/queues/'.str_repeat('x', 40)), 404, 'queue_not_found');
        $this->getJson('/api/v1/public/queues/short')->assertNotFound();

        $old = $this->token;
        app(QueueService::class)->rotatePublicToken($this->queue);
        $this->assertApiError($this->getJson('/api/v1/public/queues/'.$old), 404, 'queue_not_found');
        $this->assertApiError($this->postJson('/api/v1/public/queues/'.$old.'/join', ['name' => 'Rami']), 404, 'queue_not_found');
    }

    // ----- Joining ------------------------------------------------------

    public function test_a_guest_joins_with_a_name_and_gets_a_one_time_access_token(): void
    {
        $response = $this->guestJoin(['name' => '  Rami   Haddad ']);

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.ticket_number', 'HC-001')
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1);
        $token = $response->json('data.access_token');
        $this->assertIsString($token);
        $this->assertSame(48, strlen($token));

        $ticket = QueueTicket::query()->sole();
        $this->assertSame('Rami Haddad', $ticket->guest_name);
        $this->assertNull($ticket->user_id);
        $this->assertSame(JoinSource::PublicLink, $ticket->join_source);
        $this->assertSame(hash('sha256', $token), $ticket->public_access_token_hash);

        // The plaintext token is stored nowhere.
        $row = (array) DB::table('queue_tickets')->first();
        $this->assertNotContains($token, array_map('strval', $row));
        $this->assertStringNotContainsString($token, json_encode(DB::table('queue_events')->get()));

        $event = QueueEvent::query()->where('event_type', QueueEventType::Joined->value)->sole();
        $this->assertNull($event->actor_id);
        $this->assertSame('guest', $event->metadata['actor']);
        $this->assertArrayNotHasKey('guest_name', $event->metadata);
    }

    public function test_the_name_is_required_only_when_the_queue_asks_for_it(): void
    {
        $this->assertApiError($this->guestJoin([]), 422, 'validation_failed')->assertJsonValidationErrors('name');
        $this->assertApiError($this->guestJoin(['name' => 'R']), 422, 'validation_failed')->assertJsonValidationErrors('name');

        $this->queue->update(['require_guest_name' => false]);
        $this->guestJoin([])->assertCreated();
    }

    public function test_a_phone_number_needs_consent_and_is_stored_encrypted(): void
    {
        $this->queue->update(['require_guest_phone' => true]);

        $this->assertApiError($this->guestJoin(['name' => 'Rami']), 422, 'validation_failed')->assertJsonValidationErrors('phone');
        $this->assertApiError($this->guestJoin(['name' => 'Rami', 'phone' => '+963 944 123 456']), 422, 'validation_failed')
            ->assertJsonValidationErrors('consent');
        $this->assertApiError($this->guestJoin(['name' => 'Rami', 'phone' => '0944', 'consent' => true]), 422, 'validation_failed')
            ->assertJsonValidationErrors('phone');

        $response = $this->guestJoin(['name' => 'Rami', 'phone' => '+963 944 123 456', 'consent' => true]);

        $response->assertCreated();
        $this->assertStringNotContainsString('944123456', $response->getContent());
        $raw = DB::table('queue_tickets')->value('guest_phone');
        $this->assertStringNotContainsString('944123456', $raw);
        $this->assertSame('+963944123456', Crypt::decryptString($raw));
        $this->assertSame('+963944123456', QueueTicket::query()->sole()->guest_phone);
    }

    public function test_guest_joining_can_be_turned_off(): void
    {
        $this->queue->update(['allow_guest_join' => false]);

        $this->assertApiError($this->guestJoin(), 403, 'guest_join_disabled');
        $this->getJson($this->publicUri())->assertJsonPath('data.allow_guest_join', false);

        // Signed-in customers can still join.
        $this->actingAsUser(User::factory()->create(), 'POST', $this->queueUri($this->queue, 'join'))->assertCreated();
    }

    public function test_guests_cannot_join_paused_or_closed_queues(): void
    {
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'));
        $this->assertApiError($this->guestJoin(), 409, 'queue_paused');

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'));
        $this->assertApiError($this->guestJoin(), 409, 'queue_closed');
    }

    public function test_a_retried_guest_join_returns_the_same_ticket_with_a_fresh_token(): void
    {
        $key = ['Idempotency-Key' => 'guest-retry-key-000001'];

        $first = $this->guestJoin(['name' => 'Rami'], $key);
        $retry = $this->guestJoin(['name' => 'Rami'], $key);

        $first->assertCreated();
        $this->assertApiSuccess($retry)->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, QueueTicket::query()->count());
        $oldToken = $first->json('data.access_token');
        $newToken = $retry->json('data.access_token');
        $this->assertNotSame($oldToken, $newToken);

        $id = $first->json('data.id');
        $this->getJson($this->ticketUri($id), ['X-Ticket-Token' => $newToken])->assertOk();
        $this->assertApiError($this->getJson($this->ticketUri($id), ['X-Ticket-Token' => $oldToken]), 404, 'ticket_not_found');
    }

    // ----- Following and cancelling -------------------------------------

    public function test_the_guest_follows_the_ticket_with_the_token_header_only(): void
    {
        $joined = $this->guestJoin(['name' => 'Rami', 'phone' => '+963944123456', 'consent' => true]);
        $id = $joined->json('data.id');
        $token = $joined->json('data.access_token');

        $ok = $this->getJson($this->ticketUri($id), ['X-Ticket-Token' => $token]);

        $this->assertApiSuccess($ok)
            ->assertJsonPath('data.ticket_number', 'HC-001')
            ->assertJsonPath('data.position', 1)
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.guest_phone');
        $this->assertStringNotContainsString('944123456', $ok->getContent());

        $this->assertApiError($this->getJson($this->ticketUri($id)), 404, 'ticket_not_found');
        $this->assertApiError($this->getJson($this->ticketUri($id), ['X-Ticket-Token' => str_repeat('a', 48)]), 404, 'ticket_not_found');
        $this->assertApiError($this->getJson($this->ticketUri($id).'?token='.$token), 404, 'ticket_not_found');
        $this->assertApiError($this->getJson($this->ticketUri(str_repeat('0', 26)), ['X-Ticket-Token' => $token]), 404, 'ticket_not_found');
    }

    public function test_customer_tickets_are_not_reachable_through_the_guest_endpoint(): void
    {
        $customer = User::factory()->create();
        $joined = $this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join'));
        $this->forgetAuthenticatedUser();

        $this->assertApiError($this->getJson($this->ticketUri($joined->json('data.id')), ['X-Ticket-Token' => '']), 404, 'ticket_not_found');
    }

    public function test_the_guest_cancels_with_the_token(): void
    {
        $joined = $this->guestJoin();
        $id = $joined->json('data.id');
        $token = $joined->json('data.access_token');

        $this->assertApiError($this->postJson($this->ticketUri($id, 'cancel')), 404, 'ticket_not_found');
        $response = $this->postJson($this->ticketUri($id, 'cancel'), [], ['X-Ticket-Token' => $token]);

        $this->assertApiSuccess($response)->assertJsonPath('data.status', 'cancelled');
        $ticket = QueueTicket::query()->sole();
        $this->assertSame('guest_cancelled', $ticket->cancel_reason);
        $event = QueueEvent::query()->where('event_type', QueueEventType::Cancelled->value)->sole();
        $this->assertSame('guest', $event->metadata['actor']);
        $this->assertApiError($this->postJson($this->ticketUri($id, 'cancel'), [], ['X-Ticket-Token' => $token]), 409, 'cancellation_not_allowed');
    }

    public function test_guest_joins_are_rate_limited_per_ip_and_queue(): void
    {
        config(['dory.rate_limits.queue_join' => 2]);

        $this->guestJoin()->assertCreated();
        $this->guestJoin()->assertCreated();

        $this->assertApiError($this->guestJoin(), 429, 'too_many_requests');
    }
}
