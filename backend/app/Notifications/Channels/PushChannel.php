<?php

namespace App\Notifications\Channels;

use App\Jobs\SendPushMessage;
use App\Models\PushDevice;
use App\Models\QueueTicket;
use App\Models\User;
use App\Notifications\QueueAlert;
use App\Services\Notifications\NotificationTexts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Queues one push job per active device of the recipient: a signed-in user's
 * devices, or the devices registered with a guest ticket's token.
 *
 * Does nothing while push is off (FCM_ENABLED=false). Each device gets the
 * text in its own language. The payload carries the kind and public ids,
 * never a guest ticket token (docs/PUSH_NOTIFICATIONS.md).
 */
final class PushChannel
{
    public function __construct(private readonly NotificationTexts $texts) {}

    public function send(object $notifiable, QueueAlert $notification): void
    {
        if (! config('dory.push.enabled')) {
            return;
        }

        $guest = $notifiable instanceof AnonymousNotifiable;
        $data = [
            'kind' => $notification->kind->value,
            'ticket' => (string) ($notification->facts['ticket']['id'] ?? ''),
            'queue' => (string) ($notification->facts['queue']['id'] ?? ''),
            'guest' => $guest ? '1' : '0',
            'notification' => $guest ? '' : (string) $notification->id,
        ];

        foreach ($this->devicesOf($notifiable) as $device) {
            $text = $this->texts->render($notification->kind, $notification->facts, $device->locale);
            SendPushMessage::dispatch(
                $device->getKey(),
                $text['title'],
                $text['body'],
                $data,
                $notification->kind->isUrgent(),
                $notification->id.':'.$device->getKey(),
            );
        }
    }

    /**
     * @return Collection<int, PushDevice>
     */
    private function devicesOf(object $notifiable): Collection
    {
        if ($notifiable instanceof User) {
            return $notifiable->pushDevices()->active()->get();
        }

        $ticket = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor(self::class)
            : null;

        return $ticket instanceof QueueTicket
            ? PushDevice::query()->active()->where('queue_ticket_id', $ticket->getKey())->get()
            : new Collection;
    }
}
