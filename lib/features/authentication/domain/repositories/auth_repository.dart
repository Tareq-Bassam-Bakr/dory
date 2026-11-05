import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';

/// Email + password authentication against the DORY API.
///
/// Every method throws [AuthFailure] on failure.
abstract interface class AuthRepository {
  /// Signs in and stores the session securely on the device.
  Future<AuthSession> login({required String email, required String password});

  /// Creates the account, signs in and stores the session.
  Future<AuthSession> register(RegistrationDetails details);

  /// Restores the stored session after an app restart.
  ///
  /// Returns null when there is no session, or when it expired or was
  /// revoked (the stored data is then removed). When the server cannot be
  /// reached, returns the last known user with [AuthSession.isStale].
  Future<AuthSession?> restoreSession();

  /// Fetches the current user and updates the stored copy.
  Future<AuthUser> currentUser();

  /// Revokes the token on the server (best effort) and always removes the
  /// session from the device.
  Future<void> logout();

  /// Removes the session from the device without calling the server, e.g.
  /// after the server already rejected the token.
  Future<void> clearLocalSession();
}
