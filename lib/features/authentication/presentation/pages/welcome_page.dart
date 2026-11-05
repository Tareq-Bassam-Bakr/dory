import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/shared/components/language_picker.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// First screen for visitors: brand, value proposition, and entry points.
class WelcomePage extends StatelessWidget {
  const WelcomePage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;

    return DoryPageScaffold(
      maxContentWidth: AppLayout.maxFormWidth,
      actions: const <Widget>[LanguagePickerButton()],
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const SizedBox(height: AppSpacing.xxl),
          DoryLogo(markSize: 80, tagline: l10n.appTagline),
          const SizedBox(height: AppSpacing.xxxl),
          Text(
            l10n.welcomeHeadline,
            style: text.headlineMedium,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.welcomeBody,
            style: text.bodyLarge?.copyWith(color: AppColors.textSecondary),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: AppSpacing.xxxl),
          DoryButton(
            label: l10n.welcomeLogin,
            onPressed: () => context.go(AppRoutes.login),
          ),
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.welcomeCreateAccount,
            variant: DoryButtonVariant.secondary,
            onPressed: () => context.go(AppRoutes.register),
          ),
          const SizedBox(height: AppSpacing.sm),
          DoryButton(
            label: l10n.welcomeExploreAsGuest,
            variant: DoryButtonVariant.tertiary,
            onPressed: () => context.go(AppRoutes.customerHome),
          ),
        ],
      ),
    );
  }
}
