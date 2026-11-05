import 'package:dory_app/app/localization/generated/app_localizations.dart';
import 'package:flutter/widgets.dart';

export 'package:dory_app/app/localization/generated/app_localizations.dart';

/// Shorthand for `AppLocalizations.of(context)`.
extension AppLocalizationsContext on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}
