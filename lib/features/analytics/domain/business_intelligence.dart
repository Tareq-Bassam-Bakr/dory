import 'package:flutter/foundation.dart';

/// Business insights and peak times (docs/AI_ARCHITECTURE.md). Everything
/// comes from the business's own records on the server; the app only shows
/// it.

@immutable
class InsightMetrics {
  const InsightMetrics({
    required this.tickets,
    required this.completed,
    required this.previousTickets,
    this.completionRate,
    this.noShowRate,
    this.cancellationRate,
    this.averageWaitMinutes,
    this.medianWaitMinutes,
    this.averageServiceMinutes,
    this.changePercent,
  });

  final int tickets;
  final int completed;
  final int previousTickets;
  final int? completionRate;
  final int? noShowRate;
  final int? cancellationRate;
  final int? averageWaitMinutes;
  final int? medianWaitMinutes;
  final int? averageServiceMinutes;

  /// Tickets compared with the previous period, in percent.
  final int? changePercent;
}

enum InsightSeverity {
  warning,
  suggestion,
  positive;

  static InsightSeverity fromApi(String? value) => switch (value) {
    'warning' => InsightSeverity.warning,
    'positive' => InsightSeverity.positive,
    _ => InsightSeverity.suggestion,
  };
}

@immutable
class BusinessInsight {
  const BusinessInsight({
    required this.kind,
    required this.severity,
    required this.title,
    required this.body,
  });

  final String kind;
  final InsightSeverity severity;

  /// Worded by the server in the app's language.
  final String title;
  final String body;
}

@immutable
class InsightsReport {
  const InsightsReport({
    required this.ready,
    required this.days,
    required this.minimumTickets,
    required this.metrics,
    required this.insights,
    required this.generatedBy,
  });

  /// False: too few tickets for recommendations; metrics are still real.
  final bool ready;
  final int days;
  final int minimumTickets;
  final InsightMetrics metrics;
  final List<BusinessInsight> insights;

  /// "template": the built-in rules and texts (no external AI service).
  final String generatedBy;
}

enum PeakLevel {
  closed,
  quiet,
  normal,
  busy;

  static PeakLevel fromApi(String? value) => switch (value) {
    'busy' => PeakLevel.busy,
    'quiet' => PeakLevel.quiet,
    'normal' => PeakLevel.normal,
    _ => PeakLevel.closed,
  };
}

/// One weekday (1 = Monday) with the average customers per hour.
@immutable
class WeekdayActivity {
  const WeekdayActivity({
    required this.weekday,
    required this.hours,
    required this.levels,
    required this.total,
  });

  final int weekday;
  final List<double> hours;
  final List<PeakLevel> levels;
  final double total;
}

@immutable
class PeakSlot {
  const PeakSlot({
    required this.weekday,
    required this.hour,
    required this.average,
  });

  final int weekday;
  final int hour;
  final double average;
}

@immutable
class UpcomingDay {
  const UpcomingDay({
    required this.date,
    required this.weekday,
    required this.expected,
    this.peakHour,
  });

  final DateTime date;
  final int weekday;
  final double expected;
  final int? peakHour;
}

@immutable
class PeakTimesReport {
  const PeakTimesReport({
    required this.ready,
    required this.tickets,
    required this.daysCovered,
    required this.minimumTickets,
    required this.minimumDays,
    this.weekdays = const <WeekdayActivity>[],
    this.busiest = const <PeakSlot>[],
    this.quietest = const <PeakSlot>[],
    this.upcoming = const <UpcomingDay>[],
  });

  /// False: not enough history yet; nothing is forecast.
  final bool ready;
  final int tickets;
  final int daysCovered;
  final int minimumTickets;
  final int minimumDays;
  final List<WeekdayActivity> weekdays;
  final List<PeakSlot> busiest;
  final List<PeakSlot> quietest;
  final List<UpcomingDay> upcoming;
}

abstract interface class IntelligenceRepository {
  Future<InsightsReport> insights(String businessId);

  Future<PeakTimesReport> peakTimes(String businessId);
}
