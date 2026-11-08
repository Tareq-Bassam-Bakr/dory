<?php

namespace App\Services\Notifications;

use App\Enums\NotificationKind;
use App\Models\NotificationPreference;
use App\Models\QueueTicket;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\QueueAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sends one queue notification to one recipient, at most once
 * (docs/NOTIFICATION_EVENTS.md).
 *
 * Every alert has a key. Writing the key to notification_dispatches and
 * storing the notification happen in one transaction: a retried job or a
 * second worker finds the key and sends nothing, and a failure before the
 * commit leaves no key, so a retry sends it.
 */
final class NotificationDispatcher
{
    /**
     * The person holding the ticket: a signed-in customer, or a guest.
     *
     * Expects the ticket with user, queue.branch.business and queue.service loaded.
     *
     * @param  array<string, mixed>  $extra  Additional facts, such as people_ahead.
     */
    public function toTicketHolder(QueueTicket $ticket, NotificationKind $kind, string $key, array $extra = []): void
    {
        $alert = new QueueAlert($kind, $this->facts($ticket, $extra));

        if ($ticket->user_id !== null) {
            if ($ticket->user !== null) {
                $this->toUser($ticket->user, $alert, $key);
            }

            return;
        }

        // Guests have no notification center: push only, to the devices
        // registered with the ticket's own token. Nothing is sent while push
        // is off, but the key is still claimed so a later retry stays silent.
        if ($this->claim($key)) {
            Notification::route(PushChannel::class, $ticket)->notify($alert);
        }
    }

    /**
     * A staff member, about a ticket in a queue they run.
     */
    public function toStaff(User $user, QueueTicket $ticket, NotificationKind $kind, string $key): void
    {
        $this->toUser($user, new QueueAlert($kind, $this->facts($ticket, [])), $key);
    }

    private function toUser(User $user, QueueAlert $alert, string $key): void
    {
        if (! $user->isActive()) {
            return;
        }

        $preferences = NotificationPreference::for($user);
        if (! $preferences->allows($alert->kind)) {
            return;
        }
        if (! $preferences->push_enabled) {
            $alert->withoutPush();
        }

        DB::transaction(function () use ($user, $alert, $key): void {
            if ($this->claim($key)) {
                $user->notify($alert);
            }
        });
    }

    private function claim(string $key): bool
    {
        return DB::table('notification_dispatches')->insertOrIgnore([
            'dedupe_key' => $key,
            'created_at' => now(),
        ]) === 1;
    }

    /**
     * What the notification texts need, and public ids. No phone numbers,
     * no customer names, no tokens.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function facts(QueueTicket $ticket, array $extra): array
    {
        $queue = $ticket->queue;

        return [
            'ticket' => [
                'id' => $ticket->public_id,
                'number' => $ticket->ticket_number,
                'guest' => $ticket->user_id === null,
            ],
            'queue' => ['id' => $queue->public_id, 'name' => $queue->name],
            'business' => ['id' => $queue->branch->business->public_id, 'name' => $queue->branch->business->name],
            'service' => $queue->service?->name,
        ] + $extra;
    }
}
