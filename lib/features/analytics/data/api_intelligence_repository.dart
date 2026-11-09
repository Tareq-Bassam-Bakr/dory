import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/analytics/domain/business_intelligence.dart';

/// GET /businesses/{id}/insights and /peak-times.
class ApiIntelligenceRepository implements IntelligenceRepository {
  const ApiIntelligenceRepository(this._api);

  final ApiClient _api;

  @override
  Future<InsightsReport> insights(String businessId) async => insightsFromJson(
    (await _api.get('/businesses/$businessId/insights')).dataObject,
  );

  @override
  Future<PeakTimesReport> peakTimes(String businessId) async =>
      peakTimesFromJson(
        (await _api.get('/businesses/$businessId/peak-times')).dataObject,
      );

  static InsightsReport insightsFromJson(JsonMap json) {
    final JsonMap metrics = json.object('metrics');
    return InsightsReport(
      ready: json.stringOrNull('status') == 'ready',
      days: json.objectOrNull('period')?.integerOrNull('days') ?? 30,
      minimumTickets: json.integerOrNull('minimum_tickets') ?? 0,
      metrics: InsightMetrics(
        tickets: metrics.integerOrNull('tickets') ?? 0,
        completed: metrics.integerOrNull('completed') ?? 0,
        previousTickets: metrics.integerOrNull('previous_tickets') ?? 0,
        completionRate: metrics.integerOrNull('completion_rate'),
        noShowRate: metrics.integerOrNull('no_show_rate'),
        cancellationRate: metrics.integerOrNull('cancellation_rate'),
        averageWaitMinutes: metrics.integerOrNull('average_wait_minutes'),
        medianWaitMinutes: metrics.integerOrNull('median_wait_minutes'),
        averageServiceMinutes: metrics.integerOrNull('average_service_minutes'),
        changePercent: metrics.integerOrNull('change_percent'),
      ),
      insights: json
          .objectList('insights')
          .map(
            (item) => BusinessInsight(
              kind: item.string('kind'),
              severity: InsightSeverity.fromApi(item.stringOrNull('severity')),
              title: item.string('title'),
              body: item.string('body'),
            ),
          )
          .toList(),
      generatedBy: json.stringOrNull('generated_by') ?? 'template',
    );
  }

  static PeakTimesReport peakTimesFromJson(JsonMap json) {
    final JsonMap minimum =
        json.objectOrNull('minimum') ?? const <String, Object?>{};
    List<double> numbers(Object? value) => value is List
        ? value.map((item) => item is num ? item.toDouble() : 0.0).toList()
        : const <double>[];
    PeakSlot slot(JsonMap item) => PeakSlot(
      weekday: item.integer('weekday'),
      hour: item.integer('hour'),
      average: item.doubleOrNull('average') ?? 0,
    );

    return PeakTimesReport(
      ready: json.stringOrNull('status') == 'ready',
      tickets: json.integerOrNull('tickets') ?? 0,
      daysCovered: json.integerOrNull('days_covered') ?? 0,
      minimumTickets: minimum.integerOrNull('tickets') ?? 0,
      minimumDays: minimum.integerOrNull('days') ?? 0,
      weekdays: json
          .objectList('weekdays')
          .map(
            (day) => WeekdayActivity(
              weekday: day.integer('weekday'),
              hours: numbers(day['hours']),
              levels: day['levels'] is List
                  ? (day['levels']! as List)
                        .map((level) => PeakLevel.fromApi(level as String?))
                        .toList()
                  : const <PeakLevel>[],
              total: day.doubleOrNull('total') ?? 0,
            ),
          )
          .toList(),
      busiest: json.objectList('busiest').map(slot).toList(),
      quietest: json.objectList('quietest').map(slot).toList(),
      upcoming: json
          .objectList('upcoming')
          .map(
            (day) => UpcomingDay(
              date: DateTime.parse(day.string('date')),
              weekday: day.integer('weekday'),
              expected: day.doubleOrNull('expected') ?? 0,
              peakHour: day.integerOrNull('peak_hour'),
            ),
          )
          .toList(),
    );
  }
}
