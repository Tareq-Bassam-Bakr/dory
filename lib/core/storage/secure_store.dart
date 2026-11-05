import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Encrypted key/value store for secrets (session tokens).
///
/// Android: AES-GCM with a key held in the Android Keystore. iOS: the
/// Keychain, readable after first unlock and never synced off the device.
/// Never use [KeyValueStorage] (shared_preferences) for secrets.
abstract interface class SecureStore {
  Future<String?> read(String key);

  Future<void> write(String key, String value);

  Future<void> delete(String key);
}

/// [SecureStore] backed by flutter_secure_storage.
class FlutterSecureStore implements SecureStore {
  const FlutterSecureStore([
    this._storage = const FlutterSecureStorage(
      iOptions: IOSOptions(
        accessibility: KeychainAccessibility.first_unlock_this_device,
      ),
    ),
  ]);

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) {
    return _storage.write(key: key, value: value);
  }

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

/// Volatile [SecureStore] for tests.
class InMemorySecureStore implements SecureStore {
  InMemorySecureStore([Map<String, String>? initialValues])
    : values = <String, String>{...?initialValues};

  /// Exposed so tests can inspect what was persisted.
  final Map<String, String> values;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

final Provider<SecureStore> secureStoreProvider = Provider<SecureStore>(
  (ref) => const FlutterSecureStore(),
  name: 'secureStoreProvider',
);
