<?php

namespace App\Enums;

/**
 * Sensitive platform operations recorded in platform_audit_logs
 * (docs/ADMIN_DASHBOARD.md, "Audit log").
 */
enum PlatformAuditAction: string
{
    case AdminSignedIn = 'admin.signed_in';
    case AdminSignInFailed = 'admin.sign_in_failed';
    case AdminSignedOut = 'admin.signed_out';
    case AdminGranted = 'admin.granted';
    case AdminRevoked = 'admin.revoked';
    case AdminCreated = 'admin.created';
    case BusinessApproved = 'business.approved';
    case BusinessSuspended = 'business.suspended';
    case BusinessReactivated = 'business.reactivated';
    case UserSuspended = 'user.suspended';
    case UserReactivated = 'user.reactivated';
    case PlanCreated = 'plan.created';
    case PlanUpdated = 'plan.updated';
    case TrialExtended = 'subscription.trial_extended';
    case PlanAssigned = 'subscription.plan_assigned';
    case SubscriptionEnded = 'subscription.ended';
    case SubscriptionCanceled = 'subscription.canceled';
    case SubscriptionResumed = 'subscription.resumed';
    case PaymentRecorded = 'payment.recorded';
    case PaymentApproved = 'payment.approved';
    case PaymentRejected = 'payment.rejected';
    case SettingsUpdated = 'settings.updated';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $action): string => $action->value, self::cases());
    }

    /**
     * Badge colour on the audit log page.
     */
    public function tone(): string
    {
        return match ($this) {
            self::AdminSignInFailed, self::AdminRevoked, self::BusinessSuspended, self::UserSuspended,
            self::SubscriptionEnded, self::PaymentRejected => 'danger',
            self::AdminGranted, self::AdminCreated, self::BusinessApproved, self::BusinessReactivated,
            self::UserReactivated, self::PaymentApproved => 'success',
            self::SubscriptionCanceled, self::SettingsUpdated, self::PlanUpdated => 'warning',
            default => 'info',
        };
    }
}
