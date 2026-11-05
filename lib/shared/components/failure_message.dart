import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/errors/app_failure.dart';

/// The localized explanation of a failed action or load.
String failureMessage(AppLocalizations l10n, Object error) {
  if (error is! AppFailure) {
    return l10n.errorUnknown;
  }
  if (error.code == 'business_suspended') {
    return l10n.errorBusinessSuspended;
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
