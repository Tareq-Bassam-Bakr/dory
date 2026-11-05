import 'package:dory_app/app/localization/l10n.dart';
import 'package:material_ui/material_ui.dart';

/// Tells the user, honestly, that a feature is not available yet.
///
/// Use for entry points whose feature belongs to a later phase, instead of
/// leaving taps silently unanswered or faking behaviour.
void showComingSoonMessage(BuildContext context) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(content: Text(context.l10n.commonComingSoonMessage)),
    );
}
