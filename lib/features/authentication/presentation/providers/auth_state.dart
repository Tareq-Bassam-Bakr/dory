import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:flutter/foundation.dart';

/// Where the session stands. The router and the account screens read it.
@immutable
sealed class AuthState {
  const AuthState();
}

/// The stored session is being checked (app start).
final class AuthRestoring extends AuthState {
  const AuthRestoring();
}

final class Authenticated extends AuthState {
  const Authenticated(this.user, {this.isStale = false});

  final AuthUser user;

  /// Restored offline: [user] is the last known profile.
  final bool isStale;
}

final class Unauthenticated extends AuthState {
  const Unauthenticated({this.sessionExpired = false});

  /// True when the session ended because the server rejected the token,
  /// so the login screen can say why.
  final bool sessionExpired;
}
