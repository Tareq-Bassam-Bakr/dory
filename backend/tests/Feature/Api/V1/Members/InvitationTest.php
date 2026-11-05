<?php

namespace Tests\Feature\Api\V1\Members;

use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Notifications\BusinessInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'owner@shifa.example']);
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function uri(?Business $business = null): string
    {
        return '/api/v1/businesses/'.($business ?? $this->business)->public_id.'/invitations';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function invite(array $payload = [], ?User $as = null): TestResponse
    {
        return $this->actingAsUser($as ?? $this->owner, 'POST', $this->uri(), $payload + [
            'email' => 'Sara@Example.com',
            'role' => 'employee',
        ]);
    }

    private static function tokenFrom(string $shareUrl): string
    {
        return substr($shareUrl, -64);
    }

    public function test_the_owner_invites_a_team_member(): void
    {
        $response = $this->invite();

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.invitation.email', 'sara@example.com')
            ->assertJsonPath('data.invitation.role', 'employee')
            ->assertJsonPath('data.invitation.branch_access', 'all')
            ->assertJsonPath('data.invitation.status', 'pending')
            ->assertJsonPath('data.invitation.invited_by.name', $this->owner->name)
            ->assertJsonPath('data.delivery', ['channel' => 'email', 'status' => 'not_configured']);

        $shareUrl = $response->json('data.share_url');
        $this->assertMatchesRegularExpression('#^dory://app/invitations/[A-Za-z0-9]{64}$#', $shareUrl);

        $invitation = BusinessInvitation::query()->sole();
        $this->assertSame(hash('sha256', self::tokenFrom($shareUrl)), $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(1, BusinessAuditLog::query()->where('action', AuditAction::InvitationCreated->value)->count());
    }

    public function test_the_plain_token_is_never_stored(): void
    {
        $token = self::tokenFrom($this->invite()->json('data.share_url'));

        $row = (array) \DB::table('business_invitations')->first();
        $this->assertNotContains($token, $row);
        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertStringNotContainsString($token, json_encode(\DB::table('business_audit_logs')->get()));
    }

    public function test_tokens_are_unique_and_unpredictable(): void
    {
        $first = self::tokenFrom($this->invite(['email' => 'a@example.com'])->json('data.share_url'));
        $second = self::tokenFrom($this->invite(['email' => 'b@example.com'])->json('data.share_url'));

        $this->assertNotSame($first, $second);
    }

    public function test_no_share_link_is_returned_when_sharing_is_disabled(): void
    {
        config(['dory.invitations.share_links' => false]);

        $this->invite()->assertCreated()->assertJsonPath('data.share_url', null);
        $this->assertDatabaseCount('business_invitations', 1);
    }

    public function test_an_email_is_sent_only_with_a_real_mail_transport(): void
    {
        Notification::fake();
        config(['dory.invitations.send_emails' => true, 'mail.default' => 'log']);

        $this->invite(['email' => 'log@example.com'])->assertJsonPath('data.delivery.status', 'not_configured');
        Notification::assertNothingSent();

        config(['mail.default' => 'smtp']);
        $response = $this->invite(['email' => 'smtp@example.com']);

        $response->assertJsonPath('data.delivery.status', 'sent');
        Notification::assertSentTo(
            new AnonymousNotifiable,
            BusinessInvitationNotification::class,
            fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'smtp@example.com',
        );
    }

    public function test_a_failed_email_is_reported_without_leaking_the_token(): void
    {
        Mail::extend('broken', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused: '.$message->toString());
            }

            public function __toString(): string
            {
                return 'broken://';
            }
        });
        config([
            'dory.invitations.send_emails' => true,
            'mail.mailers.broken' => ['transport' => 'broken'],
            'mail.default' => 'broken',
        ]);
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event->message.json_encode($event->context);
        });

        $response = $this->invite();

        $this->assertApiSuccess($response, 201)->assertJsonPath('data.delivery.status', 'failed');
        $this->assertDatabaseCount('business_invitations', 1);
        $token = self::tokenFrom($response->json('data.share_url'));
        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString($token, $line);
        }
    }

    public function test_only_the_owner_can_invite(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->invite(as: $manager), 403, 'forbidden');
        $this->assertApiError($this->invite(as: $employee), 403, 'forbidden');
        $this->assertApiError($this->invite(as: User::factory()->create()), 404, 'business_not_found');
        $this->assertDatabaseCount('business_invitations', 0);
    }

    public function test_ownership_cannot_be_granted_by_invitation(): void
    {
        $this->assertApiError($this->invite(['role' => 'owner']), 422, 'validation_failed')->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('business_invitations', 0);
    }

    public function test_invalid_invitations_are_rejected(): void
    {
        $this->assertApiError($this->invite(['email' => 'not-an-email']), 422, 'validation_failed')->assertJsonValidationErrors('email');
        $this->assertApiError($this->invite(['email' => 'OWNER@shifa.example']), 422, 'validation_failed')->assertJsonValidationErrors('email');
        $this->assertApiError($this->invite(['role' => 'admin']), 422, 'validation_failed')->assertJsonValidationErrors('role');
        $this->assertApiError($this->invite(['status' => 'accepted']), 422, 'validation_failed')->assertJsonValidationErrors('status');
        $this->assertApiError($this->invite(['branch_access' => 'selected']), 422, 'validation_failed')->assertJsonValidationErrors('branches');
    }

    public function test_one_pending_invitation_per_email(): void
    {
        $this->invite()->assertCreated();

        $this->assertApiError($this->invite(['email' => 'sara@example.com']), 409, 'invitation_pending');
        $this->assertDatabaseCount('business_invitations', 1);
    }

    public function test_an_expired_invitation_no_longer_blocks_a_new_one(): void
    {
        $this->invite()->assertCreated();
        $this->travel(8)->days();

        $this->invite()->assertCreated();

        $this->assertSame(
            [InvitationStatus::Expired, InvitationStatus::Pending],
            BusinessInvitation::query()->orderBy('id')->get()->pluck('status')->all(),
        );
    }

    public function test_existing_members_cannot_be_invited_again(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        $this->assertApiError($this->invite(['email' => $employee->email]), 409, 'membership_exists');
    }

    public function test_selected_branches_must_belong_to_the_business(): void
    {
        $own = $this->branchOf($this->business);
        $foreign = $this->branchOf($this->businessOwnedBy(User::factory()->create(), ['name' => 'Other']));

        $this->assertApiError(
            $this->invite(['branch_access' => 'selected', 'branches' => [$own->public_id, $foreign->public_id]]),
            422,
            'validation_failed',
        )->assertJsonValidationErrors('branches');

        $this->invite(['branch_access' => 'selected', 'branches' => [$own->public_id]])
            ->assertCreated()
            ->assertJsonPath('data.invitation.branches', [['id' => $own->public_id, 'name' => $own->name]]);
        $this->assertSame([$own->id], BusinessInvitation::query()->sole()->branch_ids);
    }

    public function test_owners_and_managers_list_open_invitations_without_tokens(): void
    {
        $this->invite(['email' => 'one@example.com']);
        $this->invite(['email' => 'two@example.com']);
        BusinessInvitation::factory()->for($this->business)->accepted()->create();
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $employee = $this->memberOf($this->business, BusinessRole::Employee, BranchAccess::All);

        $response = $this->actingAsUser($manager, 'GET', $this->uri());

        $this->assertApiSuccess($response)->assertJsonPath('meta.pagination.total', 2);
        $this->assertSame(['two@example.com', 'one@example.com'], $response->json('data.*.email'));
        $this->assertStringNotContainsString('token', $response->getContent());
        $this->actingAsUser($this->owner, 'GET', $this->uri().'?status=all')->assertJsonPath('meta.pagination.total', 3);
        $this->assertApiError($this->actingAsUser($employee, 'GET', $this->uri()), 403, 'forbidden');
    }

    public function test_the_owner_revokes_an_invitation(): void
    {
        $this->invite()->assertCreated();
        $invitation = BusinessInvitation::query()->sole();
        $revokeUri = $this->uri().'/'.$invitation->public_id;

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'DELETE', $revokeUri));
        $this->assertSame(InvitationStatus::Revoked, $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->pending_key);
        $this->assertApiError($this->actingAsUser($this->owner, 'DELETE', $revokeUri), 409, 'invitation_not_pending');

        // A revoked invitation frees the email for a new one.
        $this->invite()->assertCreated();
    }

    public function test_invitations_of_another_business_cannot_be_revoked(): void
    {
        $otherOwner = User::factory()->create();
        $other = $this->businessOwnedBy($otherOwner, ['name' => 'Other']);
        $foreign = BusinessInvitation::factory()->for($other)->create();

        $response = $this->actingAsUser($this->owner, 'DELETE', $this->uri().'/'.$foreign->public_id);

        $this->assertApiError($response, 404, 'invitation_not_found');
        $this->assertSame(InvitationStatus::Pending, $foreign->fresh()->status);
    }

    public function test_managers_cannot_revoke(): void
    {
        $invitation = BusinessInvitation::factory()->for($this->business)->create();
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->assertApiError($this->actingAsUser($manager, 'DELETE', $this->uri().'/'.$invitation->public_id), 403, 'forbidden');
    }
}
