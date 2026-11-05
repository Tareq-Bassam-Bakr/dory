import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// [KeyValueStorage] backed by `shared_preferences`.
///
/// Uses [SharedPreferencesWithCache] (the non-legacy API) restricted to the
/// keys declared in [StorageKeys], so unrelated platform keys are never read.
class SharedPreferencesKeyValueStorage implements KeyValueStorage {
  SharedPreferencesKeyValueStorage._(this._preferences);

  final SharedPreferencesWithCache _preferences;

  static Future<SharedPreferencesKeyValueStorage> create() async {
    final SharedPreferencesWithCache preferences =
        await SharedPreferencesWithCache.create(
          cacheOptions: const SharedPreferencesWithCacheOptions(
            allowList: StorageKeys.all,
          ),
        );
    return SharedPreferencesKeyValueStorage._(preferences);
  }

  @override
  String? readString(String key) => _preferences.getString(key);

  @override
  Future<void> writeString(String key, String value) {
    return _preferences.setString(key, value);
  }

  @override
  Future<void> remove(String key) => _preferences.remove(key);
}
