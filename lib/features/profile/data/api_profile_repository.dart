import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// The signed-in user's own profile (`PUT /users/profile`). The route has no
/// user id, so it can only ever change the caller. Throws [AppFailure].
class ApiProfileRepository {
  const ApiProfileRepository(this._api);

  final ApiClient _api;

  Future<void> update({required String name, String? phone}) =>
      AppFailure.guard(() async {
        final String? trimmed = phone?.trim();
        await _api.put(
          '/users/profile',
          body: <String, Object?>{
            'name': name.trim(),
            'phone': trimmed == null || trimmed.isEmpty ? null : trimmed,
          },
        );
      });
}

final Provider<ApiProfileRepository> profileRepositoryProvider =
    Provider<ApiProfileRepository>(
      (ref) => ApiProfileRepository(ref.watch(apiClientProvider)),
      name: 'profileRepositoryProvider',
    );
