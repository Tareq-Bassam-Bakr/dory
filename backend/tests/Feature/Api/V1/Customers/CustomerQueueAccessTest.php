<?php

namespace Tests\Feature\Api\V1\Customers;

use App\Enums\JoinSource;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Joining from the app (signed in or as a guest) and following tickets.
 * Everything goes through the Phase 04 queue engine.
 */
class CustomerQueueAccessTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsTenants, RefreshDatabase;

    private Business $business;

    private Branch $branch;

    private Service $service;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->business, $this->branch] = $this->publicBusiness('Shifa Clinic');
        [$this->service, $this->queue, $this->session] = $this->openQueueIn($this->branch, ['ticket_prefix' => 'BAR', 'estimated_service_duration' => 10]);
    }

    private function publicUri(string $suffix = ''): string
    {
        return '/api/v1/public/queues/'.$this->queue->public_id.$suffix;
    }

    // ----- Guests, by queue id ---------------------------------------------

    public function test_a_guest_joins_by_queue_id_and_gets_the_same_ticket_contract(): void
    {
        $response = $this->assertApiSuccess($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 201)
            ->assertJsonPath('data.ticket_number', 'BAR-001')
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1);

        $id = $response->json('data.id');
        $token = $response->json('data.access_token');
        $this->assertSame(48, strlen($token));

        $ticket = QueueTicket::query()->sole();
        $this->assertNull($ticket->user_id);
        $this->assertSame(JoinSource::App, $ticket->join_source);
        $this->assertNotSame($token, $ticket->public_access_token_hash);

        // Exactly the Phase 04 header contract.
        $this->getJson('/api/v1/public/tickets/'.$id, ['X-Ticket-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.ticket_number', 'BAR-001')
            ->assertJsonMissingPath('data.access_token');
        $this->postJson('/api/v1/public/tickets/'.$id.'/cancel', [], ['X-Ticket-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_guest_ticket_is_not_readable_without_its_own_token(): void
    {
        $first = $this->postJson($this->publicUri('/join'), ['name' => 'Rami']);
        $second = $this->postJson($this->publicUri('/join'), ['name' => 'Lina']);
        $uri = '/api/v1/public/tickets/'.$first->json('data.id');

        $this->assertApiError($this->getJson($uri), 404, 'ticket_not_found');
        $this->assertApiError($this->getJson($uri, ['X-Ticket-Token' => $second->json('data.access_token')]), 404, 'ticket_not_found');
        // A token in the URL is not accepted.
        $this->assertApiError($this->getJson($uri.'?token='.$first->json('data.access_token')), 404, 'ticket_not_found');
        $this->assertApiError($this->postJson($uri.'/cancel', [], ['X-Ticket-Token' => 'wrong']), 404, 'ticket_not_found');
    }

    public function test_guest_rules_of_the_queue_apply_when_joining_by_id(): void
    {
        $this->queue->forceFill(['allow_guest_join' => false])->save();
        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 403, 'guest_join_disabled');

        $this->queue->forceFill(['allow_guest_join' => true, 'require_guest_phone' => true])->save();
        $this->postJson($this->publicUri('/join'), ['name' => 'Rami'])->assertStatus(422)->assertJsonValidationErrors(['phone'], 'errors');
        $this->postJson($this->publicUri('/join'), ['name' => 'Rami', 'phone' => '+963933111222'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consent'], 'errors');
        $this->postJson($this->publicUri('/join'), ['name' => 'Rami', 'phone' => '+963933111222', 'consent' => true])->assertCreated();
    }

    public function test_a_guest_retry_with_the_same_key_returns_the_same_ticket(): void
    {
        $headers = ['Idempotency-Key' => 'guest-retry-key-000001'];

        $first = $this->postJson($this->publicUri('/join'), ['name' => 'Rami'], $headers)->assertCreated();
        $retry = $this->postJson($this->publicUri('/join'), ['name' => 'Rami'], $headers)->assertOk();

        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertSame(1, QueueTicket::query()->count());
        // The first response may have been lost, so the retry gets a working token.
        $this->getJson('/api/v1/public/tickets/'.$retry->json('data.id'), ['X-Ticket-Token' => $retry->json('data.access_token')])->assertOk();
    }

    public function test_the_public_queue_view_by_id_shows_what_is_needed_before_joining(): void
    {
        $this->queue->forceFill(['capacity' => 3])->save();
        $this->ticketIn($this->session);

        $this->assertApiSuccess($this->getJson($this->publicUri()))
            ->assertJsonPath('data.name', $this->queue->name)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.accepting_joins', true)
            ->assertJsonPath('data.is_full', false)
            ->assertJsonPath('data.waiting_count', 1)
            ->assertJsonPath('data.capacity_remaining', 2)
            ->assertJsonPath('data.business.slug', $this->business->slug)
            ->assertJsonPath('data.unavailable_reason', null);
    }

    public function test_the_public_queue_view_reports_a_full_queue(): void
    {
        $this->queue->forceFill(['capacity' => 1])->save();
        $this->ticketIn($this->session);

        $this->getJson($this->publicUri())
            ->assertJsonPath('data.accepting_joins', false)
            ->assertJsonPath('data.is_full', true);
    }

    // ----- Status checks on the backend -----------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unavailableParents(): array
    {
        return [
            'inactive business' => ['business', 'business_unavailable'],
            'inactive branch' => ['branch', 'branch_inactive'],
            'inactive service' => ['service', 'service_unavailable'],
        ];
    }

    #[DataProvider('unavailableParents')]
    public function test_nobody_can_join_while_the_business_branch_or_service_is_inactive(string $parent, string $code): void
    {
        $this->setStatus($this->{$parent}, 'inactive');

        $this->assertApiError($this->actingAsUser(User::factory()->create(), 'POST', $this->queueUri($this->queue, 'join')), 409, $code);

        $this->forgetAuthenticatedUser();
        $this->flushHeaders();
        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 409, $code);

        $token = $this->queue->public_token_encrypted;
        $this->assertApiError($this->postJson('/api/v1/public/queues/'.$token.'/join', ['name' => 'Rami']), 409, $code);

        $this->assertSame(0, QueueTicket::query()->count());
    }

    #[DataProvider('unavailableParents')]
    public function test_the_public_view_agrees_with_the_join_guard(string $parent, string $reason): void
    {
        $this->setStatus($this->{$parent}, 'inactive');

        $this->getJson($this->publicUri())
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.unavailable_reason', $reason)
            ->assertJsonPath('data.accepting_joins', false);
    }

    public function test_nobody_can_join_a_deleted_service(): void
    {
        $this->service->delete();

        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 409, 'service_unavailable');
    }

    public function test_a_paused_closed_or_full_queue_refuses_with_its_own_code(): void
    {
        $this->session->forceFill(['status' => 'paused'])->save();
        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 409, 'queue_paused');

        $this->session->forceFill(['status' => 'open'])->save();
        $this->queue->forceFill(['capacity' => 1])->save();
        $this->ticketIn($this->session);
        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 409, 'queue_full');

        $this->session->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Rami']), 409, 'queue_closed');
    }

    // ----- Signed-in customers --------------------------------------------

    public function test_a_customer_joins_from_the_app_and_cannot_join_twice(): void
    {
        $customer = User::factory()->create();

        $this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join'))
            ->assertCreated()
            ->assertJsonPath('data.business.slug', $this->business->slug)
            ->assertJsonPath('data.branch.city', 'Damascus')
            ->assertJsonPath('data.can_review', false)
            ->assertJsonMissingPath('data.access_token');

        $this->assertApiError($this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join')), 409, 'duplicate_active_ticket');
    }

    public function test_tickets_can_be_filtered_into_active_and_history(): void
    {
        $customer = User::factory()->create();
        $mine = ['user_id' => $customer->getKey()];
        $this->ticketIn($this->session, TicketStatus::Waiting, $mine);
        $this->ticketIn($this->session, TicketStatus::Completed, $mine);
        $this->ticketIn($this->session, TicketStatus::Cancelled, $mine);
        $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => User::factory()->create()->getKey()]);

        $statuses = fn (string $query) => collect($this->actingAsUser($customer, 'GET', '/api/v1/me/tickets'.$query)->assertOk()->json('data'))
            ->pluck('status')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['cancelled', 'completed', 'waiting'], $statuses(''));
        $this->assertSame(['waiting'], $statuses('?group=active'));
        $this->assertSame(['cancelled', 'completed'], $statuses('?group=history'));
        $this->assertSame(['completed'], $statuses('?status=completed'));
        $this->assertSame(['cancelled', 'waiting'], $statuses('?status=waiting,cancelled'));
    }

    public function test_ticket_history_is_paginated(): void
    {
        $customer = User::factory()->create();
        foreach (range(1, 3) as $ignored) {
            $this->ticketIn($this->session, TicketStatus::Cancelled, ['user_id' => $customer->getKey()]);
        }

        $this->actingAsUser($customer, 'GET', '/api/v1/me/tickets?group=history&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_invalid_ticket_filters_are_rejected(): void
    {
        $customer = User::factory()->create();

        $this->actingAsUser($customer, 'GET', '/api/v1/me/tickets?status=nonsense')->assertStatus(422);
        $this->actingAsUser($customer, 'GET', '/api/v1/me/tickets?group=everything')->assertStatus(422);
    }

    public function test_tickets_of_other_customers_stay_out_of_reach(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => $owner->getKey()]);
        $other = User::factory()->create();
        $uri = '/api/v1/tickets/'.$ticket->public_id;

        $this->assertApiError($this->actingAsUser($other, 'GET', $uri.'/position'), 404, 'ticket_not_found');
        $this->assertApiError($this->actingAsUser($other, 'POST', $uri.'/cancel'), 404, 'ticket_not_found');
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);

        // A customer's ticket has no guest token, so the guest route cannot reach it either.
        $this->forgetAuthenticatedUser();
        $this->flushHeaders();
        $this->assertApiError($this->getJson('/api/v1/public/tickets/'.$ticket->public_id, ['X-Ticket-Token' => 'anything']), 404, 'ticket_not_found');
    }

    public function test_a_ticket_response_shows_nothing_about_other_customers(): void
    {
        $customer = User::factory()->create();
        $this->ticketIn($this->session, TicketStatus::Called, ['guest_name' => 'Someone Else', 'guest_phone' => '+963933111222']);
        $ticket = $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => $customer->getKey()]);

        $text = $this->actingAsUser($customer, 'GET', '/api/v1/tickets/'.$ticket->public_id.'/position')->assertOk()->getContent();

        $this->assertStringNotContainsString('Someone Else', $text);
        $this->assertStringNotContainsString('+963933111222', $text);
    }

    public function test_only_a_waiting_ticket_can_be_cancelled_by_its_holder(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->ticketIn($this->session, TicketStatus::Called, ['user_id' => $customer->getKey()]);

        $this->assertApiError($this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel'), 409, 'cancellation_not_allowed');
    }

    public function test_join_routes_stay_rate_limited_by_queue_id(): void
    {
        config(['dory.rate_limits.queue_join' => 1]);

        $this->postJson($this->publicUri('/join'), ['name' => 'Rami'])->assertCreated();

        $this->assertApiError($this->postJson($this->publicUri('/join'), ['name' => 'Lina']), 429, 'too_many_requests');
    }
}
