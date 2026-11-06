<?php

namespace Tests\Feature\Api\V1\Queues;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Events\QueueActivity;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class QueueSessionTest extends TestCase
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
        $this->branch = $this->branchOf($this->business, ['timezone' => 'Asia/Damascus']);
        $this->queue = $this->queueFor($this->serviceOf($this->branch), ['ticket_prefix' => 'BAR']);
    }

    // ----- Opening ------------------------------------------------------

    public function test_opening_starts_a_session_for_the_branch_business_day(): void
    {
        // 22:30 UTC is already the next day in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-11-06 22:30:00', 'UTC'));

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.business_date', '2026-11-07')
            ->assertJsonPath('data.session_number', 1)
            ->assertJsonPath('data.closed_at', null);

        $event = QueueEvent::query()->sole();
        $this->assertSame(QueueEventType::SessionOpened, $event->event_type);
        $this->assertSame($this->owner->getKey(), $event->actor_id);
        $this->assertSame('2026-11-07', $event->metadata['business_date']);
    }

    public function test_only_one_session_can_be_active(): void
    {
        $this->openSession($this->queue, $this->owner);

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $this->assertApiError($response, 409, 'session_already_active');
        $this->assertSame(1, QueueSession::query()->count());
    }

    public function test_the_database_rejects_a_second_active_session(): void
    {
        $session = $this->openSession($this->queue, $this->owner);

        $this->expectException(UniqueConstraintViolationException::class);

        $duplicate = $session->replicate(['public_id', 'active_key']);
        $duplicate->session_number = 2;
        $duplicate->save();
    }

    public function test_an_inactive_queue_cannot_be_opened(): void
    {
        $this->queue->update(['status' => 'inactive']);

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $this->assertApiError($response, 409, 'queue_inactive');
    }

    public function test_reopening_on_the_same_day_starts_the_next_session_number(): void
    {
        $this->openSession($this->queue, $this->owner);
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $response->assertCreated()->assertJsonPath('data.session_number', 2);
    }

    public function test_a_session_left_open_from_an_earlier_day_is_closed_and_its_waiting_tickets_expire(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Damascus'));
        $stale = $this->openSession($this->queue, $this->owner);
        $waiting = $this->ticketIn($stale);
        $serving = $this->ticketIn($stale, TicketStatus::Serving, ['served_by' => $this->owner->getKey()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00', 'Asia/Damascus'));
        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $response->assertCreated()
            ->assertJsonPath('data.business_date', '2026-11-06')
            ->assertJsonPath('data.session_number', 1);
        $stale->refresh();
        $this->assertSame('closed', $stale->status->value);
        $this->assertSame('day_ended', $stale->close_reason);
        $this->assertNull($stale->closed_by);
        $this->assertSame(TicketStatus::Expired, $waiting->fresh()->status);
        $this->assertNotNull($waiting->fresh()->ended_at);
        $this->assertSame(TicketStatus::Serving, $serving->fresh()->status);
        $this->assertTrue(QueueEvent::query()
            ->where('ticket_id', $waiting->getKey())
            ->where('event_type', QueueEventType::Expired->value)
            ->exists());
    }

    // ----- Pausing and resuming -----------------------------------------

    public function test_a_session_is_paused_and_resumed(): void
    {
        $this->openSession($this->queue, $this->owner);

        $paused = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'));
        $pausedAgain = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'));
        $resumed = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'resume'));
        $resumedAgain = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'resume'));

        $this->assertApiSuccess($paused)->assertJsonPath('data.status', 'paused');
        $this->assertNotNull($paused->json('data.paused_at'));
        $this->assertApiError($pausedAgain, 409, 'session_not_open');
        $this->assertApiSuccess($resumed)->assertJsonPath('data.status', 'open');
        $this->assertApiError($resumedAgain, 409, 'session_not_paused');
        $this->assertSame(
            ['session_opened', 'session_paused', 'session_resumed'],
            QueueEvent::query()->orderBy('id')->pluck('event_type')->map->value->all(),
        );
    }

    public function test_pausing_or_closing_without_a_session_is_rejected(): void
    {
        foreach (['pause', 'resume', 'close'] as $action) {
            $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, $action)), 409, 'no_active_session');
        }
    }

    // ----- Closing ------------------------------------------------------

    public function test_closing_cancels_waiting_tickets_and_keeps_customers_in_service(): void
    {
        $session = $this->openSession($this->queue, $this->owner);
        $first = $this->ticketIn($session);
        $second = $this->ticketIn($session);
        $called = $this->ticketIn($session, TicketStatus::Called, ['served_by' => $this->owner->getKey()]);
        $done = $this->ticketIn($session, TicketStatus::Completed);

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'));

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.close_reason', 'closed_by_staff');
        foreach ([$first, $second] as $ticket) {
            $ticket->refresh();
            $this->assertSame(TicketStatus::Cancelled, $ticket->status);
            $this->assertSame('queue_closed', $ticket->cancel_reason);
            $this->assertNotNull($ticket->ended_at);
        }
        $this->assertSame(TicketStatus::Called, $called->fresh()->status);
        $this->assertSame(TicketStatus::Completed, $done->fresh()->status);

        $closed = QueueEvent::query()->where('event_type', QueueEventType::SessionClosed->value)->sole();
        $this->assertSame(2, $closed->metadata['waiting_tickets_closed']);
        $this->assertSame(2, QueueEvent::query()->where('event_type', QueueEventType::Cancelled->value)->count());
        $this->actingAsUser($this->owner, 'GET', $this->queueUri($this->queue, 'current-session'))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_session_changes_announce_activity_after_commit(): void
    {
        Event::fake([QueueActivity::class]);

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();

        Event::assertDispatched(QueueActivity::class, fn (QueueActivity $event) => $event->queueId === $this->queue->public_id
            && $event->type === QueueEventType::SessionOpened);
    }

    // ----- Reading ------------------------------------------------------

    public function test_the_current_session_and_history_are_listed(): void
    {
        $first = $this->openSession($this->queue, $this->owner);
        $this->ticketIn($first);
        $this->ticketIn($first);
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
        $second = $this->openSession($this->queue, $this->owner);

        $current = $this->actingAsUser($this->owner, 'GET', $this->queueUri($this->queue, 'current-session'));
        $history = $this->actingAsUser($this->owner, 'GET', $this->queueUri($this->queue, 'sessions'));

        $this->assertApiSuccess($current)->assertJsonPath('data.id', $second->public_id);
        $this->assertApiSuccess($history)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->public_id)
            ->assertJsonPath('data.0.tickets_count', 0)
            ->assertJsonPath('data.1.id', $first->public_id)
            ->assertJsonPath('data.1.status', 'closed')
            ->assertJsonPath('data.1.tickets_count', 2);
    }

    // ----- Permissions --------------------------------------------------

    public function test_assigned_employees_operate_the_queue_and_others_cannot(): void
    {
        $assigned = $this->memberOf($this->business, BusinessRole::Employee);
        $unassigned = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $assigned, $this->owner);

        $this->assertApiError($this->actingAsUser($unassigned, 'POST', $this->queueUri($this->queue, 'open')), 403, 'forbidden');
        $this->actingAsUser($assigned, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();
        $this->assertApiError($this->actingAsUser($unassigned, 'POST', $this->queueUri($this->queue, 'close')), 403, 'forbidden');
        $this->actingAsUser($unassigned, 'GET', $this->queueUri($this->queue, 'current-session'))->assertOk();
        $this->actingAsUser($assigned, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();
    }

    public function test_managers_operate_queues_of_their_branches(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->branch]);

        $this->actingAsUser($manager, 'POST', $this->queueUri($this->queue, 'open'))->assertCreated();
    }

    public function test_outsiders_cannot_find_the_queue(): void
    {
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);
        $otherBranchManager = $this->memberOf(
            $this->business,
            BusinessRole::Manager,
            BranchAccess::Selected,
            [$this->branchOf($this->business)],
        );

        foreach ([$stranger, $otherBranchManager] as $user) {
            foreach (['open', 'current-session', 'sessions'] as $action) {
                $method = $action === 'open' ? 'POST' : 'GET';
                $this->assertApiError($this->actingAsUser($user, $method, $this->queueUri($this->queue, $action)), 404, 'queue_not_found');
            }
        }
        $this->assertSame(0, QueueSession::query()->count());
    }

    public function test_a_suspended_business_cannot_operate_queues(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $response = $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'open'));

        $this->assertApiError($response, 403, 'business_suspended');
    }

    public function test_session_endpoints_require_authentication(): void
    {
        $this->assertApiError($this->postJson($this->queueUri($this->queue, 'open')), 401, 'unauthenticated');
    }
}
