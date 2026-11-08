<?php

namespace App\Services\Push;

/**
 * What happened to one push message.
 */
enum PushOutcome: string
{
    /** Accepted by FCM. Delivery to the device is not confirmed by FCM. */
    case Accepted = 'accepted';

    /** Push is switched off (FCM_ENABLED=false): nothing was sent. */
    case Skipped = 'skipped';

    /** FCM says the token is unknown or invalid: the device is revoked. */
    case InvalidToken = 'invalid_token';

    /** Temporary failure (network, quota, FCM unavailable): retried later. */
    case Retry = 'retry';

    /** Permanent failure for this message: logged, not retried. */
    case Failed = 'failed';
}
