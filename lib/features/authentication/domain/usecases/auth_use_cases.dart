import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';

/// Signs in with email and password.
class LoginUseCase {
  const LoginUseCase(this._repository);

  final AuthRepository _repository;

  Future<AuthSession> call({required String email, required String password}) {
    return _repository.login(
      email: email.trim().toLowerCase(),
      password: password,
    );
  }
}

/// Creates an account and signs in.
class RegisterUseCase {
  const RegisterUseCase(this._repository);

  final AuthRepository _repository;

  Future<AuthSession> call(RegistrationDetails details) {
    return _repository.register(details);
  }
}

/// Restores the session stored on the device, if it is still valid.
class RestoreSessionUseCase {
  const RestoreSessionUseCase(this._repository);

  final AuthRepository _repository;

  Future<AuthSession?> call() => _repository.restoreSession();
}

/// Reloads the signed-in user's profile and memberships.
class GetCurrentUserUseCase {
  const GetCurrentUserUseCase(this._repository);

  final AuthRepository _repository;

  Future<AuthUser> call() => _repository.currentUser();
}

/// Ends the session on the server and on the device.
class LogoutUseCase {
  const LogoutUseCase(this._repository);

  final AuthRepository _repository;

  Future<void> call() => _repository.logout();
}
