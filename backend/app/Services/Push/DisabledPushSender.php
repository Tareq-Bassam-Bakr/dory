<?php

namespace App\Services\Push;

/**
 * Push is switched off (FCM_ENABLED=false or no Firebase credentials):
 * nothing leaves the server. Notifications still reach the app through the
 * notification center and Reverb.
 */
final class DisabledPushSender implements PushSender
{
    public function send(PushMessage $message): PushOutcome
    {
        return PushOutcome::Skipped;
    }
}
