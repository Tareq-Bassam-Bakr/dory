import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/profile/presentation/widgets/workspace_switch_tile.dart';
import 'package:dory_app/shared/components/language_picker.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Account tab, shared by the customer and business workspaces: the
/// signed-in user (or a sign-in prompt for guests), preferences and the
/// workspace switch.
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
    final AuthState auth = ref.watch(authControllerProvider);

    return DoryPageScaffold(
      title: l10n.accountTitle,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          switch (auth) {
            Authenticated(:final user, :final isStale) => _UserCard(
              user: user,
              isStale: isStale,
            ),
            AuthRestoring() => const DoryCard(
              child: SizedBox(height: 96, child: DoryLoadingIndicator()),
            ),
            Unauthenticated() => const _GuestCard(),
          },
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

class _UserCard extends ConsumerStatefulWidget {
  const _UserCard({required this.user, required this.isStale});

  final AuthUser user;
  final bool isStale;

  @override
  ConsumerState<_UserCard> createState() => _UserCardState();
}

class _UserCardState extends ConsumerState<_UserCard> {
  bool _loggingOut = false;

  Future<void> _logout() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: Text(l10n.accountLogoutConfirmTitle),
            content: Text(l10n.accountLogoutConfirmMessage),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(l10n.commonCancel),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: Text(l10n.accountLogout),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) {
      return;
    }

    setState(() => _loggingOut = true);
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) {
      context.go(AppRoutes.welcome);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final AuthUser user = widget.user;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(l10n.accountSignedInSection, style: text.labelMedium),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: <Widget>[
              CircleAvatar(
                radius: 24,
                backgroundColor: AppColors.primaryContainer,
                foregroundColor: AppColors.primary,
                child: Text(
                  user.name.characters.first.toUpperCase(),
                  style: text.titleMedium?.copyWith(color: AppColors.primary),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(user.name, style: text.titleMedium),
                    Text(
                      user.email,
                      style: text.bodyMedium,
                      textDirection: TextDirection.ltr,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (widget.isStale) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryNotice(
              message: l10n.accountOfflineNotice,
              tone: DoryNoticeTone.warning,
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          DoryButton(
            label: l10n.accountLogout,
            icon: Icons.logout_rounded,
            variant: DoryButtonVariant.secondary,
            isLoading: _loggingOut,
            onPressed: _logout,
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
