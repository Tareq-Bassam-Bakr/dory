<?php

namespace App\Services\Push;

/**
 * One push message for one device. Built at send time, so the token is
 * decrypted only for the moment of sending and never queued or logged.
 *
 * data values are strings (an FCM requirement). They hold the notification
 * kind and public ids only: never a guest ticket token.
 */
final readonly class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $token,
        public string $title,
        public string $body,
        public array $data,
        public bool $urgent,
    ) {}
}
