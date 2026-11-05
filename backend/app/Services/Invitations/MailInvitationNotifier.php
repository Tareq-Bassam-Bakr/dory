<?php

namespace App\Services\Invitations;

use App\Contracts\InvitationNotifier;
use App\Models\BusinessInvitation;
use App\Notifications\BusinessInvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Emails the invitation link, synchronously, so the result is known.
 *
 * It sends only when invitation emails are enabled (INVITATION_SEND_EMAILS)
 * AND the default mailer is a real transport. The "log" and "array"
 * mailers are treated as "not configured": they would only write the link,
 * and its token, to a log file or to memory.
 */
final class MailInvitationNotifier implements InvitationNotifier
{
    private const NON_DELIVERING_MAILERS = ['log', 'array'];

    public function deliver(BusinessInvitation $invitation, string $invitationUrl): InvitationDelivery
    {
        if (! $this->canDeliver()) {
            return InvitationDelivery::notConfigured();
        }

        try {
            Notification::route('mail', $invitation->email)
                ->notifyNow(new BusinessInvitationNotification($invitation, $invitationUrl));
        } catch (Throwable $e) {
            // The exception class only: messages may echo the recipient or
            // the message body, which contains the token.
            Log::warning('Invitation email could not be sent', [
                'invitation' => $invitation->public_id,
                'error' => $e::class,
            ]);

            return InvitationDelivery::failed();
        }

        return InvitationDelivery::sent();
    }

    private function canDeliver(): bool
    {
        return (bool) config('dory.invitations.send_emails')
            && ! in_array(config('mail.default'), self::NON_DELIVERING_MAILERS, true);
    }
}
