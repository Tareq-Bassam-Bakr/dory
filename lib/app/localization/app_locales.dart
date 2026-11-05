import 'package:dory_app/app/localization/generated/app_localizations.dart';
import 'package:material_ui/material_ui.dart';

/// Locales DORY ships with, and the delegates that serve them.
abstract final class AppLocales {
  static const Locale english = Locale('en');
  static const Locale arabic = Locale('ar');

  /// Order matters: the first entry is the fallback for unsupported devices.
  static const List<Locale> supported = <Locale>[english, arabic];

  /// Uses material_ui's localization delegates (Material, Cupertino and
  /// Widgets) rather than `AppLocalizations.localizationsDelegates`, which
  /// gen-l10n still wires to the framework's legacy Material library.
  static const List<LocalizationsDelegate<dynamic>> delegates =
      <LocalizationsDelegate<dynamic>>[
        AppLocalizations.delegate,
        ...GlobalMaterialLocalizations.delegates,
      ];
}

/// The user's language choice. [system] follows the device language.
enum AppLanguage {
  system(null),
  english(AppLocales.english),
  arabic(AppLocales.arabic);

  const AppLanguage(this.locale);

  /// Locale forced on the app, or `null` to follow the device.
  final Locale? locale;

  /// Value persisted in storage. [system] is stored as "no value".
  String? get storageCode => locale?.languageCode;

  /// Parses a stored language code. Unknown or missing codes map to [system].
  static AppLanguage fromStorageCode(String? code) {
    for (final AppLanguage language in values) {
      if (language.storageCode != null && language.storageCode == code) {
        return language;
      }
    }
    return system;
  }
}
