import 'dart:convert';
import 'dart:io';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/bootstrap.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/presentation/pages/login_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/discovery/presentation/pages/explore_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:dory_app/features/queues/presentation/pages/queue_dashboard_page.dart';
import 'package:dory_app/features/tickets/presentation/pages/customer_ticket_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:integration_test/integration_test.dart';
import 'package:material_ui/material_ui.dart';

/// Real-time on a real device (docs/REALTIME_ARCHITECTURE.md), against a
/// running API, Reverb and queue worker on a throwaway database:
///
///   php artisan serve, php artisan reverb:start, php artisan queue:work
///   adb reverse tcp:8000 tcp:8000
///   adb reverse tcp:8080 tcp:8080
///   flutter test integration_test/phase06_realtime_test.dart -d DEVICE_ID
///     --dart-define=DORY_REVERB_APP_KEY=...
///     --dart-define=DORY_E2E_STAFF_EMAIL=... --dart-define=DORY_E2E_STAFF_PASSWORD=...
///
/// The staff account must run the queues that discovery lists first, as the
/// owner of the demo data does. Never point it at production data: it joins,
/// calls and completes real tickets.
///
/// Polling would take 15 seconds. These tests require each change on screen
/// within [_liveLimit], and live mode has no timer at all, so passing means
/// the change came through Reverb.
const Duration _liveLimit = Duration(seconds: 6);

