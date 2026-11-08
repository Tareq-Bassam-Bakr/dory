<?php

namespace Tests\Feature\Realtime;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Private channel authorization (routes/channels.php) through the real
 * endpoint, POST /api/v1/broadcasting/auth, with a Reverb broadcaster, so
 * the signature is the one Reverb checks.
 */
class BroadcastChannelsTest extends TestCase
{
    use BuildsQueues, BuildsTenants, RefreshDatabase;

    private const KEY = 'test-app-key';

    private const SECRET = 'test-app-secret';

    private const SOCKET = '1234.5678';

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
        $this->branch = $this->branchOf($this->business);
        $this->queue = $this->queueFor($this->serviceOf($this->branch));
        $this->session = $this->openSession($this->queue, $this->owner);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => self::KEY,
            'broadcasting.connections.reverb.secret' => self::SECRET,
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);
        // Fixtures first (they broadcast through the null driver), then the
        // Reverb driver. The rules were registered on the default driver at
        // boot: register them again on the Reverb driver.
        require base_path('routes/channels.php');
    }

    private function authorize(?User $user, string $channel): TestResponse
    {
        $data = ['socket_id' => self::SOCKET, 'channel_name' => $channel];

        return $user === null
            ? $this->postJson('/api/v1/broadcasting/auth', $data)
            : $this->actingAsUser($user, 'POST', '/api/v1/broadcasting/auth', $data);
    }

    private function assertAllowed(TestResponse $response, string $channel): void
    {
        $response->assertOk();
        $expected = self::KEY.':'.hash_hmac('sha256', self::SOCKET.':'.$channel, self::SECRET);
        $this->assertSame($expected, $response->json('auth'));
    }

    private function sessionChannel(): string
    {
        return 'private-queue-sessions.'.$this->session->public_id;
    }

    private function businessChannel(?Business $business = null): string
    {
        return 'private-businesses.'.($business ?? $this->business)->public_id;
    }

    // ----- Users and tickets ----------------------------------------------

    public function test_a_user_may_listen_to_their_own_channel_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertAllowed($this->authorize($user, 'private-users.'.$user->public_id), 'private-users.'.$user->public_id);
        $this->authorize($user, 'private-users.'.$other->public_id)->assertForbidden();
    }

    public function test_a_customer_may_listen_to_their_own_ticket_only(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => $customer->getKey()]);
        $theirs = $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => $other->getKey()]);
        $guest = $this->ticketIn($this->session);

        $this->assertAllowed($this->authorize($customer, 'private-tickets.'.$mine->public_id), 'private-tickets.'.$mine->public_id);
        $this->authorize($customer, 'private-tickets.'.$theirs->public_id)->assertForbidden();
        $this->authorize($customer, 'private-tickets.'.$guest->public_id)->assertForbidden();
        $this->authorize($customer, 'private-tickets.01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertForbidden();
    }

    public function test_the_business_cannot_listen_to_a_customer_ticket_channel(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->ticketIn($this->session, TicketStatus::Waiting, ['user_id' => $customer->getKey()]);

        $this->authorize($this->owner, 'private-tickets.'.$ticket->public_id)->assertForbidden();
    }

    public function test_channels_need_a_signed_in_user(): void
    {
        $this->authorize(null, 'private-users.'.$this->owner->public_id)->assertUnauthorized();
        $this->authorize(null, $this->sessionChannel())->assertUnauthorized();
    }

    // ----- Queue sessions -------------------------------------------------

    public function test_the_owner_and_managers_who_reach_the_branch_may_listen_to_a_session(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->branch]);

        $this->assertAllowed($this->authorize($this->owner, $this->sessionChannel()), $this->sessionChannel());
        $this->assertAllowed($this->authorize($manager, $this->sessionChannel()), $this->sessionChannel());
        $this->assertAllowed($this->authorize($branchManager, $this->sessionChannel()), $this->sessionChannel());
    }

    public function test_employees_may_listen_only_to_queues_they_are_assigned_to(): void
    {
        $assigned = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $assigned, $this->owner);
        $notAssigned = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertAllowed($this->authorize($assigned, $this->sessionChannel()), $this->sessionChannel());
        $this->authorize($notAssigned, $this->sessionChannel())->assertForbidden();
    }

    public function test_a_manager_of_another_branch_may_not_listen_to_the_session(): void
    {
        $otherBranch = $this->branchOf($this->business);
        $manager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$otherBranch]);

        $this->authorize($manager, $this->sessionChannel())->assertForbidden();
    }

    public function test_another_business_may_not_listen_to_the_session(): void
    {
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);

        $this->authorize($stranger, $this->sessionChannel())->assertForbidden();
        $this->authorize(User::factory()->create(), $this->sessionChannel())->assertForbidden();
        $this->authorize($this->owner, 'private-queue-sessions.01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertForbidden();
    }

    public function test_a_suspended_membership_loses_the_session_channel(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $this->assignToQueue($this->queue, $employee, $this->owner);
        $membership = $this->membershipOf($employee, $this->business);
        $membership->forceFill(['status' => MembershipStatus::Suspended])->save();

        $this->authorize($employee, $this->sessionChannel())->assertForbidden();
    }

    // ----- Businesses -----------------------------------------------------

    public function test_the_business_channel_is_for_the_owner_and_managers_of_every_branch(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $branchManager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$this->branch]);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertAllowed($this->authorize($this->owner, $this->businessChannel()), $this->businessChannel());
        $this->assertAllowed($this->authorize($manager, $this->businessChannel()), $this->businessChannel());
        // A branch manager would see other branches' activity there.
        $this->authorize($branchManager, $this->businessChannel())->assertForbidden();
        $this->authorize($employee, $this->businessChannel())->assertForbidden();
    }

    public function test_an_owner_may_not_listen_to_another_business(): void
    {
        $stranger = User::factory()->create();
        $theirs = $this->businessOwnedBy($stranger);

        $this->authorize($this->owner, $this->businessChannel($theirs))->assertForbidden();
        $this->assertAllowed($this->authorize($stranger, $this->businessChannel($theirs)), $this->businessChannel($theirs));
    }
}
