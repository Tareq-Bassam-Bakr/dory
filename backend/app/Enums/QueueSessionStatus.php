<?php

namespace App\Enums;

/**
 * - open:   customers can join; staff call and serve.
 * - paused: no new joins; staff keep serving existing tickets.
 * - closed: no joins and no calls; tickets already called or in service can
 *           still be finished, so no customer is silently dropped.
 */
enum QueueSessionStatus: string
{
    case Open = 'open';
    case Paused = 'paused';
    case Closed = 'closed';

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }
}
