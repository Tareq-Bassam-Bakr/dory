import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';

/// The localized explanation of an [AuthFailure].
String authFailureMessage(AppLocalizations l10n, AuthFailure failure) {
  return switch (failure.reason) {
    AuthFailureReason.invalidCredentials => l10n.authErrorInvalidCredentials,
    AuthFailureReason.accountInactive => l10n.authErrorAccountInactive,
    AuthFailureReason.validation => l10n.authErrorValidation,
    AuthFailureReason.rateLimited => l10n.authErrorRateLimited,
    AuthFailureReason.network => l10n.errorNetwork,
    AuthFailureReason.server => l10n.errorServer,
    AuthFailureReason.unknown => l10n.errorUnknown,
  };
}
