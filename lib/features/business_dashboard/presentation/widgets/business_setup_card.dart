import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// Invites an owner without a registered business to create one.
class BusinessSetupCard extends StatelessWidget {
  const BusinessSetupCard({super.key, required this.onRegister});

  final VoidCallback onRegister;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const DecoratedBox(
            decoration: BoxDecoration(
              color: AppColors.primaryContainer,
              borderRadius: AppRadii.mdAll,
            ),
            child: Padding(
              padding: EdgeInsets.all(AppSpacing.md),
              child: Icon(Icons.storefront_outlined, color: AppColors.primary),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(l10n.dashboardSetupTitle, style: text.titleMedium),
          const SizedBox(height: AppSpacing.xs),
          Text(l10n.dashboardSetupMessage, style: text.bodyMedium),
          const SizedBox(height: AppSpacing.lg),
          DoryButton(
            label: l10n.dashboardSetupAction,
            icon: Icons.add_rounded,
            onPressed: onRegister,
          ),
        ],
      ),
    );
  }
}
