import 'package:dory_app/core/network/json.dart';
import 'package:flutter/foundation.dart';

/// The Sanctum token returned by login and registration:
/// `{ access_token, token_type: "Bearer", expires_at }`.
@immutable
class AuthTokenModel {
  const AuthTokenModel({required this.accessToken, this.expiresAt});

  factory AuthTokenModel.fromJson(JsonMap json) => AuthTokenModel(
    accessToken: json.string('access_token'),
    expiresAt: json.dateTimeOrNull('expires_at'),
  );

  final String accessToken;
  final DateTime? expiresAt;

  @override
  String toString() => 'AuthTokenModel(expiresAt: $expiresAt)';
}
