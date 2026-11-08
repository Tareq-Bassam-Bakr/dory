<?php

namespace Tests\Feature\Notifications;

use App\Enums\TicketStatus;
use App\Jobs\SendPushMessage;
use App\Models\PushDevice;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Push\PushDeviceRegistry;
use App\Services\Push\PushOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\Fakes\FakePushSender;
use Tests\TestCase;

/**
 * Push device registration (docs/DEVICE_TOKEN_SECURITY.md).
 */
class PushDevicesTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private const TOKEN = 'fcm-token-AAAAAAAAAA:BBBBBBBBBBBBBBBBBBBBBBBBBBBB_cccc-dddd';

    private Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $this->queue = $this->queueFor($this->serviceOf($this->branchOf($this->businessOwnedBy($owner))));
        $this->openSession($this->queue, $owner);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAsUser($user, 'POST', '/api/v1/devices/register', $overrides + [
            'token' => self::TOKEN,
            'platform' => 'android',
            'locale' => 'ar',
            'app_version' => '1.0.0',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function guestTicket(): array
    {
        $joined = $this->postJson('/api/v1/public/queues/'.$this->queue->public_token_encrypted.'/join', ['name' => 'Rami']);
        $joined->assertCreated();

        return [$joined->json('data.id'), $joined->json('data.access_token')];
    }

    // ----- Signed-in users --------------------------------------------------

    public function test_a_device_is_registered_with_its_token_encrypted(): void
    {
        $user = User::factory()->create();

        $response = $this->register($user);

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.locale', 'ar');
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertArrayNotHasKey('token', $response->json('data'));

        $row = DB::table('push_devices')->sole();
        $this->assertNotSame(self::TOKEN, $row->token_encrypted);
        $this->assertStringNotContainsString(self::TOKEN, $row->token_encrypted);
        $this->assertSame(hash('sha256', self::TOKEN), $row->token_hash);
        $this->assertSame(self::TOKEN, PushDevice::query()->sole()->token_encrypted);
        $this->assertSame($user->getKey(), $row->user_id);
    }

    public function test_registering_the_same_token_again_keeps_one_row(): void
    {
        $user = User::factory()->create();

        $first = $this->register($user)->json('data.id');
        $second = $this->register($user, ['locale' => 'en'])->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, PushDevice::query()->count());
        $this->assertSame('en', PushDevice::query()->sole()->locale);
    }

    public function test_a_refreshed_token_replaces_the_old_one(): void
    {
        $user = User::factory()->create();
        $old = $this->register($user)->json('data.id');

        $new = $this->register($user, ['token' => 'fcm-token-ROTATED-0000000000000000', 'replaces' => $old])->json('data.id');

        $this->assertNotSame($old, $new);
        $this->assertNotNull(PushDevice::query()->where('public_id', $old)->sole()->revoked_at);
        $this->assertNull(PushDevice::query()->where('public_id', $new)->sole()->revoked_at);
    }

    public function test_a_user_cannot_revoke_another_users_device_by_replacing_it(): void
    {
        $victim = User::factory()->create();
        $theirs = $this->register($victim)->json('data.id');

        $this->register(User::factory()->create(), ['token' => 'fcm-token-OTHER-00000000000000000', 'replaces' => $theirs])->assertCreated();

        $this->assertNull(PushDevice::query()->where('public_id', $theirs)->sole()->revoked_at);
    }

    public function test_a_phone_signing_in_to_another_account_stops_the_previous_one(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->register($first);

        $this->register($second);

        $this->assertSame(0, $first->pushDevices()->active()->count());
        $this->assertSame(1, $second->pushDevices()->active()->count());
    }

    public function test_users_unregister_their_own_devices_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = $this->register($owner)->json('data.id');

        $this->assertApiError(
            $this->actingAsUser($other, 'DELETE', '/api/v1/devices/'.$device.'/unregister'),
            404,
            'device_not_found',
        );
        $this->assertNull(PushDevice::query()->sole()->revoked_at);

        $this->assertApiSuccess($this->actingAsUser($owner, 'DELETE', '/api/v1/devices/'.$device.'/unregister'));
        $this->assertNotNull(PushDevice::query()->sole()->revoked_at);
    }

    public function test_registration_validates_and_requires_an_account(): void
    {
        $user = User::factory()->create();

        $this->assertApiError($this->register($user, ['token' => 'has spaces in it and more than twenty']), 422, 'validation_failed');
        $this->assertApiError($this->register($user, ['platform' => 'windows']), 422, 'validation_failed');
        $this->forgetAuthenticatedUser();
        $this->withoutToken()->postJson('/api/v1/devices/register', ['token' => self::TOKEN, 'platform' => 'android'])->assertUnauthorized();
        $this->assertSame(0, PushDevice::query()->count());
    }

    public function test_the_raw_token_never_reaches_the_logs(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message->message.' '.json_encode($message->context);
        });
        $user = User::factory()->create();
        $this->register($user);
        $this->register($user, ['token' => 'bad token']);
        $device = PushDevice::query()->sole();

        // The paths that do log: FCM refusing a message, then the token.
        foreach ([PushOutcome::Failed, PushOutcome::InvalidToken] as $outcome) {
            (new SendPushMessage($device->getKey(), 'T', 'B', ['kind' => 'ticket_called'], true, 'k'))
                ->handle(new FakePushSender($outcome), app(PushDeviceRegistry::class));
        }

        $this->assertCount(2, $logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString(self::TOKEN, $line);
            $this->assertStringNotContainsString(hash('sha256', self::TOKEN), $line);
            $this->assertStringContainsString($device->public_id, $line);
        }
    }

    // ----- Guests -----------------------------------------------------------

    public function test_a_guest_registers_a_device_with_the_ticket_token_header(): void
    {
        [$id, $token] = $this->guestTicket();

        $response = $this->postJson('/api/v1/public/tickets/'.$id.'/devices/register', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['X-Ticket-Token' => $token]);

        $this->assertApiSuccess($response, 201);
        $device = PushDevice::query()->sole();
        $this->assertNull($device->user_id);
        $this->assertSame(QueueTicket::query()->where('public_id', $id)->sole()->getKey(), $device->queue_ticket_id);
    }

    public function test_a_guest_device_needs_the_right_ticket_token(): void
    {
        [$id, $token] = $this->guestTicket();
        $data = ['token' => self::TOKEN, 'platform' => 'android'];

        $this->assertApiError($this->postJson('/api/v1/public/tickets/'.$id.'/devices/register', $data), 404, 'ticket_not_found');
        $this->assertApiError(
            $this->postJson('/api/v1/public/tickets/'.$id.'/devices/register', $data, ['X-Ticket-Token' => str_repeat('x', 48)]),
            404,
            'ticket_not_found',
        );
        // A token in the URL is not accepted either.
        $this->assertApiError(
            $this->postJson('/api/v1/public/tickets/'.$id.'/devices/register?token='.$token, $data),
            404,
            'ticket_not_found',
        );
        $this->assertSame(0, PushDevice::query()->count());
    }

    public function test_a_finished_guest_ticket_takes_no_new_devices(): void
    {
        [$id, $token] = $this->guestTicket();
        QueueTicket::query()->where('public_id', $id)->update(['status' => TicketStatus::Cancelled->value]);

        $this->assertApiError(
            $this->postJson('/api/v1/public/tickets/'.$id.'/devices/register', [
                'token' => self::TOKEN,
                'platform' => 'android',
            ], ['X-Ticket-Token' => $token]),
            422,
            'ticket_finished',
        );
    }

    public function test_a_guest_removes_only_devices_of_their_own_ticket(): void
    {
        [$firstId, $firstToken] = $this->guestTicket();
        [$secondId, $secondToken] = $this->guestTicket();
        $mine = $this->postJson('/api/v1/public/tickets/'.$firstId.'/devices/register', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['X-Ticket-Token' => $firstToken])->json('data.id');

        $this->assertApiError(
            $this->deleteJson('/api/v1/public/tickets/'.$secondId.'/devices/unregister', ['device' => $mine], ['X-Ticket-Token' => $secondToken]),
            404,
            'device_not_found',
        );
        $this->assertApiSuccess(
            $this->deleteJson('/api/v1/public/tickets/'.$firstId.'/devices/unregister', ['device' => $mine], ['X-Ticket-Token' => $firstToken]),
        );
        $this->assertNotNull(PushDevice::query()->sole()->revoked_at);
    }
}
