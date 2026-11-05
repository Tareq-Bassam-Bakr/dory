import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Holds the selected [AppLanguage] and persists changes.
class LanguageController extends Notifier<AppLanguage> {
  @override
  AppLanguage build() {
    final KeyValueStorage storage = ref.watch(keyValueStorageProvider);
    return AppLanguage.fromStorageCode(
      storage.readString(StorageKeys.languageCode),
    );
  }

  /// Applies [language] immediately, then persists it.
  ///
  /// A persistence failure is reported but does not revert the choice: the
  /// language stays applied for this session and the device keeps its
  /// previous stored value.
  Future<void> select(AppLanguage language) async {
    if (state == language) {
      return;
    }
    state = language;

    final KeyValueStorage storage = ref.read(keyValueStorageProvider);
    final String? code = language.storageCode;
    try {
      if (code == null) {
        await storage.remove(StorageKeys.languageCode);
      } else {
        await storage.writeString(StorageKeys.languageCode, code);
      }
    } on Object catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Failed to persist the selected language',
      );
    }
  }
}

final NotifierProvider<LanguageController, AppLanguage>
languageControllerProvider = NotifierProvider<LanguageController, AppLanguage>(
  LanguageController.new,
  name: 'languageControllerProvider',
);
