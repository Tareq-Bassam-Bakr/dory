<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationKind;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\QueueAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The notification center and preferences (docs/NOTIFICATION_CENTER.md).
 */
class NotificationCenterTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private function alert(User $user, NotificationKind $kind = NotificationKind::TicketCalled, int $ahead = 1): void
    {
        $user->notify((new QueueAlert($kind, [
            'ticket' => ['id' => '01JTICKET0000000000000000', 'number' => 'A-007', 'guest' => false],
            'queue' => ['id' => '01JQUEUE00000000000000000', 'name' => 'General'],
            'business' => ['id' => '01JBUSINESS00000000000000', 'name' => 'Shifa Clinic'],
            'service' => null,
            'people_ahead' => $ahead,
        ]))->withoutPush());
    }

    private function list(User $user, string $query = '', string $language = 'en'): TestResponse
    {
        return $this->actingAsUser($user, 'GET', '/api/v1/me/notifications'.$query, [], ['Accept-Language' => $language]);
    }

    public function test_a_user_lists_their_notifications_in_their_language(): void
    {
        $user = User::factory()->create();
        $this->alert($user);

        $english = $this->list($user);
        $this->assertApiSuccess($english)
            ->assertJsonPath('data.0.kind', 'ticket_called')
            ->assertJsonPath('data.0.title', "It's your turn")
            ->assertJsonPath('data.0.body', 'Shifa Clinic, ticket A-007 is called. Please go to the counter.')
            ->assertJsonPath('data.0.ticket.id', '01JTICKET0000000000000000')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('meta.unread_count', 1);

        $arabic = $this->list($user, '', 'ar-SY,ar;q=0.9');
        $arabic->assertJsonPath('data.0.title', 'حان دورك')
            ->assertJsonPath('data.0.body', 'Shifa Clinic، نُوديت التذكرة A-007. يرجى التوجه إلى المكتب.');
    }

    public function test_almost_your_turn_says_who_is_next(): void
    {
        $user = User::factory()->create();
        $this->alert($user, NotificationKind::TicketAlmostTurn, 0);

        $this->list($user)->assertJsonPath('data.0.body', 'Shifa Clinic, ticket A-007: you are next.');
        $this->list($user, '', 'ar')->assertJsonPath('data.0.title', 'اقترب دورك');
    }

    public function test_users_see_only_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->alert($other);

        $this->list($user)->assertJsonCount(0, 'data')->assertJsonPath('meta.unread_count', 0);
        $theirs = $other->notifications()->sole()->id;

        $this->assertApiError(
            $this->actingAsUser($user, 'POST', '/api/v1/me/notifications/'.$theirs.'/read'),
            404,
            'notification_not_found',
        );
        $this->assertNull($other->notifications()->sole()->read_at);
    }

    public function test_read_and_unread_filters_and_marking(): void
    {
        $user = User::factory()->create();
        $this->alert($user);
        $this->alert($user, NotificationKind::TicketCompleted);
        $first = $user->notifications()->firstOrFail()->id;

        $this->assertApiSuccess($this->actingAsUser($user, 'POST', '/api/v1/me/notifications/'.$first.'/read'))
            ->assertJsonPath('data.read', true)
            ->assertJsonPath('meta.unread_count', 1);

        $this->list($user, '?filter=unread')->assertJsonCount(1, 'data');
        $this->list($user, '?filter=read')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->list($user, '?filter=all')->assertJsonCount(2, 'data');
        $this->assertApiError($this->list($user, '?filter=everything'), 422, 'validation_failed');
    }

    public function test_mark_all_as_read_touches_only_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->alert($user);
        $this->alert($user);
        $this->alert($other);

        $this->assertApiSuccess($this->actingAsUser($user, 'POST', '/api/v1/me/notifications/read-all'))
            ->assertJsonPath('data.marked', 2);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_the_notification_center_needs_an_account(): void
    {
        $this->getJson('/api/v1/me/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/me/notification-preferences')->assertUnauthorized();
    }

    // ----- Preferences --------------------------------------------------------

    public function test_preferences_default_to_everything_on(): void
    {
        $user = User::factory()->create();

        $this->assertApiSuccess($this->actingAsUser($user, 'GET', '/api/v1/me/notification-preferences'))
            ->assertExactJson([
                'success' => true,
                'message' => 'Notification preferences',
                'data' => array_fill_keys(NotificationPreference::FLAGS, true),
                'meta' => null,
            ]);
    }

    public function test_preferences_change_one_flag_at_a_time(): void
    {
        $user = User::factory()->create();

        $this->assertApiSuccess($this->actingAsUser($user, 'PUT', '/api/v1/me/notification-preferences', [
            'almost_turn' => false,
            'push_enabled' => false,
        ]))->assertJsonPath('data.almost_turn', false)
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.ticket_updates', true);

        $this->actingAsUser($user, 'PUT', '/api/v1/me/notification-preferences', ['push_enabled' => true]);
        $saved = NotificationPreference::for($user);
        $this->assertFalse($saved->almost_turn);
        $this->assertTrue($saved->push_enabled);
        $this->assertSame(1, NotificationPreference::query()->count());

        $this->assertApiError(
            $this->actingAsUser($user, 'PUT', '/api/v1/me/notification-preferences', ['almost_turn' => 'maybe']),
            422,
            'validation_failed',
        );
    }
}
