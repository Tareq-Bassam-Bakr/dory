<?php

namespace Tests\Feature\Api\V1\Members;

use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;
use Throwable;

class InvitationAcceptanceTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private User $invitee;

    private string $token;

    private BusinessInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Dr. Lina']);
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic']);
        $this->invitee = User::factory()->create(['email' => 'sara.k@example.com']);
        $this->token = Str::random(64);
        $this->invitation = $this->invitationFor('sara.k@example.com', BusinessRole::Manager);
    }

    private function invitationFor(string $email, BusinessRole $role, array $attributes = [], ?string $token = null): BusinessInvitation
    {
        return BusinessInvitation::factory()
            ->for($this->business)
            ->withToken($token ?? $this->token)
            ->create(['email' => $email, 'role' => $role, 'invited_by' => $this->owner->id] + $attributes);
    }

    private function uri(string $suffix = ''): string
    {
        return '/api/v1/invitations/'.$this->token.$suffix;
    }

    // ----- Preview -------------------------------------------------------

    public function test_anyone_with_the_link_sees_what_it_is_about(): void
    {
        $response = $this->getJson($this->uri());

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.business.name', 'Shifa Clinic')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.email', 'sa****@example.com')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.invited_by.name', 'Dr. Lina')
            ->assertJsonPath('data.can_respond', null);
    }

    public function test_a_signed_in_viewer_learns_whether_they_can_respond(): void
    {
        $this->actingAsUser($this->invitee, 'GET', $this->uri())->assertJsonPath('data.can_respond', true);
        $this->actingAsUser(User::factory()->create(), 'GET', $this->uri())->assertJsonPath('data.can_respond', false);
    }

    public function test_unknown_tokens_are_not_found(): void
    {
        $this->assertApiError($this->getJson('/api/v1/invitations/'.Str::random(64)), 404, 'invitation_not_found');
        $this->assertApiError($this->getJson('/api/v1/invitations/short-token'), 404, 'not_found');
    }

    public function test_an_expired_invitation_shows_as_expired(): void
    {
        $this->travel(8)->days();

        $this->getJson($this->uri())->assertOk()->assertJsonPath('data.status', 'expired');
    }

    // ----- Accepting -----------------------------------------------------

    public function test_the_invited_user_accepts_and_joins_the_team(): void
    {
        $response = $this->actingAsUser($this->invitee, 'POST', $this->uri('/accept'));

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.id', $this->business->public_id)
            ->assertJsonPath('data.membership.role', 'manager');

        $membership = $this->membershipOf($this->invitee, $this->business);
        $this->assertSame(BusinessRole::Manager, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $invitation = $this->invitation->fresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);
        $this->assertSame($this->invitee->id, $invitation->accepted_by);
        $this->assertNull($invitation->pending_key);
        $this->assertSame(1, BusinessAuditLog::query()->where('action', AuditAction::InvitationAccepted->value)->count());

        $this->actingAsUser($this->invitee, 'GET', '/api/v1/auth/me')
            ->assertJsonPath('data.memberships.0.business.id', $this->business->public_id);
    }

    public function test_accepting_requires_authentication(): void
    {
        $this->assertApiError($this->postJson($this->uri('/accept')), 401, 'unauthenticated');
        $this->assertNull($this->membershipOf($this->invitee, $this->business));
    }

    public function test_an_invitation_is_single_use(): void
    {
        $this->actingAsUser($this->invitee, 'POST', $this->uri('/accept'))->assertOk();

        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 409, 'invitation_not_pending');
        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/decline')), 409, 'invitation_not_pending');
        $this->assertSame(2, $this->business->members()->count());
    }

    public function test_another_account_cannot_use_the_link(): void
    {
        $intruder = User::factory()->create();

        $this->assertApiError($this->actingAsUser($intruder, 'POST', $this->uri('/accept')), 403, 'invitation_email_mismatch');
        $this->assertApiError($this->actingAsUser($intruder, 'POST', $this->uri('/decline')), 403, 'invitation_email_mismatch');
        $this->assertNull($this->membershipOf($intruder, $this->business));
        $this->assertSame(InvitationStatus::Pending, $this->invitation->fresh()->status);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 410, 'invitation_expired');
        $this->assertNull($this->membershipOf($this->invitee, $this->business));
    }

    public function test_a_revoked_invitation_cannot_be_accepted(): void
    {
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/businesses/'.$this->business->public_id.'/invitations/'.$this->invitation->public_id)
            ->assertOk();

        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 409, 'invitation_not_pending');
        $this->assertNull($this->membershipOf($this->invitee, $this->business));
    }

    public function test_declining_closes_the_invitation(): void
    {
        $this->assertApiSuccess($this->actingAsUser($this->invitee, 'POST', $this->uri('/decline')));

        $this->assertSame(InvitationStatus::Declined, $this->invitation->fresh()->status);
        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 409, 'invitation_not_pending');
        $this->assertNull($this->membershipOf($this->invitee, $this->business));
    }

    public function test_someone_who_already_joined_cannot_join_twice(): void
    {
        app(BusinessMembershipService::class)
            ->addMember($this->business, $this->invitee, BusinessRole::Employee);

        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 409, 'membership_exists');
        $this->assertSame(BusinessRole::Employee, $this->membershipOf($this->invitee, $this->business)->role);
        $this->assertSame(InvitationStatus::Pending, $this->invitation->fresh()->status);
    }

    public function test_selected_branch_access_carries_over_but_only_for_live_branches(): void
    {
        $kept = $this->branchOf($this->business, ['name' => 'Kept']);
        $removed = $this->branchOf($this->business, ['name' => 'Removed']);
        $other = $this->branchOf($this->business, ['name' => 'Other']);
        $token = Str::random(64);
        $invitee = User::factory()->create(['email' => 'branch.lead@example.com']);
        $this->invitationFor('branch.lead@example.com', BusinessRole::Manager, [
            'branch_access' => BranchAccess::Selected,
            'branch_ids' => [$kept->id, $removed->id],
        ], $token);
        $removed->delete();

        $this->actingAsUser($invitee, 'POST', '/api/v1/invitations/'.$token.'/accept')->assertOk();

        $membership = $this->membershipOf($invitee, $this->business);
        $this->assertSame(BranchAccess::Selected, $membership->branch_access);
        $this->assertSame([$kept->id], $membership->branches()->pluck('branches.id')->all());
        $this->actingAsUser($invitee, 'GET', '/api/v1/branches/'.$kept->public_id)->assertOk();
        $this->assertApiError($this->actingAsUser($invitee, 'GET', '/api/v1/branches/'.$other->public_id), 404, 'branch_not_found');
    }

    public function test_invitations_of_a_deleted_business_are_dead(): void
    {
        $this->actingAsUser($this->owner, 'DELETE', '/api/v1/businesses/'.$this->business->public_id)->assertOk();

        $this->assertApiError($this->getJson($this->uri()), 404, 'invitation_not_found');
        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 404, 'invitation_not_found');
        $this->assertSame(InvitationStatus::Revoked, $this->invitation->fresh()->status);
    }

    public function test_tokens_never_reach_the_logs_even_when_a_request_fails(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $exception = $event->context['exception'] ?? null;
            $logged[] = $event->message.json_encode($event->context)
                .($exception instanceof Throwable ? $exception->getMessage().$exception->getTraceAsString() : '');
        });
        $this->assertApiError($this->actingAsUser(User::factory()->create(), 'POST', $this->uri('/accept')), 403, 'invitation_email_mismatch');

        // Server errors are logged with a stack trace. Fail inside the token
        // lookup, where the token is a function argument, and while accepting.
        BusinessMember::creating(fn () => throw new RuntimeException('Simulated failure while accepting'));
        $this->assertApiError($this->actingAsUser($this->invitee, 'POST', $this->uri('/accept')), 500, 'server_error');
        BusinessInvitation::retrieved(fn () => throw new RuntimeException('Simulated failure during token lookup'));
        $this->assertApiError($this->getJson($this->uri()), 500, 'server_error');

        $log = implode("\n", $logged);
        $this->assertStringContainsString('Simulated failure while accepting', $log);
        $this->assertStringContainsString('Simulated failure during token lookup', $log);
        $this->assertStringContainsString('invitationByToken', $log, 'The trace must include the lookup frame.');
        // PHP truncates string arguments in traces to 15 characters: check the prefix.
        $this->assertStringNotContainsString(substr($this->token, 0, 15), $log);
    }

    public function test_a_branch_route_cannot_be_reached_through_an_invitation_token(): void
    {
        // A token belongs to one invitation; it is no key to anything else.
        $branch = $this->branchOf($this->business);

        $this->assertApiError(
            $this->actingAsUser($this->invitee, 'GET', '/api/v1/branches/'.$branch->public_id),
            404,
            'branch_not_found',
        );
        $this->assertInstanceOf(Branch::class, $branch->fresh());
    }
}
