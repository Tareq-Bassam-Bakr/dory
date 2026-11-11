<?php

namespace App\Enums;

/**
 * Entries of a business's subscription history.
 */
enum SubscriptionEventType: string
{
    case Started = 'started';
    case TrialStarted = 'trial_started';
    case TrialEnded = 'trial_ended';
    case TrialExtended = 'trial_extended';
    case Activated = 'activated';
    case Renewed = 'renewed';
    case Upgraded = 'upgraded';
    case DowngradeScheduled = 'downgrade_scheduled';
    case DowngradeCanceled = 'downgrade_canceled';
    case Canceled = 'canceled';
    case Resumed = 'resumed';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case PaymentFailed = 'payment_failed';
    case PlanAssigned = 'plan_assigned';
}
