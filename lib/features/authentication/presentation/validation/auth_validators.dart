import 'package:dory_app/app/localization/l10n.dart';

/// Client-side checks mirroring the API rules, so users see problems before
/// submitting. The server validates again and is the authority.
abstract final class AuthValidators {
  static final RegExp _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');
  static final RegExp _e164 = RegExp(r'^\+[1-9]\d{6,14}$');
  static final RegExp _phoneSeparators = RegExp(r'[\s\-.()]');

  static String? email(AppLocalizations l10n, String? value) {
    final String email = (value ?? '').trim();
    if (email.isEmpty) {
      return l10n.authEmailRequired;
    }
    return _email.hasMatch(email) && email.length <= 255
        ? null
        : l10n.authEmailInvalid;
  }

  /// Login only checks presence: strength rules would block legacy passwords.
  static String? loginPassword(AppLocalizations l10n, String? value) {
    return (value ?? '').isEmpty ? l10n.authPasswordRequired : null;
  }

  static String? name(AppLocalizations l10n, String? value) {
    final int length = (value ?? '').trim().length;
    return length >= 2 && length <= 120 ? null : l10n.authNameInvalid;
  }

  /// Optional; when given it must be E.164 after removing separators.
  static String? optionalPhone(AppLocalizations l10n, String? value) {
    final String? phone = normalizePhone(value);
    return phone == null || _e164.hasMatch(phone)
        ? null
        : l10n.authPhoneInvalid;
  }

  /// Same policy as the API: 8 to 128 characters with upper and lower case
  /// letters and a number.
  static String? newPassword(AppLocalizations l10n, String? value) {
    final String password = value ?? '';
    if (password.isEmpty) {
      return l10n.authPasswordRequired;
    }
    final bool strong =
        password.length >= 8 &&
        password.length <= 128 &&
        password.contains(RegExp('[a-z]')) &&
        password.contains(RegExp('[A-Z]')) &&
        password.contains(RegExp('[0-9]'));
    return strong ? null : l10n.authPasswordTooWeak;
  }

  static String? confirmation(
    AppLocalizations l10n,
    String? value,
    String password,
  ) {
    return value == password ? null : l10n.authPasswordMismatch;
  }

  /// Removes spaces, dashes, dots and parentheses; empty becomes null.
  static String? normalizePhone(String? value) {
    final String phone = (value ?? '').replaceAll(_phoneSeparators, '');
    return phone.isEmpty ? null : phone;
  }
}
