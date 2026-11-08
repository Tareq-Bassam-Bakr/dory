<?php

namespace App\Services\Push;

/**
 * Sends one push message. FirebasePushSender talks to FCM; DisabledPushSender
 * is used while push is off; tests use a fake.
 */
interface PushSender
{
    public function send(PushMessage $message): PushOutcome;
}
