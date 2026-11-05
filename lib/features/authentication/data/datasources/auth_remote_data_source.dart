import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/authentication/data/models/auth_token_model.dart';
import 'package:dory_app/features/authentication/data/models/auth_user_model.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';

/// The user and token returned by login and registration.
typedef AuthPayload = ({AuthUser user, AuthTokenModel token});

/// Calls the Laravel authentication endpoints (`/auth/*`). Throws
/// [ApiException] on failure.
class AuthRemoteDataSource {
  const AuthRemoteDataSource(this._api, {required this.deviceName});

  final ApiClient _api;

  /// Sent as `device_name`, shown in the user's token list.
  final String deviceName;

  Future<AuthPayload> login({
    required String email,
    required String password,
  }) async {
    final ApiEnvelope envelope = await _api.post(
      '/auth/login',
      body: <String, Object?>{
        'email': email,
        'password': password,
        'device_name': deviceName,
      },
    );
    return _payload(envelope.dataObject);
  }

  Future<AuthPayload> register(RegistrationDetails details) async {
    final ApiEnvelope envelope = await _api.post(
      '/auth/register',
      body: <String, Object?>{
        'name': details.name,
        'email': details.email,
        if (details.phone != null) 'phone': details.phone,
        'password': details.password,
        'password_confirmation': details.passwordConfirmation,
        if (details.locale != null) 'locale': details.locale,
        'device_name': deviceName,
      },
    );
    return _payload(envelope.dataObject);
  }

  Future<AuthUser> me() async {
    final ApiEnvelope envelope = await _api.get('/auth/me');
    return AuthUserModel.fromJson(envelope.dataObject);
  }

  Future<void> logout() async {
    await _api.post('/auth/logout');
  }

  static AuthPayload _payload(JsonMap data) => (
    user: AuthUserModel.fromJson(data.object('user')),
    token: AuthTokenModel.fromJson(data.object('token')),
  );
}
