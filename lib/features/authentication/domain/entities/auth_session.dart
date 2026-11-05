import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:flutter/foundation.dart';

/// An active session. The token itself never leaves the data layer.
@immutable
class AuthSession {
  const AuthSession({required this.user, this.expiresAt, this.isStale = false});

  final AuthUser user;

  /// When the server will stop accepting the token, if it expires.
  final DateTime? expiresAt;

  /// True when the session was restored from the device without reaching
  /// the server (offline): [user] is the last known profile.
  final bool isStale;
}
