import 'dart:async';

import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Localized display name of an [AppLanguage].
///
/// Language names are shown in their own language (English, العربية) so a
/// user can always find their language, whatever the current UI language.
String appLanguageLabel(AppLocalizations l10n, AppLanguage language) {
  return switch (language) {
    AppLanguage.system => l10n.languageSystem,
    AppLanguage.english => l10n.languageEnglish,
    AppLanguage.arabic => l10n.languageArabic,
  };
}

/// Opens a bottom sheet listing the available languages.
Future<void> showLanguagePicker(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (context) => const _LanguagePickerSheet(),
  );
}

/// App bar action that opens the language picker.
class LanguagePickerButton extends StatelessWidget {
  const LanguagePickerButton({super.key});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: context.l10n.accountLanguage,
      icon: const Icon(Icons.translate_rounded),
      onPressed: () => showLanguagePicker(context),
    );
  }
}

class _LanguagePickerSheet extends ConsumerWidget {
  const _LanguagePickerSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AppLanguage selected = ref.watch(languageControllerProvider);

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.xl,
                vertical: AppSpacing.sm,
              ),
              child: Text(
                l10n.accountLanguage,
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
            for (final AppLanguage language in AppLanguage.values)
              ListTile(
                key: ValueKey<AppLanguage>(language),
                title: Text(appLanguageLabel(l10n, language)),
                selected: language == selected,
                trailing: language == selected
                    ? const Icon(Icons.check_rounded)
                    : null,
                onTap: () {
                  // The language applies synchronously; persisting finishes
                  // in the background and reports its own failures.
                  unawaited(
                    ref
                        .read(languageControllerProvider.notifier)
                        .select(language),
                  );
                  Navigator.of(context).pop();
                },
              ),
          ],
        ),
      ),
    );
  }
}
