import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/profile/presentation/widgets/workspace_switch_tile.dart';
import 'package:dory_app/shared/components/language_picker.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Account tab, shared by the customer and business workspaces.
///
/// Phase 01 has no session, so the user is always shown as a guest.
class AccountPage extends ConsumerWidget {
  const AccountPage({super.key, required this.workspace})
    : assert(
        workspace != AppWorkspace.administration,
        'The administration workspace has no mobile shell.',
      );

  /// Workspace whose shell hosts this page.
  final AppWorkspace workspace;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AppLanguage language = ref.watch(languageControllerProvider);

    return DoryPageScaffold(
      title: l10n.accountTitle,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const _GuestCard(),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.accountPreferencesSection),
          DoryCard(
            padding: EdgeInsets.zero,
            child: ListTile(
              leading: const Icon(Icons.translate_rounded),
              title: Text(l10n.accountLanguage),
              subtitle: Text(appLanguageLabel(l10n, language)),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => showLanguagePicker(context),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.accountWorkspaceSection),
          DoryCard(
            padding: EdgeInsets.zero,
            child: WorkspaceSwitchTile(currentWorkspace: workspace),
          ),
        ],
      ),
    );
  }
}

class _GuestCard extends StatelessWidget {
  const _GuestCard();

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const CircleAvatar(
                radius: 24,
                backgroundColor: AppColors.primaryContainer,
                foregroundColor: AppColors.primary,
                child: Icon(Icons.person_outline_rounded),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Text(l10n.accountGuestTitle, style: text.titleMedium),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(l10n.accountGuestMessage, style: text.bodyMedium),
          const SizedBox(height: AppSpacing.lg),
          DoryButton(
            label: l10n.welcomeLogin,
            onPressed: () => context.go(AppRoutes.login),
          ),
        ],
      ),
    );
  }
}
