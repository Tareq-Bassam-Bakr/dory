<?php

namespace App\Enums;

/**
 * Entries of the append-only queue_events history.
 */
enum QueueEventType: string
{
    case Joined = 'joined';
    case Called = 'called';
    case ServiceStarted = 'service_started';
    case ServiceCompleted = 'service_completed';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
    case NoShow = 'no_show';
    case Expired = 'expired';
    case Recalled = 'recalled';
    case SessionOpened = 'session_opened';
    case SessionPaused = 'session_paused';
    case SessionResumed = 'session_resumed';
    case SessionClosed = 'session_closed';
}
