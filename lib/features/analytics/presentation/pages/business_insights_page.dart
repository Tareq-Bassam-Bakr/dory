import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/analytics/domain/business_intelligence.dart';
import 'package:dory_app/features/analytics/presentation/providers/intelligence_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

/// Insights and peak times of one business (docs/AI_ARCHITECTURE.md). The
/// numbers and recommendations come from the server, computed from the
/// business's own records; this screen only presents them, and says so
/// when there is not enough data yet.
class BusinessInsightsPage extends ConsumerWidget {
  const BusinessInsightsPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<InsightsReport> insights = ref.watch(
      insightsProvider(businessId),
    );

    // The plan has no insights (docs/SUBSCRIPTIONS.md): say so and offer
    // the plans, instead of an error. Peak times are not asked for then.
    final Object? error = insights.error;
    if (error is AppFailure && error.isFeatureNotInPlan) {
      return DoryPageScaffold(
        title: l10n.insightsTitle,
        body: PlanLockedView(
          businessId: businessId,
          message: l10n.planLockedInsights,
        ),
      );
    }

    final AsyncValue<PeakTimesReport> peaks = ref.watch(
      peakTimesProvider(businessId),
    );

    Future<void> refresh() async {
      ref
        ..invalidate(insightsProvider(businessId))
        ..invalidate(peakTimesProvider(businessId));
      await ref.read(insightsProvider(businessId).future);
    }

