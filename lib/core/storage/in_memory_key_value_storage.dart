import 'package:dory_app/core/storage/key_value_storage.dart';

/// Volatile [KeyValueStorage].
///
/// Used in tests and as a fallback when platform storage cannot be opened,
/// so the app still starts (preferences are simply not persisted).
class InMemoryKeyValueStorage implements KeyValueStorage {
  InMemoryKeyValueStorage([Map<String, String>? initialValues])
    : _values = <String, String>{...?initialValues};

  final Map<String, String> _values;

  @override
  String? readString(String key) => _values[key];

  @override
  Future<void> writeString(String key, String value) async {
    _values[key] = value;
  }

  @override
  Future<void> remove(String key) async {
    _values.remove(key);
  }
}
