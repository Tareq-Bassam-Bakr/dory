import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Entry to business creation: explains the five onboarding steps, then
/// starts them.
class CreateBusinessPage extends StatelessWidget {
  const CreateBusinessPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final List<(IconData, String)> steps = <(IconData, String)>[
      (Icons.badge_outlined, l10n.onboardingStepBasics),
      (Icons.contact_phone_outlined, l10n.onboardingStepDetails),
      (Icons.store_mall_directory_outlined, l10n.onboardingStepBranch),
      (Icons.design_services_outlined, l10n.onboardingStepServices),
      (Icons.fact_check_outlined, l10n.onboardingStepReview),
    ];

    return DoryPageScaffold(
      title: l10n.createBusinessTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(l10n.createBusinessIntro, style: text.bodyLarge),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.createBusinessStepsTitle),
          DoryCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: <Widget>[
                for (final (int index, (IconData icon, String label))
                    in steps.indexed)
                  ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppColors.primaryContainer,
                      foregroundColor: AppColors.primary,
                      child: Icon(icon, size: 20),
                    ),
                    title: Text(label),
                    trailing: Text('${index + 1}', style: text.labelLarge),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: l10n.createBusinessStart,
            icon: Icons.arrow_forward_rounded,
            onPressed: () => context.push(AppRoutes.businessOnboarding),
          ),
        ],
      ),
    );
  }
}