    return DoryPageScaffold(
      title: l10n.insightsTitle,
      // The list scrolls itself, with pull to refresh.
      scrollable: false,
      padding: EdgeInsets.zero,
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.pageGutter),
          children: <Widget>[
            AsyncValueView<InsightsReport>(
              value: insights,
              onRetry: () => ref.invalidate(insightsProvider(businessId)),
              data: (report) => _InsightsSection(report: report),
            ),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.peakTitle),
            AsyncValueView<PeakTimesReport>(
              value: peaks,
              onRetry: () => ref.invalidate(peakTimesProvider(businessId)),
              data: (report) => _PeakSection(report: report),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              l10n.insightsSource,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textMuted),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _InsightsSection extends StatelessWidget {
  const _InsightsSection({required this.report});

  final InsightsReport report;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final InsightMetrics metrics = report.metrics;
    String percent(int? value) =>
        value == null ? '' : l10n.insightsPercentValue(value);
    final int? change = metrics.changePercent;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DorySectionHeader(title: l10n.insightsPeriod(report.days)),
        Row(
          children: <Widget>[
            Expanded(
              child: DoryMetricTile(
                label: l10n.insightsCustomers,
                icon: Icons.groups_outlined,
                value: '${metrics.tickets}',
                noValueLabel: '0',
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: DoryMetricTile(
                label: l10n.insightsServed,
                icon: Icons.task_alt_rounded,
                value: metrics.completionRate == null
                    ? null
                    : percent(metrics.completionRate),
                noValueLabel: '—',
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        Row(
          children: <Widget>[
            Expanded(
              child: DoryMetricTile(
                label: l10n.insightsAverageWait,
                icon: Icons.hourglass_top_rounded,
                value: metrics.averageWaitMinutes == null
                    ? null
                    : l10n.queueEstimateValue(metrics.averageWaitMinutes!),
                noValueLabel: '—',
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: DoryMetricTile(
                label: l10n.insightsMissedTurns,
                icon: Icons.person_off_outlined,
                value: metrics.noShowRate == null
                    ? null
                    : percent(metrics.noShowRate),
                noValueLabel: '—',
              ),
            ),
          ],
        ),
        if (change != null && change != 0) ...<Widget>[
          const SizedBox(height: AppSpacing.sm),
          Text(
            change > 0
                ? l10n.insightsChangeUp(change)
                : l10n.insightsChangeDown(-change),
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.insightsRecommendations),
        if (!report.ready)
          DoryNotice(
            message: l10n.insightsNotEnoughData(
              metrics.tickets,
              report.minimumTickets,
            ),
          )
        else if (report.insights.isEmpty)
          DoryNotice(
            message: l10n.insightsNothingToChange,
            tone: DoryNoticeTone.success,
          )
        else
          for (final BusinessInsight insight in report.insights)
            _InsightCard(insight: insight),
      ],
    );
  }
}

class _InsightCard extends StatelessWidget {
  const _InsightCard({required this.insight});

  final BusinessInsight insight;

  @override
  Widget build(BuildContext context) {
    final (IconData icon, Color color) = switch (insight.severity) {
      InsightSeverity.warning => (
        Icons.warning_amber_rounded,
        AppColors.warning,
      ),
      InsightSeverity.suggestion => (
        Icons.lightbulb_outline_rounded,
        AppColors.primary,
      ),
      InsightSeverity.positive => (
        Icons.check_circle_outline_rounded,
        AppColors.success,
      ),
    };
    final TextTheme text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: DoryCard(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(icon, color: color),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(insight.title, style: text.titleSmall),
                  const SizedBox(height: AppSpacing.xs),
                  Text(insight.body, style: text.bodyMedium),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Weekday name in the app's language (1 = Monday).
String weekdayName(BuildContext context, int weekday) => DateFormat.EEEE(
  Localizations.localeOf(context).toLanguageTag(),
).format(DateTime(2024, 1, weekday)); // 1 January 2024 was a Monday.

String _hour(BuildContext context, int hour) =>
    formatClock(context, DateTime(2024, 1, 1, hour));

class _PeakSection extends StatefulWidget {
  const _PeakSection({required this.report});

  final PeakTimesReport report;

  @override
  State<_PeakSection> createState() => _PeakSectionState();
}

class _PeakSectionState extends State<_PeakSection> {
  late int _weekday = widget.report.upcoming.isEmpty
      ? 1
      : widget.report.upcoming.first.weekday;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final PeakTimesReport report = widget.report;
    if (!report.ready) {
      return DoryNotice(
        message: l10n.peakNotEnoughData(
          report.minimumTickets,
          report.minimumDays,
          report.tickets,
          report.daysCovered,
        ),
      );
    }

    final TextTheme text = Theme.of(context).textTheme;
    String slot(PeakSlot slot) => l10n.peakSlot(
      weekdayName(context, slot.weekday),
      _hour(context, slot.hour),
    );
    final WeekdayActivity? day = report.weekdays
        .where((d) => d.weekday == _weekday)
        .firstOrNull;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(l10n.peakBusiest, style: text.titleSmall),
              for (final PeakSlot busy in report.busiest)
                _SlotLine(
                  icon: Icons.trending_up_rounded,
                  color: AppColors.warning,
                  label: slot(busy),
                  value: busy.average,
                ),
              const SizedBox(height: AppSpacing.md),
              Text(l10n.peakQuietest, style: text.titleSmall),
              for (final PeakSlot quiet in report.quietest)
                _SlotLine(
                  icon: Icons.trending_down_rounded,
                  color: AppColors.success,
                  label: slot(quiet),
                  value: quiet.average,
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(l10n.peakPerHour, style: text.titleSmall),
        const SizedBox(height: AppSpacing.sm),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              for (int weekday = 1; weekday <= 7; weekday++)
                Padding(
                  padding: const EdgeInsetsDirectional.only(end: AppSpacing.sm),
                  child: ChoiceChip(
                    label: Text(weekdayName(context, weekday)),
                    selected: weekday == _weekday,
                    onSelected: (_) => setState(() => _weekday = weekday),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        if (day != null) _HourBars(day: day, weekdays: report.weekdays),
        const SizedBox(height: AppSpacing.lg),
        Text(l10n.peakThisWeek, style: text.titleSmall),
        const SizedBox(height: AppSpacing.sm),
        DoryCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: <Widget>[
              for (final UpcomingDay upcoming in report.upcoming)
                ListTile(
                  dense: true,
                  title: Text(weekdayName(context, upcoming.weekday)),
                  subtitle: Text(
                    upcoming.peakHour == null
                        ? l10n.peakUsuallyClosed
                        : l10n.peakExpected(
                            upcoming.expected.round(),
                            _hour(context, upcoming.peakHour!),
                          ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SlotLine extends StatelessWidget {
  const _SlotLine({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final Color color;
  final String label;
  final double value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.xs),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 18, color: color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: Text(label)),
          Text(
            context.l10n.peakAverage(
              NumberFormat.decimalPattern(
                Localizations.localeOf(context).toLanguageTag(),
              ).format(value),
            ),
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

/// Average customers per hour of one weekday, over the hours the business
/// is active on any day.
class _HourBars extends StatelessWidget {
  const _HourBars({required this.day, required this.weekdays});

  final WeekdayActivity day;
  final List<WeekdayActivity> weekdays;

  @override
  Widget build(BuildContext context) {
    final List<int> active = <int>[
      for (int hour = 0; hour < 24; hour++)
        if (weekdays.any((d) => hour < d.hours.length && d.hours[hour] > 0))
          hour,
    ];
    if (active.isEmpty) {
      return const SizedBox.shrink();
    }
    final int first = active.first;
    final int last = active.last;
    final double peak = weekdays
        .expand((d) => d.hours)
        .fold<double>(0, (max, value) => value > max ? value : max);
    Color colour(PeakLevel level) => switch (level) {
      PeakLevel.busy => AppColors.warning,
      PeakLevel.quiet => AppColors.success,
      PeakLevel.normal => AppColors.primary,
      PeakLevel.closed => AppColors.border,
    };

    return Semantics(
      label: context.l10n.peakPerHour,
      child: Column(
        children: <Widget>[
          SizedBox(
            height: 120,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: <Widget>[
                for (int hour = first; hour <= last; hour++)
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 1),
                      child: FractionallySizedBox(
                        heightFactor: peak <= 0
                            ? 0
                            : (day.hours[hour] / peak).clamp(0.02, 1.0),
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: colour(
                              hour < day.levels.length
                                  ? day.levels[hour]
                                  : PeakLevel.closed,
                            ),
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(3),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Text(
                _hour(context, first),
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Text(
                _hour(context, last),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Wrap(
            spacing: AppSpacing.md,
            children: <Widget>[
              _Legend(color: AppColors.warning, label: context.l10n.peakBusy),
              _Legend(color: AppColors.primary, label: context.l10n.peakNormal),
              _Legend(color: AppColors.success, label: context.l10n.peakQuiet),
            ],
          ),
        ],
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: <Widget>[
      Container(width: 10, height: 10, color: color),
      const SizedBox(width: AppSpacing.xs),
      Text(label, style: Theme.of(context).textTheme.bodySmall),
    ],
  );
}
