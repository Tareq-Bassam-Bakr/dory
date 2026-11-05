import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// Headline queue figures for the dashboard.
///
/// No queue data source exists in Phase 01, so every tile renders the
/// explicit "no data" state rather than a fabricated number.
class QueueSummaryGrid extends StatelessWidget {
  const QueueSummaryGrid({super.key});

  /// From this width on, all four tiles fit on one row.
  static const double _singleRowMinWidth = 520;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final List<(String, IconData)> metrics = <(String, IconData)>[
      (l10n.dashboardMetricWaiting, Icons.people_outline_rounded),
      (l10n.dashboardMetricServing, Icons.support_agent_rounded),
      (l10n.dashboardMetricAverageWait, Icons.timer_outlined),
      (l10n.dashboardMetricServedToday, Icons.task_alt_rounded),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final int columns = constraints.maxWidth >= _singleRowMinWidth ? 4 : 2;
        const double gap = AppSpacing.md;
        final double tileWidth =
            (constraints.maxWidth - gap * (columns - 1)) / columns;

        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: <Widget>[
            for (final (String label, IconData icon) in metrics)
              SizedBox(
                width: tileWidth,
                child: DoryMetricTile(
                  label: label,
                  icon: icon,
                  noValueLabel: l10n.dashboardMetricNoValue,
                ),
              ),
          ],
        );
      },
    );
  }
}
