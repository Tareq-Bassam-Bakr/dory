import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/analytics/data/api_intelligence_repository.dart';
import 'package:dory_app/features/analytics/domain/business_intelligence.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/api_fixtures.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';

/// Insights and peak times (docs/AI_ARCHITECTURE.md): the app shows what the
/// server computed, and says plainly when there is not enough data.
void main() {
  Map<String, Object?> insightsJson({bool ready = true}) => <String, Object?>{
    'status': ready ? 'ready' : 'insufficient_data',
    'period': <String, Object?>{'days': 30},
    'minimum_tickets': 20,
    'metrics': <String, Object?>{
      'tickets': ready ? 120 : 6,
      'completed': ready ? 96 : 6,
      'completion_rate': ready ? 80 : 100,
      'no_show_rate': ready ? 18 : 0,
      'cancellation_rate': 5,
      'average_wait_minutes': ready ? 34 : 4,
      'median_wait_minutes': 30,
      'average_service_minutes': 9,
      'previous_tickets': 90,
      'change_percent': ready ? 33 : null,
    },
    'insights': ready
        ? <Object?>[
            <String, Object?>{
              'kind': 'no_show_high',
              'severity': 'warning',
              'title': 'Many customers miss their turn',
              'body': '18% of the customers who were called did not show up.',
              'facts': <String, Object?>{'rate': 18},
            },
            <String, Object?>{
              'kind': 'peak_concentration',
              'severity': 'suggestion',
              'title': 'Your busiest hours',
              'body': '52% of customers arrive at 10:00, 11:00, 12:00.',
              'facts': <String, Object?>{'share': 52},
            },
          ]
        : <Object?>[],
    'generated_by': 'template',
  };

  List<double> day(Map<int, double> values) =>
      List<double>.generate(24, (hour) => values[hour] ?? 0);
  List<String> levels(Map<int, String> values) =>
      List<String>.generate(24, (hour) => values[hour] ?? 'closed');

  Map<String, Object?> peakJson({bool ready = true}) => ready
      ? <String, Object?>{
          'status': 'ready',
          'timezone': 'Asia/Damascus',
          'tickets': 480,
          'days_covered': 56,
          'minimum': <String, Object?>{'tickets': 50, 'days': 14},
          'weekdays': <Object?>[
            for (int weekday = 1; weekday <= 7; weekday++)
              <String, Object?>{
                'weekday': weekday,
                'hours': day(<int, double>{
                  9: 2,
                  10: weekday == 1 ? 6 : 3,
                  15: 1,
                }),
                'levels': levels(<int, String>{
                  9: 'normal',
                  10: weekday == 1 ? 'busy' : 'normal',
                  15: 'quiet',
                }),
                'total': 6,
              },
          ],
          'busiest': <Object?>[
            <String, Object?>{'weekday': 1, 'hour': 10, 'average': 6},
          ],
          'quietest': <Object?>[
            <String, Object?>{'weekday': 3, 'hour': 15, 'average': 1},
          ],
          'upcoming': <Object?>[
            <String, Object?>{
              'date': '2026-11-09',
              'weekday': 1,
              'expected': 9,
              'peak_hour': 10,
            },
            <String, Object?>{
              'date': '2026-11-10',
              'weekday': 2,
              'expected': 0,
              'peak_hour': null,
            },
          ],
        }
      : <String, Object?>{
          'status': 'insufficient_data',
          'tickets': 6,
          'days_covered': 3,
          'minimum': <String, Object?>{'tickets': 50, 'days': 14},
          'weekdays': <Object?>[],
          'busiest': <Object?>[],
          'quietest': <Object?>[],
          'upcoming': <Object?>[],
        };

  FakeHttpAdapter api({bool ready = true}) => FakeHttpAdapter()
    ..reply('GET', '/businesses/$businessId', FakeResponse.ok(businessJson()))
    ..reply(
      'GET',
      '/businesses/$businessId/insights',
      FakeResponse.ok(insightsJson(ready: ready)),
    )
    ..reply(
      'GET',
      '/businesses/$businessId/peak-times',
      FakeResponse.ok(peakJson(ready: ready)),
    );

  group('parsing', () {
    test('insights and peak times', () {
      final InsightsReport insights =
          ApiIntelligenceRepository.insightsFromJson(insightsJson());
      expect(insights.ready, isTrue);
      expect(insights.metrics.noShowRate, 18);
      expect(insights.metrics.changePercent, 33);
      expect(insights.insights.first.severity, InsightSeverity.warning);
      expect(insights.generatedBy, 'template');

      final PeakTimesReport peaks = ApiIntelligenceRepository.peakTimesFromJson(
        peakJson(),
      );
      expect(peaks.ready, isTrue);
      expect(peaks.weekdays.first.hours[10], 6);
      expect(peaks.weekdays.first.levels[10], PeakLevel.busy);
      expect(peaks.busiest.single.hour, 10);
      expect(peaks.upcoming[1].peakHour, isNull);
    });
  });

  // The page's vertical list (the weekday chips scroll sideways).
  final Finder list = find.byType(Scrollable).first;

  group('insights screen', () {
    testWidgets('shows the metrics, the advice and the peak times', (
      tester,
    ) async {
      setTestWindowSize(tester, const Size(390, 900));
      await pumpAppAt(
        tester,
        AppRoutes.businessInsights(businessId),
        http: api(),
      );

      expect(find.text('Last 30 days'), findsOneWidget);
      expect(find.text('120'), findsOneWidget);
      expect(find.text('80%'), findsOneWidget);
      expect(find.text('~34 min'), findsOneWidget);
      expect(
        find.text('33% more customers than the previous period'),
        findsOneWidget,
      );
      expect(find.text('Many customers miss their turn'), findsOneWidget);
      expect(find.text('Your busiest hours'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.text('Busiest times'),
        300,
        scrollable: list,
      );
      expect(find.textContaining('Monday'), findsWidgets);
      await tester.scrollUntilVisible(
        find.text('This week'),
        300,
        scrollable: list,
      );
      expect(find.text('Usually no customers'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.textContaining("DORY's built-in rules"),
        300,
        scrollable: list,
      );
      expect(find.textContaining("DORY's built-in rules"), findsOneWidget);
    });

    testWidgets('says plainly when there is not enough data', (tester) async {
      setTestWindowSize(tester, const Size(390, 900));
      await pumpAppAt(
        tester,
        AppRoutes.businessInsights(businessId),
        http: api(ready: false),
      );

      expect(
        find.text(
          '6 customers so far. Recommendations start at 20, so that they rest on real activity.',
        ),
        findsOneWidget,
      );
      await tester.scrollUntilVisible(
        find.textContaining('Peak times need at least 50'),
        300,
        scrollable: list,
      );
      expect(
        find.text(
          'Peak times need at least 50 customers over 14 days. So far: 6 over 3 days.',
        ),
        findsOneWidget,
      );
      expect(find.text('Busiest times'), findsNothing);
    });

    testWidgets('the overview offers insights only to who may see them', (
      tester,
    ) async {
      setTestWindowSize(tester, const Size(390, 900));
      await pumpAppAt(tester, AppRoutes.business(businessId), http: api());
      await tester.scrollUntilVisible(
        find.text('Insights and peak times'),
        300,
        scrollable: list,
      );
      await tester.tap(find.text('Insights and peak times'));
      await tester.pumpAndSettle();
      expect(find.text('Insights'), findsWidgets);

      await pumpAppAt(
        tester,
        AppRoutes.business(businessId),
        http: api()
          ..reply(
            'GET',
            '/businesses/$businessId',
            FakeResponse.ok(
              businessJson(role: 'employee', permissions: employeePermissions),
            ),
          ),
      );
      expect(find.text('Insights and peak times'), findsNothing);
      // Unmount, and let the overview's pending refresh timers end.
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(minutes: 1));
    });
  });
}
