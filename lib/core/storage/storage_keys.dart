/// Every key persisted through `KeyValueStorage`.
///
/// Keys are namespaced with `dory.` and must be added to [all], which is the
/// allow-list handed to shared_preferences.
abstract final class StorageKeys {
  static const String languageCode = 'dory.language_code';

  static const Set<String> all = <String>{languageCode};
}
