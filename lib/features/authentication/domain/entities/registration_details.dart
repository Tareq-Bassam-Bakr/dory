import 'package:flutter/foundation.dart';

/// What the user enters to create an account (email + password).
@immutable
class RegistrationDetails {
  const RegistrationDetails({
    required this.name,
    required this.email,
    required this.password,
    required this.passwordConfirmation,
    this.phone,
    this.locale,
  });

  final String name;
  final String email;
  final String password;
  final String passwordConfirmation;

  /// Optional, E.164 (for example +963912345678).
  final String? phone;

  /// Preferred language code ("en" or "ar"); the server defaults to "en".
  final String? locale;
}
