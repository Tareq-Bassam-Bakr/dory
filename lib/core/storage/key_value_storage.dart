import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Small, synchronous-read key/value store for non-sensitive preferences.
///
/// Never store secrets (tokens, credentials) here. Sensitive values belong in
/// `SecureStore`.
abstract interface class KeyValueStorage {
  String? readString(String key);

  Future<void> writeString(String key, String value);

  Future<void> remove(String key);
}

/// Provides the app-wide [KeyValueStorage].
///
/// The real instance needs async initialization, so it is created in
/// `bootstrap()` and injected with `overrideWithValue`. Tests inject
/// [InMemoryKeyValueStorage].
final Provider<KeyValueStorage> keyValueStorageProvider =
    Provider<KeyValueStorage>(
      (ref) => throw StateError(
        'keyValueStorageProvider was read before being overridden. '
        'Override it in bootstrap() or in the test ProviderScope.',
      ),
      name: 'keyValueStorageProvider',
    );
