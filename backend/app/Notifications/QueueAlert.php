<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A queue notification (docs/NOTIFICATION_EVENTS.md).
 *
 * Signed-in users: stored for the notification center, announced on their
 * private channel, and pushed to their devices when they allow it. Guests
 * (an anonymous notifiable routed to their ticket): pushed only.
 *
 * facts holds what the texts need (ticket number, business and queue names,
 * people ahead) and public ids. Never a phone number or a ticket token.
 */
final class QueueAlert extends Notification
{
    private bool $push = true;

    /**
     * @param  array<string, mixed>  $facts
     */
    public function __construct(
        public readonly NotificationKind $kind,
        public readonly array $facts,
    ) {}

    public function withoutPush(): self
    {
        $this->push = false;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return [PushChannel::class];
        }

        return $this->push
            ? ['database', 'broadcast', PushChannel::class]
            : ['database', 'broadcast'];
    }

    /**
     * Stored in notifications.type: the kind, not the class name.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->kind->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return ['kind' => $this->kind->value] + $this->facts;
    }

    /**
     * On private-users.{user}: enough for the app to update its badge and,
     * if it wants, to read the notification from the API.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'kind' => $this->kind->value,
            'ticket' => $this->facts['ticket']['id'] ?? null,
        ]))->onQueue((string) config('dory.realtime.queue'));
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }
}
