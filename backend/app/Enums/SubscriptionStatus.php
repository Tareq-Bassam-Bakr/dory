<?php

namespace App\Enums;

/**
 * Stored status of a business subscription (docs/SUBSCRIPTIONS.md).
 *
 * - trialing: the trial plan until trial_ends_at.
 * - active: the free plan (no end), or a paid plan until current_period_end.
 * - past_due: a paid period ended unpaid; the plan stays until grace_ends_at.
 * - expired: a paid plan or trial ended; the business is on the free plan.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
}
