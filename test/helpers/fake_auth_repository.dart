import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';
import 'package:dory_app/shared/enums/business_role.dart';

/// A signed-in test user. With [owns] the user owns one business, so the
/// router sends them to the business workspace.
AuthUser testUser({bool owns = false}) => AuthUser(
  id: '01jtestuserxxxxxxxxxxxxxxx',
  name: 'Lina Haddad',
  email: 'lina@dory.test',
  locale: 'en',
  timezone: 'UTC',
  memberships: owns
      ? const <UserMembership>[
          UserMembership(
            businessId: '01jbusinessxxxxxxxxxxxxxxx',
            businessName: 'Shifa Clinic',
            role: BusinessRole.owner,
            status: 'active',
          ),
        ]
      : const <UserMembership>[],
);

/// In-memory [AuthRepository] for widget tests that do not exercise HTTP.
/// The data layer itself is tested against a fake HTTP transport.
class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({AuthSession? storedSession}) : _session = storedSession;

  /// Starts signed in, as if the session was stored on the device.
  factory FakeAuthRepository.signedIn({bool owns = false}) =>
      FakeAuthRepository(
        storedSession: AuthSession(user: testUser(owns: owns)),
      );

  static const String validEmail = 'lina@dory.test';
  static const String validPassword = 'Correct1Horse';

  AuthSession? _session;

  /// Thrown by the next login or registration instead of succeeding.
  AuthFailure? nextFailure;
  int loginCalls = 0;
  int logoutCalls = 0;
  RegistrationDetails? lastRegistration;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    loginCalls++;
    final AuthFailure? failure = nextFailure;
    if (failure != null) {
      nextFailure = null;
      throw failure;
    }
    if (email != validEmail || password != validPassword) {
      throw const AuthFailure(AuthFailureReason.invalidCredentials);
    }
    return _session = AuthSession(user: testUser());
  }

  @override
  Future<AuthSession> register(RegistrationDetails details) async {
    lastRegistration = details;
    final AuthFailure? failure = nextFailure;
    if (failure != null) {
      nextFailure = null;
      throw failure;
    }
    return _session = AuthSession(user: testUser());
  }

  @override
  Future<AuthSession?> restoreSession() async => _session;

  @override
  Future<AuthUser> currentUser() async {
    final AuthSession? session = _session;
    if (session == null) {
      throw const AuthFailure(AuthFailureReason.invalidCredentials);
    }
    return session.user;
  }

  @override
  Future<void> logout() async {
    logoutCalls++;
    _session = null;
  }

  @override
  Future<void> clearLocalSession() async {
    _session = null;
  }
}
