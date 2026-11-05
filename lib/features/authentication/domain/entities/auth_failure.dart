import 'package:flutter/foundation.dart';

/// Why an authentication action failed, in terms the UI can explain.
enum AuthFailureReason {
  invalidCredentials,
  accountInactive,
  validation,
  rateLimited,
  network,
  server,
  unknown,
}

/// A failed login, registration or session call.
@immutable
class AuthFailure implements Exception {
  const AuthFailure(this.reason, {this.fieldErrors = const <String, String>{}});

  final AuthFailureReason reason;

  /// First server message per field, for validation failures. Keys are API
  /// field names (name, email, phone, password).
  final Map<String, String> fieldErrors;

  @override
  String toString() => 'AuthFailure(${reason.name})';
}
