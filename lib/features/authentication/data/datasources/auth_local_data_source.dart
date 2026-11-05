import 'dart:convert';

import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/data/models/auth_token_model.dart';
import 'package:dory_app/features/authentication/data/models/auth_user_model.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';

/// The stored session: token, its expiry and the last known user.
typedef StoredSession = ({String token, DateTime? expiresAt, AuthUser? user});

/// Persists the session in [SecureStore] (Keystore/Keychain backed) and
/// mirrors the token into [SessionTokenHolder] for the HTTP layer.
class AuthLocalDataSource {
  AuthLocalDataSource(this._store, this._tokens);

  final SecureStore _store;
  final SessionTokenHolder _tokens;

  static const String tokenKey = 'dory.auth.token';
  static const String expiresAtKey = 'dory.auth.expires_at';
  static const String userKey = 'dory.auth.user';

  Future<void> saveSession(AuthTokenModel token, AuthUser user) async {
    await _store.write(tokenKey, token.accessToken);
    final DateTime? expiresAt = token.expiresAt;
    if (expiresAt == null) {
      await _store.delete(expiresAtKey);
    } else {
      await _store.write(expiresAtKey, expiresAt.toUtc().toIso8601String());
    }
    await saveUser(user);
    _tokens.token = token.accessToken;
  }

  Future<void> saveUser(AuthUser user) {
    return _store.write(userKey, jsonEncode(AuthUserModel.toJson(user)));
  }

  /// Reads the stored session and arms the HTTP layer with its token.
  /// A corrupt user copy is ignored rather than failing the restore.
  Future<StoredSession?> readSession() async {
    final String? token = await _store.read(tokenKey);
    if (token == null || token.isEmpty) {
      return null;
    }

    final String? expiresAt = await _store.read(expiresAtKey);
    AuthUser? user;
    final String? userJson = await _store.read(userKey);
    if (userJson != null) {
      try {
        user = AuthUserModel.fromJson(asJsonMap(jsonDecode(userJson)));
      } on FormatException {
        user = null;
      }
    }

    _tokens.token = token;
    return (
      token: token,
      expiresAt: expiresAt == null ? null : DateTime.tryParse(expiresAt),
      user: user,
    );
  }

  Future<void> clear() async {
    _tokens.token = null;
    await _store.delete(tokenKey);
    await _store.delete(expiresAtKey);
    await _store.delete(userKey);
  }
}
