import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fake_notifications.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

/// The notification center and its settings (docs/NOTIFICATION_CENTER.md).
void main() {
  const String first = '11111111-1111-4111-8111-111111111111';
  const String second = '22222222-2222-4222-8222-222222222222';

  Map<String, Object?> note(
    String id, {
    required String title,
    bool read = false,
    String kind = 'ticket_called',
  }) => <String, Object?>{
    'id': id,
    'kind': kind,
    'title': title,
    'body': '$title, ticket BAR-024.',
    'ticket': <String, Object?>{'id': customerTicketId, 'number': 'BAR-024'},
    'queue': <String, Object?>{'id': publicQueueId, 'name': 'General'},
    'business': <String, Object?>{'id': publicBusinessId, 'name': 'Shifa'},
    'for_staff': kind.startsWith('staff_'),
    'read': read,
    'read_at': read ? '2026-11-08T10:05:00+00:00' : null,
    'created_at': '2026-11-08T10:00:00+00:00',
  };

  FakeResponse page(List<Map<String, Object?>> items, {int unread = 1}) =>
      FakeResponse.ok(
        items,
        meta: <String, Object?>{
          'pagination': <String, Object?>{
            'current_page': 1,
            'per_page': 20,
            'total': items.length,
            'last_page': 1,
          },
          'unread_count': unread,
        },
      );

  FakeHttpAdapter api({List<Map<String, Object?>>? items}) {
    final FakeHttpAdapter http = customerApi();
    // A small stateful server: reading changes what is listed next.
    final List<Map<String, Object?>> all =
        items ??
        <Map<String, Object?>>[
          note(first, title: "It's your turn"),
          note(second, title: 'Visit completed', read: true),
        ];
    int unread() => all.where((item) => item['read'] == false).length;
    void markRead(Map<String, Object?> item) {
      item['read'] = true;
      item['read_at'] = '2026-11-08T10:05:00+00:00';
    }

    http.on('GET', '/me/notifications', (request) {
      final String filter =
          request.queryParameters['filter'] as String? ?? 'all';
      return page(<Map<String, Object?>>[
        for (final Map<String, Object?> item in all)
          if (filter == 'all' ||
              (filter == 'unread' && item['read'] == false) ||
              (filter == 'read' && item['read'] == true))
            item,
      ], unread: unread());
    });
    http.on('POST', '/me/notifications/$first/read', (request) {
      all.where((item) => item['id'] == first).forEach(markRead);
      return FakeResponse.ok(
        all.firstWhere((item) => item['id'] == first),
        meta: <String, Object?>{'unread_count': unread()},
      );
    });
    http.on('POST', '/me/notifications/read-all', (request) {
      all.forEach(markRead);
      return FakeResponse.ok(
        <String, Object?>{'marked': 1},
        meta: <String, Object?>{'unread_count': 0},
      );
    });
    return http..reply(
      'GET',
      '/me/notification-preferences',
      FakeResponse.ok(<String, Object?>{
        'ticket_updates': true,
        'almost_turn': true,
        'queue_updates': true,
        'staff_new_tickets': true,
        'staff_cancellations': true,
        'push_enabled': true,
      }),
    );
  }

  group('notification center', () {
    testWidgets('lists the server notifications, newest first', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
      );

      expect(find.text("It's your turn"), findsOneWidget);
      expect(find.text('Visit completed'), findsOneWidget);
      // One unread dot: the first notification.
      expect(find.byIcon(Icons.circle), findsOneWidget);
    });

    testWidgets('filters unread and read', (tester) async {
      final FakeHttpAdapter http = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.text('Unread'));
      await tester.pumpAndSettle();
      expect(find.text("It's your turn"), findsOneWidget);
      expect(find.text('Visit completed'), findsNothing);

      await tester.tap(find.text('Read'));
      await tester.pumpAndSettle();
      expect(find.text("It's your turn"), findsNothing);
      expect(find.text('Visit completed'), findsOneWidget);
      expect(
        http.queries('/me/notifications').map((q) => q['filter']),
        <Object?>['unread', 'all', 'unread', 'read'],
      );
    });

    testWidgets('opening one marks it read and shows its ticket', (
      tester,
    ) async {
      final FakeHttpAdapter http = api()
        ..reply(
          'GET',
          '/tickets/$customerTicketId/position',
          FakeResponse.ok(customerTicketJson()),
        );
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.text("It's your turn"));
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/me/notifications/$first/read'), hasLength(1));
      expect(topLocation(router), AppRoutes.customerTicket(customerTicketId));
    });

    testWidgets('mark all as read', (tester) async {
      final FakeHttpAdapter http = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.byTooltip('Mark all as read'));
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/me/notifications/read-all'), hasLength(1));
      expect(find.byTooltip('Mark all as read'), findsNothing);
    });

    testWidgets('an empty center says what will appear there', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(items: const <Map<String, Object?>>[]),
      );

      expect(find.text('No notifications yet'), findsOneWidget);
    });

    testWidgets('guests are invited to sign in', (tester) async {
      final FakeHttpAdapter http = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository(),
        http: http,
      );

      expect(find.text('Sign in to see notifications'), findsOneWidget);
      expect(http.sent('GET', '/me/notifications'), isEmpty);
    });

    testWidgets('the bell shows the unread count', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
      );

      expect(find.byTooltip('Notifications, 1 unread'), findsOneWidget);
    });

    testWidgets('in Arabic, the screen and the server texts are Arabic', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await storage.writeString(
        StorageKeys.languageCode,
        AppLocales.arabic.languageCode,
      );
      final FakeHttpAdapter http = api(
        items: <Map<String, Object?>>[note(first, title: 'حان دورك')],
      );
      await pumpAppAt(
        tester,
        AppRoutes.customerNotifications,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
        storage: storage,
      );

      expect(find.text('الإشعارات'), findsOneWidget);
      expect(find.text('حان دورك'), findsOneWidget);
      expect(
        http.sent('GET', '/me/notifications').last.headers['Accept-Language'],
        'ar',
      );
    });
  });

  group('notification settings', () {
    testWidgets('a preference is saved when switched', (tester) async {
      final FakeHttpAdapter http = api()
        ..reply(
          'PUT',
          '/me/notification-preferences',
          FakeResponse.ok(<String, Object?>{
            'ticket_updates': true,
            'almost_turn': false,
            'queue_updates': true,
            'staff_new_tickets': true,
            'staff_cancellations': true,
            'push_enabled': true,
          }),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.text('Almost my turn'));
      await tester.pumpAndSettle();

      expect(
        http.sent('PUT', '/me/notification-preferences').single.data,
        <String, Object?>{'almost_turn': false},
      );
      final SwitchListTile tile = tester.widget(
        find.widgetWithText(SwitchListTile, 'Almost my turn'),
      );
      expect(tile.value, isFalse);
    });

    testWidgets('a refused change is undone', (tester) async {
      final FakeHttpAdapter http = api()
        ..reply(
          'PUT',
          '/me/notification-preferences',
          FakeResponse.error(500, 'server_error'),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.text('My tickets'));
      await tester.pumpAndSettle();

      final SwitchListTile tile = tester.widget(
        find.widgetWithText(SwitchListTile, 'My tickets'),
      );
      expect(tile.value, isTrue);
    });

    testWidgets('permission: asked on the button only, granted', (
      tester,
    ) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        notifier: notifier,
      );
      expect(notifier.requests, 0);
      expect(
        find.text('DORY cannot show notifications on this phone.'),
        findsOneWidget,
      );

      await tester.tap(find.text('Allow notifications'));
      await tester.pumpAndSettle();

      expect(notifier.requests, 1);
      expect(
        find.text('Notifications are allowed on this phone.'),
        findsOneWidget,
      );
    });

    testWidgets('permission: refused, then the phone settings', (tester) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier(
        answer: NotificationPermission.denied,
      );
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        notifier: notifier,
      );

      await tester.tap(find.text('Allow notifications'));
      await tester.pumpAndSettle();
      expect(
        find.text('DORY cannot show notifications on this phone.'),
        findsOneWidget,
      );

      await tester.tap(find.text('Open phone settings'));
      await tester.pumpAndSettle();
      expect(notifier.settingsOpened, 1);
    });

    testWidgets('says plainly when push is not set up', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
      );

      expect(find.textContaining('not set up yet'), findsOneWidget);
    });

    testWidgets('with push ready it says so', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerNotificationSettings,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        push: FakePushMessaging(),
      );

      expect(find.textContaining('even when DORY is closed'), findsOneWidget);
    });
  });
}
