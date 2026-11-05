import 'dart:async';

import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_providers.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Owns the session for the whole app.
///
/// On creation it restores the stored session ([ready] completes when that
/// is done). Login, registration and logout go through the use cases; a
/// 401 on any authenticated request ends the session ([SessionEvents]).
class AuthController extends Notifier<AuthState> {
  Completer<void> _ready = Completer<void>();

  /// Completes once the stored session has been checked.
  Future<void> get ready => _ready.future;

  @override
  AuthState build() {
    final StreamSubscription<void> expiry = ref
        .watch(sessionEventsProvider)
        .expired
        .listen((_) => unawaited(_endExpiredSession()));
    ref.onDispose(expiry.cancel);

    _ready = Completer<void>();
    unawaited(Future<void>.microtask(_restore));
    return const AuthRestoring();
  }

  Future<void> _restore() async {
    try {
      final AuthSession? session = await ref
          .read(restoreSessionUseCaseProvider)
          .call();
      if (state is AuthRestoring) {
        state = session == null
            ? const Unauthenticated()
            : Authenticated(session.user, isStale: session.isStale);
      }
    } on Object catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Session restore failed',
      );
      if (state is AuthRestoring) {
        state = const Unauthenticated();
      }
    } finally {
      if (!_ready.isCompleted) {
        _ready.complete();
      }
    }
  }

  /// Throws [AuthFailure] when the credentials are rejected.
  Future<void> login({required String email, required String password}) async {
    final AuthSession session = await ref
        .read(loginUseCaseProvider)
        .call(email: email, password: password);
    state = Authenticated(session.user);
  }

  /// Throws [AuthFailure], with field errors for invalid input.
  Future<void> register(RegistrationDetails details) async {
    final AuthSession session = await ref
        .read(registerUseCaseProvider)
        .call(details);
    state = Authenticated(session.user);
  }

  /// Reloads the profile and memberships, e.g. after joining a business.
  Future<AuthUser?> refreshUser() async {
    if (state is! Authenticated) {
      return null;
    }
    try {
      final AuthUser user = await ref
          .read(getCurrentUserUseCaseProvider)
          .call();
      if (state is Authenticated) {
        state = Authenticated(user);
      }
      return user;
    } on AuthFailure catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Profile refresh failed',
      );
      return null;
    }
  }

  Future<void> logout() async {
    await ref.read(logoutUseCaseProvider).call();
    state = const Unauthenticated();
  }

  Future<void> _endExpiredSession() async {
    await ref.read(authRepositoryProvider).clearLocalSession();
    if (state is Authenticated) {
      state = const Unauthenticated(sessionExpired: true);
    }
  }
}

final NotifierProvider<AuthController, AuthState> authControllerProvider =
    NotifierProvider<AuthController, AuthState>(
      AuthController.new,
      name: 'authControllerProvider',
    );
