<?php

namespace Tests\Feature\Notifications;

use App\Enums\BusinessRole;
use App\Enums\DevicePlatform;
use App\Enums\NotificationKind;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Jobs\ProcessQueueNotifications;
use App\Jobs\SendPushMessage;
use App\Models\Business;
use App\Models\NotificationPreference;
use App\Models\PushDevice;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Notifications\QueueNotificationPlanner;
use App\Services\Push\PushDeviceRegistry;
use App\Services\Push\PushOutcome;
use App\Services\Push\PushSender;
use App\Services\Queues\TicketStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue as QueueFacade;
use RuntimeException;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\Fakes\FakePushSender;
use Tests\TestCase;

/**
 * Who is told what about each queue change (docs/NOTIFICATION_EVENTS.md),
 * at most once, after the change was committed.
 */
class QueueNotificationsTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private User $owner;

    private User $employee;

    private Business $business;

    private Queue $queue;

    private QueueSession $session;

    private FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dory.push.enabled' => true, 'dory.notifications.almost_turn_people_ahead' => 2]);
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic']);
        $this->queue = $this->queueFor($this->serviceOf($this->branchOf($this->business)), [
            'name' => 'General',
            'ticket_prefix' => 'A',
            'grace_period_seconds' => 60,
        ]);
        $this->employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $this->employee, $this->owner);
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    private function join(User $customer): QueueTicket
    {
        $response = $this->actingAsUser($customer, 'POST', $this->queueUri($this->queue, 'join'));
        $response->assertCreated();

        return QueueTicket::query()->where('public_id', $response->json('data.id'))->sole();
    }

    private function staff(string $action, QueueTicket $ticket): void
    {
        $this->actingAsUser($this->owner, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/'.$action)->assertOk();
    }

    /**
     * @return list<string>
     */
    private function kinds(User $user): array
    {
        return $user->notifications()->orderBy('created_at')->orderBy('id')->pluck('type')->all();
    }

    private function registerDevice(User $user, string $token, string $locale = 'en'): PushDevice
    {
        return app(PushDeviceRegistry::class)->registerForUser($user, [
            'token' => $token,
            'platform' => DevicePlatform::Android,
            'locale' => $locale,
            'app_version' => '1.0.0',
            'replaces' => null,
        ]);
    }

    // ----- The ticket holder ------------------------------------------------

    public function test_the_customer_is_told_when_called_served_and_completed(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->join($customer);

        $this->staff('call', $ticket);
        $this->staff('start', $ticket);
        $this->staff('complete', $ticket);

        // Same second: compare as a set.
        $this->assertEqualsCanonicalizing(['ticket_called', 'ticket_service_started', 'ticket_completed'], $this->kinds($customer));
        $data = $customer->notifications()->where('type', 'ticket_called')->sole()->data;
        $this->assertSame($ticket->public_id, $data['ticket']['id']);
        $this->assertSame($ticket->ticket_number, $data['ticket']['number']);
        $this->assertSame('Shifa Clinic', $data['business']['name']);
    }

    public function test_the_app_hears_new_notifications_on_the_users_channel(): void
    {
        // Faked first: the notification channel keeps the dispatcher it was
        // built with.
        Event::fake([BroadcastNotificationCreated::class]);
        $customer = User::factory()->create();
        $ticket = $this->join($customer);

        $this->staff('call', $ticket);

        // The name, channel and payload the app subscribes to.
        Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($customer, $ticket): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $event->broadcastAs() === 'notification.created'
                && $channels === ['private-users.'.$customer->public_id]
                && array_keys($event->broadcastWith()) === ['kind', 'ticket', 'id', 'type']
                && $event->broadcastWith()['kind'] === 'ticket_called'
                && $event->broadcastWith()['ticket'] === $ticket->public_id;
        });
    }

    public function test_skipped_and_no_show_customers_are_told(): void
    {
        $skipped = User::factory()->create();
        $missed = User::factory()->create();
        $skippedTicket = $this->join($skipped);
        $missedTicket = $this->join($missed);

        $this->staff('skip', $skippedTicket);
        $this->staff('call', $missedTicket);
        $this->travel(5)->minutes();
        $this->staff('no-show', $missedTicket);

        $this->assertSame(['ticket_skipped'], $this->kinds($skipped));
        $this->assertContains('ticket_no_show', $this->kinds($missed));
    }

    public function test_customers_are_not_told_about_their_own_cancellation_but_staff_are(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->join($customer);
        $this->owner->notifications()->delete();
        $this->employee->notifications()->delete();

        $this->actingAsUser($customer, 'POST', '/api/v1/tickets/'.$ticket->public_id.'/cancel')->assertOk();

        $this->assertSame([], $this->kinds($customer));
        $this->assertSame(['staff_ticket_cancelled'], $this->kinds($this->owner));
        $this->assertSame(['staff_ticket_cancelled'], $this->kinds($this->employee));
    }

    public function test_a_cancellation_by_the_business_is_announced_to_the_customer(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->join($customer);
        $this->staff('call', $ticket);
        $this->staff('start', $ticket);

        DB::transaction(function () use ($ticket): void {
            $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->sole();
            app(TicketStateMachine::class)->transition($locked, TicketStatus::Cancelled, QueueEventType::Cancelled, $this->owner, ['reason' => 'staff']);
        });

        $this->assertContains('ticket_cancelled', $this->kinds($customer));
    }

    public function test_pausing_and_closing_the_queue_reach_everyone_still_in_it(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->join($first);
        $this->join($second);

        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'pause'))->assertOk();
        $this->actingAsUser($this->owner, 'POST', $this->queueUri($this->queue, 'close'))->assertOk();

        foreach ([$first, $second] as $customer) {
            $this->assertEqualsCanonicalizing(['queue_paused', 'queue_closed'], $this->kinds($customer));
        }
    }

    // ----- Staff ------------------------------------------------------------

    public function test_the_staff_who_run_the_queue_hear_about_new_customers(): void
    {
        $notAssigned = $this->memberOf($this->business, BusinessRole::Employee);
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);

        $ticket = $this->join(User::factory()->create());

        $this->assertSame(['staff_ticket_joined'], $this->kinds($this->owner));
        $this->assertSame(['staff_ticket_joined'], $this->kinds($this->employee));
        $this->assertSame([], $this->kinds($notAssigned));
        $this->assertSame([], $this->kinds($stranger));

        $data = $this->owner->notifications()->sole()->data;
        $this->assertSame($ticket->ticket_number, $data['ticket']['number']);
        $this->assertArrayNotHasKey('name', $data['ticket']);
    }

    // ----- Almost your turn -------------------------------------------------

    public function test_almost_your_turn_is_sent_once_to_tickets_within_the_threshold(): void
    {
        $customers = User::factory()->count(5)->create();
        $tickets = $customers->map(fn (User $customer) => $this->join($customer))->all();

        $this->staff('call', $tickets[0]);
        // Waiting now: 1 (0 ahead), 2 (1 ahead), 3 (2 ahead), 4 (3 ahead).
        foreach ([1 => 0, 2 => 1, 3 => 2] as $index => $ahead) {
            $alert = $customers[$index]->notifications()->where('type', 'ticket_almost_turn')->sole();
            $this->assertSame($ahead, $alert->data['people_ahead']);
        }
        $this->assertSame([], $this->kinds($customers[4]));

        // One customer at a time per staff member: finish the first.
        $this->staff('start', $tickets[0]);
        $this->staff('complete', $tickets[0]);
        $this->staff('call', $tickets[1]);

        // 2 and 3 were already told: nothing new. 4 moved within 2 places.
        $this->assertSame(1, $customers[2]->notifications()->where('type', 'ticket_almost_turn')->count());
        $this->assertSame(1, $customers[3]->notifications()->where('type', 'ticket_almost_turn')->count());
        $this->assertSame(['ticket_almost_turn'], $this->kinds($customers[4]));
    }

    // ----- At most once -----------------------------------------------------

    public function test_processing_the_same_change_again_sends_nothing_new(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->join($customer);
        $this->registerDevice($customer, str_repeat('t', 40).':device-one');
        $this->staff('call', $ticket);
        $pushes = count($this->push->sent);

        $event = QueueEvent::query()->where('event_type', QueueEventType::Called->value)->sole();
        (new ProcessQueueNotifications($event->getKey()))->handle(app(QueueNotificationPlanner::class));
        ProcessQueueNotifications::dispatchSync($event->getKey());

        $this->assertSame(['ticket_called'], $this->kinds($customer));
        $this->assertCount($pushes, $this->push->sent, 'A retried job pushed again.');
    }

    public function test_a_rolled_back_change_notifies_nobody(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->join($customer);

        try {
            DB::transaction(function () use ($ticket): void {
                $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->sole();
                app(TicketStateMachine::class)->transition($locked, TicketStatus::Called, QueueEventType::Called, $this->owner);
                throw new RuntimeException('Failure after the transition.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->kinds($customer));
    }

    // ----- Preferences and push ---------------------------------------------

    public function test_preferences_turn_groups_off_and_push_off(): void
    {
        $quiet = User::factory()->create();
        NotificationPreference::for($quiet)->fill(['ticket_updates' => false])->save();
        $inAppOnly = User::factory()->create();
        NotificationPreference::for($inAppOnly)->fill(['push_enabled' => false])->save();
        $this->registerDevice($inAppOnly, str_repeat('p', 40));

        $first = $this->join($quiet);
        $second = $this->join($inAppOnly);
        $this->staff('call', $first);
        $this->staff('start', $first);
        $this->staff('complete', $first);
        $this->staff('call', $second);

        $this->assertSame([], $this->kinds($quiet));
        $this->assertContains('ticket_called', $this->kinds($inAppOnly));
        $this->assertSame([], $this->push->sent);
    }

    public function test_pushes_reach_every_device_in_its_own_language(): void
    {
        $customer = User::factory()->create();
        $this->registerDevice($customer, str_repeat('e', 40), 'en');
        $this->registerDevice($customer, str_repeat('a', 40), 'ar');

        $ticket = $this->join($customer);
        $this->staff('call', $ticket);

        $called = array_values(array_filter($this->push->sent, fn ($message) => $message->data['kind'] === 'ticket_called'));
        $this->assertCount(2, $called);
        $byToken = collect($called)->keyBy('token');
        $this->assertSame("It's your turn", $byToken[str_repeat('e', 40)]->title);
        $this->assertSame('حان دورك', $byToken[str_repeat('a', 40)]->title);
        $this->assertTrue($byToken[str_repeat('e', 40)]->urgent);
        $this->assertSame(['kind', 'ticket', 'queue', 'guest', 'notification'], array_keys($called[0]->data));
        $this->assertSame($ticket->public_id, $called[0]->data['ticket']);
    }

    public function test_guests_get_pushes_on_their_ticket_devices_without_their_token(): void
    {
        $this->queue->forceFill(['allow_guest_join' => true])->save();
        $joined = $this->postJson('/api/v1/public/queues/'.$this->queue->public_token_encrypted.'/join', ['name' => 'Rami']);
        $joined->assertCreated();
        [$id, $token] = [$joined->json('data.id'), $joined->json('data.access_token')];
        $this->postJson('/api/v1/public/tickets/'.$id.'/devices/register', [
            'token' => str_repeat('g', 40),
            'platform' => 'android',
            'locale' => 'ar',
        ], ['X-Ticket-Token' => $token])->assertCreated();

        $this->staff('call', QueueTicket::query()->where('public_id', $id)->sole());

        $message = collect($this->push->sent)->firstWhere('data.kind', 'ticket_called');
        $this->assertNotNull($message);
        $this->assertSame('1', $message->data['guest']);
        $this->assertSame($id, $message->data['ticket']);
        $this->assertStringNotContainsString($token, json_encode([$message->title, $message->body, $message->data]));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_type', 'guest')->count());
    }

    public function test_nothing_is_pushed_while_push_is_off(): void
    {
        config(['dory.push.enabled' => false]);
        $customer = User::factory()->create();
        $this->registerDevice($customer, str_repeat('x', 40));

        $this->staff('call', $this->join($customer));

        $this->assertSame(['ticket_called'], $this->kinds($customer));
        $this->assertSame([], $this->push->sent);
    }

    public function test_push_failures_never_undo_the_queue_action(): void
    {
        QueueFacade::fake();
        $customer = User::factory()->create();
        $ticket = $this->join($customer);

        $this->staff('call', $ticket);

        // The change is committed and the work is queued for the worker.
        $this->assertSame(TicketStatus::Called, $ticket->fresh()->status);
        QueueFacade::assertPushedOn('notifications', ProcessQueueNotifications::class);

        // Later, FCM is down: the job retries; the ticket stays called.
        QueueFacade::fake([]);
        $this->push->outcome = PushOutcome::Retry;
        $device = $this->registerDevice($customer, str_repeat('r', 40));
        (new SendPushMessage($device->getKey(), 'T', 'B', ['kind' => 'ticket_called'], true, 'key'))
            ->handle($this->push, app(PushDeviceRegistry::class));
        $this->assertSame(TicketStatus::Called, $ticket->fresh()->status);
        $this->assertNull($device->fresh()->revoked_at);
    }

    public function test_an_invalid_token_revokes_the_device_everywhere(): void
    {
        $customer = User::factory()->create();
        $device = $this->registerDevice($customer, str_repeat('i', 40));
        $this->push->outcome = PushOutcome::InvalidToken;

        (new SendPushMessage($device->getKey(), 'T', 'B', ['kind' => 'ticket_called'], true, 'key'))
            ->handle($this->push, app(PushDeviceRegistry::class));

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_notification_kinds_match_their_preferences(): void
    {
        $this->assertSame('almost_turn', NotificationKind::TicketAlmostTurn->preference());
        $this->assertSame('queue_updates', NotificationKind::QueueClosed->preference());
        $this->assertSame('staff_new_tickets', NotificationKind::StaffTicketJoined->preference());
        $this->assertSame('ticket_updates', NotificationKind::TicketNoShow->preference());
    }
}
