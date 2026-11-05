import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/network/api_exception.dart';
import 'package:dory_app/features/authentication/data/datasources/auth_local_data_source.dart';
import 'package:dory_app/features/authentication/data/datasources/auth_remote_data_source.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';

/// [AuthRepository] backed by the Laravel API and secure device storage.
class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl({
    required this._remote,
    required this._local,
    DateTime Function()? clock,
  }) : _now = clock ?? DateTime.now;

  final AuthRemoteDataSource _remote;
  final AuthLocalDataSource _local;
  final DateTime Function() _now;

  @override
  Future<AuthSession> login({required String email, required String password}) {
    return _authenticate(() => _remote.login(email: email, password: password));
  }

  @override
  Future<AuthSession> register(RegistrationDetails details) {
    return _authenticate(() => _remote.register(details));
  }

  @override
  Future<AuthSession?> restoreSession() async {
    final StoredSession? stored = await _local.readSession();
    if (stored == null) {
      return null;
    }

    final DateTime? expiresAt = stored.expiresAt;
    if (expiresAt != null && !expiresAt.isAfter(_now())) {
      await _local.clear();
      return null;
    }

    try {
      final AuthUser user = await _remote.me();
      await _local.saveUser(user);
      return AuthSession(user: user, expiresAt: expiresAt);
    } on ApiException catch (error) {
      if (error.kind == ApiErrorKind.unauthorized ||
          error.kind == ApiErrorKind.forbidden) {
        // Revoked, expired on the server, or the account was deactivated.
        await _local.clear();
        return null;
      }
      final AuthUser? cached = stored.user;
      if (error.isConnectivityProblem && cached != null) {
        // Offline: keep the session; the server re-checks the token on the
        // next request.
        return AuthSession(user: cached, expiresAt: expiresAt, isStale: true);
      }
      throw _failureFrom(error);
    }
  }

  @override
  Future<AuthUser> currentUser() async {
    try {
      final AuthUser user = await _remote.me();
      await _local.saveUser(user);
      return user;
    } on ApiException catch (error) {
      throw _failureFrom(error);
    }
  }

  @override
  Future<void> logout() async {
    try {
      await _remote.logout();
    } on ApiException catch (error, stackTrace) {
      // The device forgets the token either way; an unreachable server
      // will reject it once it expires.
      if (error.kind != ApiErrorKind.unauthorized) {
        ErrorReporter.report(
          error,
          stackTrace,
          context: 'Server-side logout failed',
        );
      }
    } finally {
      await _local.clear();
    }
  }

  @override
  Future<void> clearLocalSession() => _local.clear();

  Future<AuthSession> _authenticate(
    Future<AuthPayload> Function() request,
  ) async {
    final AuthPayload payload;
    try {
      payload = await request();
    } on ApiException catch (error) {
      throw _failureFrom(error);
    }

    await _local.saveSession(payload.token, payload.user);
    return AuthSession(user: payload.user, expiresAt: payload.token.expiresAt);
  }

  static AuthFailure _failureFrom(ApiException error) {
    return switch (error.kind) {
      ApiErrorKind.unauthorized => const AuthFailure(
        AuthFailureReason.invalidCredentials,
      ),
      ApiErrorKind.forbidden when error.code == 'account_inactive' =>
        const AuthFailure(AuthFailureReason.accountInactive),
      ApiErrorKind.validation => AuthFailure(
        AuthFailureReason.validation,
        fieldErrors: <String, String>{
          for (final MapEntry<String, List<String>> entry
              in error.fieldErrors.entries)
            if (entry.value.isNotEmpty) entry.key: entry.value.first,
        },
      ),
      ApiErrorKind.rateLimited => const AuthFailure(
        AuthFailureReason.rateLimited,
      ),
      ApiErrorKind.network ||
      ApiErrorKind.timeout => const AuthFailure(AuthFailureReason.network),
      ApiErrorKind.server => const AuthFailure(AuthFailureReason.server),
      _ => const AuthFailure(AuthFailureReason.unknown),
    };
  }
}
