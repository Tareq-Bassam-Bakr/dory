<?php

namespace App\Notifications;

use App\Models\BusinessInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email. Sent synchronously by MailInvitationNotifier (never
 * queued, so the link is not serialized into the jobs table).
 */
class BusinessInvitationNotification extends Notification
{
    public function __construct(
        private readonly BusinessInvitation $invitation,
        private readonly string $invitationUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->invitation->business->name;
        $inviter = $this->invitation->inviter?->name;

        return (new MailMessage)
            ->subject("You're invited to join {$business} on DORY")
            ->line($inviter !== null
                ? "{$inviter} invited you to join {$business} as {$this->invitation->role->value}."
                : "You were invited to join {$business} as {$this->invitation->role->value}.")
            ->action('Open invitation', $this->invitationUrl)
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().' (UTC).')
            ->line('If you were not expecting it, you can ignore this email.');
    }
}
