import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';

/// Volatile [KeyValueStorage].
///
/// Used in tests and as a fallback when platform storage cannot be opened,
/// so the app still starts (preferences are simply not persisted).
///
/// Like the shared_preferences storage on a phone, it accepts only the keys
/// in [StorageKeys.all], so tests catch a key that was never listed.
class InMemoryKeyValueStorage implements KeyValueStorage {
  InMemoryKeyValueStorage([Map<String, String>? initialValues])
    : _values = <String, String>{...?initialValues} {
    _values.keys.forEach(_check);
  }

  final Map<String, String> _values;

  void _check(String key) {
    if (!StorageKeys.all.contains(key)) {
      throw ArgumentError.value(key, 'key', 'is not in StorageKeys.all');
    }
  }

  @override
  String? readString(String key) {
    _check(key);
    return _values[key];
  }

  @override
  Future<void> writeString(String key, String value) async {
    _check(key);
    _values[key] = value;
  }

  @override
  Future<void> remove(String key) async {
    _check(key);
    _values.remove(key);
  }
}
