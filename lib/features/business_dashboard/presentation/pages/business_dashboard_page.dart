import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/business_dashboard/presentation/widgets/business_setup_card.dart';
import 'package:dory_app/features/business_dashboard/presentation/widgets/queue_summary_grid.dart';
import 'package:dory_app/shared/components/coming_soon.dart';
import 'package:material_ui/material_ui.dart';

/// Business dashboard: overview, queue summary, and employees.
///
/// Phase 01 renders the layout with explicit empty states only.
class BusinessDashboardPage extends StatelessWidget {
  const BusinessDashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;

    return DoryPageScaffold(
      title: l10n.dashboardTitle,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          DorySectionHeader(title: l10n.dashboardOverviewTitle),
          BusinessSetupCard(onRegister: () => showComingSoonMessage(context)),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.dashboardQueueSummaryTitle),
          const QueueSummaryGrid(),
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.dashboardQueueSummaryEmpty,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.dashboardEmployeesTitle),
          DoryCard(
            child: DoryEmptyState(
              icon: Icons.badge_outlined,
              title: l10n.dashboardEmployeesEmptyTitle,
              message: l10n.dashboardEmployeesEmptyMessage,
            ),
          ),
        ],
      ),
    );
  }
}