const String _staffEmail = String.fromEnvironment('DORY_E2E_STAFF_EMAIL');
const String _staffPassword = String.fromEnvironment('DORY_E2E_STAFF_PASSWORD');
const String _apiBase = String.fromEnvironment(
  'DORY_API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  final bool configured =
      _staffEmail.isNotEmpty &&
      _staffPassword.isNotEmpty &&
      const String.fromEnvironment('DORY_REVERB_APP_KEY').isNotEmpty;
  if (!configured) {
    debugPrint(
      'Skipped: needs DORY_REVERB_APP_KEY, DORY_E2E_STAFF_EMAIL and '
      'DORY_E2E_STAFF_PASSWORD.',
    );
  }

  final _Api api = _Api(_apiBase);

  /// Pumps until [done], and says how long it took.
  Future<Duration> pumpUntilTrue(
    WidgetTester tester,
    bool Function() done,
    String what, {
    Duration timeout = const Duration(seconds: 30),
  }) async {
    final Stopwatch watch = Stopwatch()..start();
    while (!done()) {
      if (watch.elapsed > timeout) {
        fail('Timed out after ${watch.elapsed} waiting for $what');
      }
      await tester.pump(const Duration(milliseconds: 100));
    }
    final Duration took = watch.elapsed;
    await tester.pump(const Duration(milliseconds: 300));
    return took;
  }

  Future<Duration> pumpUntil(
    WidgetTester tester,
    Finder finder, {
    Duration timeout = const Duration(seconds: 30),
  }) => pumpUntilTrue(
    tester,
    () => finder.evaluate().isNotEmpty,
    '$finder',
    timeout: timeout,
  );

  Future<void> tap(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder.first);
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(finder.first);
    await tester.pump(const Duration(milliseconds: 500));
  }

  AppLocalizations l10nOf(WidgetTester tester, Type page) =>
      AppLocalizations.of(tester.element(find.byType(page).first));

  Future<void> startApp(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    // bootstrap() installs the production error handlers. Putting the test
    // framework's handler back keeps the real message of a failure.
    final FlutterExceptionHandler? testErrorHandler = FlutterError.onError;
    await bootstrap();
    FlutterError.onError = testErrorHandler;
    await tester.pump(const Duration(seconds: 2));
  }

  Future<String> staffToken() async {
    final Map<String, Object?> body = await api.send(
      'POST',
      '/auth/login',
      body: <String, Object?>{
        'email': _staffEmail,
        'password': _staffPassword,
        'device_name': 'phase06-device-test',
      },
    );
    return ((body['data']! as Map)['token']! as Map)['access_token']! as String;
  }

  testWidgets(
    'a guest ticket follows staff actions through Reverb',
    skip: !configured,
    (tester) async {
      await startApp(tester);
      await pumpUntil(tester, find.byType(WelcomePage));
      await tap(
        tester,
        find.text(l10nOf(tester, WelcomePage).welcomeExploreAsGuest),
      );
      await pumpUntil(tester, find.byType(CustomerHomePage));
      final AppLocalizations l10n = l10nOf(tester, CustomerHomePage);

      // Explore → first business → first branch → first open service.
      await tap(tester, find.text(l10n.navExplore));
      await pumpUntil(tester, find.byType(ExplorePage));
      // The first requests after a build can take half a minute on a
      // local PHP development server.
      await pumpUntil(
        tester,
        find.byType(Card),
        timeout: const Duration(seconds: 60),
      );
      await tap(tester, find.byType(Card));
      await pumpUntil(tester, find.text(l10n.businessDetailsBranches));
      await pumpUntil(tester, find.byIcon(Icons.chevron_right_rounded));
      await tap(tester, find.byIcon(Icons.chevron_right_rounded));
      await pumpUntil(tester, find.text(l10n.branchDetailsServices));
      await pumpUntil(tester, find.text(l10n.queueStateOpen));
      await tap(tester, find.text(l10n.queueStateOpen));

      await pumpUntil(tester, find.text(l10n.joinAsGuestAction));
      final Finder name = find.byType(TextFormField);
      if (name.evaluate().isNotEmpty) {
        await tester.enterText(name.first, 'Live Device Guest');
      }
      await tap(tester, find.text(l10n.joinAsGuestAction));
      await pumpUntil(tester, find.byType(CustomerTicketPage));
      await pumpUntil(tester, find.text(l10n.ticketHeadlineWaiting));

      // Connected to Reverb, the screen says so and stops polling.
      final Duration connected = await pumpUntil(
        tester,
        find.text(l10n.ticketLiveNote),
      );
      debugPrint('Live on the ticket screen after $connected');

      // The ticket screen's own route: /customer/ticket/{id}.
      final String ticketId = GoRouterState.of(
        tester.element(find.byType(CustomerTicketPage)),
      ).uri.pathSegments.last;
      final String token = await staffToken();

      for (final (String action, String headline) in <(String, String)>[
        ('call', l10n.ticketHeadlineCalled),
        ('start', l10n.ticketHeadlineServing),
        ('complete', l10n.ticketHeadlineCompleted),
      ]) {
        await api.send('POST', '/tickets/$ticketId/$action', token: token);
        final Duration took = await pumpUntil(
          tester,
          find.text(headline),
          timeout: _liveLimit,
        );
        debugPrint('Staff "$action" reached the ticket screen in $took');
      }
    },
  );

  testWidgets(
    'the staff dashboard shows a new customer at once',
    skip: !configured,
    (tester) async {
      // A queue staff run, and its session (from a first guest's ticket).
      final List<Object?> queues =
          (await api.send('GET', '/discovery/queues'))['data']!
              as List<Object?>;
      final Map<Object?, Object?> queue = queues
          .cast<Map<Object?, Object?>>()
          .firstWhere(
            (q) =>
                q['accepting_joins'] == true &&
                q['allow_guest_join'] == true &&
                q['require_guest_phone'] != true,
          );
      Future<Map<Object?, Object?>> guestJoins(String guest) async =>
          (await api.send(
                'POST',
                '/public/queues/${queue['id']}/join',
                body: <String, Object?>{'name': guest},
              ))['data']!
              as Map<Object?, Object?>;
      final Map<Object?, Object?> first = await guestJoins('Live Guest A');
      final String channel =
          (first['realtime']! as Map<Object?, Object?>)['public_channel']!
              as String;
      final String sessionId = channel.split('.').last;

      // Sign in as staff through the real login screen.
      await startApp(tester);
      await pumpUntil(tester, find.byType(WelcomePage));
      final AppLocalizations l10n = l10nOf(tester, WelcomePage);
      await tap(tester, find.text(l10n.welcomeLogin));
      await pumpUntil(tester, find.byType(LoginPage));
      await tester.enterText(
        find.widgetWithText(TextFormField, l10n.authEmailLabel),
        _staffEmail,
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, l10n.authPasswordLabel),
        _staffPassword,
      );
      await tap(tester, find.text(l10n.loginSubmit).last);
      final DateTime deadline = DateTime.now().add(const Duration(seconds: 30));
      while (find.byType(LoginPage).evaluate().isNotEmpty) {
        if (DateTime.now().isAfter(deadline)) {
          fail('Signing in did not leave the login screen');
        }
        await tester.pump(const Duration(milliseconds: 200));
      }
      await tester.pump(const Duration(seconds: 1));

      GoRouter.of(
        tester.element(find.byType(Scaffold).first),
      ).go(AppRoutes.queueSession(sessionId));
      await pumpUntil(tester, find.byType(QueueDashboardPage));
      final Duration connected = await pumpUntil(
        tester,
        find.text(l10n.queueRealtimeLive),
      );
      debugPrint('Live on the staff dashboard after $connected');

      int waiting() {
        final Iterable<DoryMetricTile> tiles = tester
            .widgetList<DoryMetricTile>(find.byType(DoryMetricTile))
            .where((tile) => tile.label == l10n.queueCountWaiting);
        return tiles.isEmpty ? -1 : int.tryParse(tiles.first.value ?? '') ?? -1;
      }

      await pumpUntilTrue(tester, () => waiting() >= 1, 'the first guest');
      final int before = waiting();
      await guestJoins('Live Guest B');
      final Duration took = await pumpUntilTrue(
        tester,
        () => waiting() == before + 1,
        'the second guest on the dashboard',
        timeout: _liveLimit,
      );
      debugPrint('A new customer reached the staff dashboard in $took');
    },
  );
}

/// Calls the API directly, as staff or as another guest would from their
/// own phone.
class _Api {
  _Api(this.base);

  final String base;
  final HttpClient _http = HttpClient();

  Future<Map<String, Object?>> send(
    String method,
    String path, {
    Map<String, Object?>? body,
    String? token,
  }) async {
    final HttpClientRequest request = await _http.openUrl(
      method,
      Uri.parse('$base$path'),
    );
    request.headers.set(HttpHeaders.acceptHeader, 'application/json');
    if (token != null) {
      request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
    }
    if (body != null) {
      request.headers.contentType = ContentType.json;
      request.write(jsonEncode(body));
    }
    final HttpClientResponse response = await request.close();
    final String text = await response.transform(utf8.decoder).join();
    if (response.statusCode >= 300) {
      // The status only: bodies can hold personal data or tokens.
      fail('$method $path answered ${response.statusCode}');
    }
    return jsonDecode(text) as Map<String, Object?>;
  }
}
