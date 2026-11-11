import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/errors/app_failure.dart';

/// The localized explanation of a failed action or load.
String failureMessage(AppLocalizations l10n, Object error) {
  if (error is! AppFailure) {
    return l10n.errorUnknown;
  }
  final String? specific = switch (error.code) {
    'business_suspended' => l10n.errorBusinessSuspended,
    // Plans (docs/SUBSCRIPTIONS.md).
    'plan_limit_reached' => planLimitMessage(l10n, error),
    'feature_not_in_plan' => l10n.errorPlanFeature,
    'payments_unavailable' => l10n.errorPaymentsUnavailable,
    'payment_provider_error' => l10n.errorPaymentProvider,
    'plan_not_available' => l10n.errorPlanNotAvailable,
    'subscription_change_not_allowed' => switch (error.details['reason']) {
      'use_downgrade' => l10n.errorPlanChangeUseDowngrade,
      'managed_by_admin' => l10n.errorPlanChangeManaged,
      _ => l10n.errorPlanChange,
    },
    _ => null,
  };
  if (specific != null) {
    return specific;
  }
  return switch (error.kind) {
    FailureKind.network => l10n.errorNetwork,
    FailureKind.unauthorized => l10n.authSessionExpired,
    FailureKind.forbidden => l10n.errorForbidden,
    FailureKind.notFound => l10n.errorNotFound,
    FailureKind.validation => l10n.errorValidation,
    FailureKind.rateLimited => l10n.authErrorRateLimited,
    FailureKind.server => l10n.errorServer,
    FailureKind.conflict ||
    FailureKind.gone ||
    FailureKind.unknown => l10n.errorUnknown,
  };
}

/// A reached plan limit, with its number: "This plan includes up to 3
/// branches. Upgrade to add more."
String planLimitMessage(AppLocalizations l10n, AppFailure failure) {
  final Object? max = failure.details['max'];
  final int limit = max is num ? max.toInt() : 0;
  return switch (failure.details['limit']) {
    'branches' => l10n.planLimitBranches(limit),
    'queues' => l10n.planLimitQueues(limit),
    'members' => l10n.planLimitMembers(limit),
    _ => l10n.planLimitTitle,
  };
}
