import 'package:dory_app/app/app.dart';
import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/notifications/push_messaging.dart';
import 'package:dory_app/core/realtime/realtime_providers.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_coordinator.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fake_notifications.dart';
import '../../helpers/fake_realtime.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

void main() {
  const String queueId = '01jqueuexxxxxxxxxxxxxxxxxx';
  const String userId = '01jtestuserxxxxxxxxxxxxxxx';
  const String notificationId = '9d4f6a1e-1c2b-4c3d-8e9f-0a1b2c3d4e5f';

  Map<String, Object?> notificationJson({
    String title = "It's your turn",
    String body = 'Shifa Clinic, ticket BAR-024 is called.',
  }) => <String, Object?>{
    'id': notificationId,
    'kind': 'ticket_called',
    'title': title,
    'body': body,
    'ticket': <String, Object?>{'id': customerTicketId, 'number': 'BAR-024'},
    'business': <String, Object?>{'id': publicBusinessId, 'name': 'Shifa'},
    'queue': <String, Object?>{'id': queueId, 'name': 'General'},
    'for_staff': false,
    'read': false,
    'read_at': null,
    'created_at': '2026-11-08T10:00:00+00:00',
  };

  FakeHttpAdapter api() => customerApi()
    ..reply(
      'GET',
      '/me/notifications',
      FakeResponse.ok(
        <Object?>[notificationJson()],
        meta: <String, Object?>{
          'pagination': <String, Object?>{
            'current_page': 1,
            'per_page': 20,
            'total': 1,
            'last_page': 1,
          },
          'unread_count': 1,
        },
      ),
    )
    ..reply(
      'POST',
      '/devices/register',
      FakeResponse.ok(<String, Object?>{
        'id': '01jdevicexxxxxxxxxxxxxxxxx',
      }, status: 201),
    );

  String fullLocation(GoRouter router) =>
      router.routerDelegate.currentConfiguration.uri.toString();

  /// The app as it starts on a phone: no explicit navigation by the test.
  Future<GoRouter> startApp(
    WidgetTester tester, {
    required FakeHttpAdapter http,
    LocalNotifier? notifier,
    PushMessaging? push,
    bool owns = false,
  }) async {
    setTestWindowSize(tester, const Size(390, 900));
    final ProviderContainer container = createTestContainer(
      authRepository: FakeAuthRepository.signedIn(owns: owns),
      http: http,
      notifier: notifier,
      push: push,
    );
    final GoRouter router = container.read(appRouterProvider);
    await pumpDoryApp(tester, container);
    for (int i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    await tester.pumpAndSettle();
    return router;
  }

  group('opening notifications', () {
    testWidgets('a tapped notification opens the ticket', (tester) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        notifier: notifier,
      );

      notifier.tap(<String, String>{
        'kind': 'ticket_called',
        'ticket': customerTicketId,
        'guest': '0',
      });
      await tester.pumpAndSettle();

      expect(fullLocation(router), AppRoutes.customerTicket(customerTicketId));
    });

    testWidgets('a guest notification opens the guest ticket', (tester) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: api(),
        notifier: notifier,
      );

      notifier.tap(<String, String>{
        'kind': 'ticket_called',
        'ticket': customerTicketId,
        'guest': '1',
      });
      await tester.pumpAndSettle();

      expect(
        fullLocation(router),
        AppRoutes.customerTicket(customerTicketId, guest: true),
      );
      // No token on this device: the ticket explains how to recover.
      expect(find.textContaining('can no longer be opened'), findsOneWidget);
    });

    testWidgets('signed out, an account ticket asks to sign in first', (
      tester,
    ) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: api(),
        notifier: notifier,
      );

      notifier.tap(<String, String>{
        'kind': 'ticket_called',
        'ticket': customerTicketId,
      });
      await tester.pumpAndSettle();

      expect(currentLocation(router), AppRoutes.login);
      expect(
        router.routerDelegate.currentConfiguration.uri.queryParameters['from'],
        AppRoutes.customerTicket(customerTicketId),
      );
    });

    testWidgets('a staff notification opens the queue', (tester) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(owns: true),
        http: api(),
        notifier: notifier,
      );

      notifier.tap(<String, String>{
        'kind': 'staff_ticket_joined',
        'ticket': customerTicketId,
        'queue': queueId,
      });
      await tester.pumpAndSettle();

      expect(topLocation(router), AppRoutes.queue(queueId));
      // The queue screen refreshes on a timer: unmount it.
      // Unmount, and let the retries of unmocked business requests end.
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(minutes: 1));
    });

    testWidgets('a notification that started the app is opened', (
      tester,
    ) async {
      // A real start: splash, session restore, then the notification.
      final GoRouter router = await startApp(
        tester,
        http: api(),
        notifier: FakeLocalNotifier(
          launch: <String, String>{
            'kind': 'ticket_called',
            'ticket': customerTicketId,
          },
        ),
      );

      expect(fullLocation(router), AppRoutes.customerTicket(customerTicketId));
    });

    testWidgets('FCM: the opening message and background taps', (tester) async {
      final FakePushMessaging push = FakePushMessaging(
        initial: const IncomingPush(
          data: <String, Object?>{
            'kind': 'ticket_completed',
            'ticket': customerTicketId,
            'guest': '0',
          },
        ),
      );
      final GoRouter router = await startApp(
        tester,
        http: api(),
        push: push,
        owns: true,
      );
      expect(fullLocation(router), AppRoutes.customerTicket(customerTicketId));

      push.opened.add(
        const IncomingPush(
          data: <String, Object?>{
            'kind': 'staff_ticket_cancelled',
            'queue': queueId,
          },
        ),
      );
      await tester.pumpAndSettle();
      expect(topLocation(router), AppRoutes.queue(queueId));
      // Unmount, and let the retries of unmocked business requests end.
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(minutes: 1));
    });
  });

  group('alerts while DORY is open', () {
    testWidgets('FCM messages are shown once, not over their own ticket', (
      tester,
    ) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier(
        current: NotificationPermission.granted,
      );
      final FakePushMessaging push = FakePushMessaging();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        notifier: notifier,
        push: push,
      );
      const IncomingPush called = IncomingPush(
        title: "It's your turn",
        body: 'Ticket BAR-024 is called.',
        data: <String, Object?>{
          'kind': 'ticket_called',
          'ticket': customerTicketId,
          'notification': notificationId,
        },
      );

      push.foreground.add(called);
      await tester.pumpAndSettle();
      push.foreground.add(called);
      await tester.pumpAndSettle();

      expect(notifier.shown, hasLength(1));
      expect(notifier.shown.single.title, "It's your turn");
      expect(notifier.shown.single.payload['ticket'], customerTicketId);
    });

    testWidgets('nothing is shown on top of the ticket it is about', (
      tester,
    ) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier(
        current: NotificationPermission.granted,
      );
      final FakePushMessaging push = FakePushMessaging();
      await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: api()
          ..reply(
            'GET',
            '/tickets/$customerTicketId/position',
            FakeResponse.ok(customerTicketJson()),
          ),
        notifier: notifier,
        push: push,
      );

      push.foreground.add(
        const IncomingPush(
          title: "It's your turn",
          data: <String, Object?>{
            'kind': 'ticket_called',
            'ticket': customerTicketId,
          },
        ),
      );
      await tester.pumpAndSettle();

      expect(notifier.shown, isEmpty);
    });

    testWidgets('without permission nothing is shown', (tester) async {
      final FakeLocalNotifier notifier = FakeLocalNotifier();
      final FakePushMessaging push = FakePushMessaging();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: api(),
        notifier: notifier,
        push: push,
      );

      push.foreground.add(
        const IncomingPush(
          title: 'Hello',
          data: <String, Object?>{'kind': 'ticket_called'},
        ),
      );
      await tester.pumpAndSettle();

      expect(notifier.shown, isEmpty);
      expect(notifier.requests, 0, reason: 'Never asked without the user.');
    });

    testWidgets('Reverb: a new notification is shown in the server language', (
      tester,
    ) async {
      final FakeRealtimeServer server = FakeRealtimeServer();
      final FakeLocalNotifier notifier = FakeLocalNotifier(
        current: NotificationPermission.granted,
      );
      final FakeHttpAdapter http = api()
        ..reply(
          'POST',
          '/broadcasting/auth',
          const FakeResponse(200, <String, Object?>{'auth': 'key:sig'}),
        )
        ..reply(
          'GET',
          '/me/notifications',
          FakeResponse.ok(
            <Object?>[
              notificationJson(
                title: 'حان دورك',
                body: 'نُوديت التذكرة BAR-024.',
              ),
            ],
            meta: <String, Object?>{'unread_count': 1},
          ),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
        notifier: notifier,
        overrides: [
          realtimeUriProvider.overrideWithValue(
            Uri.parse('ws://127.0.0.1:8080/app/key'),
          ),
          realtimeSocketConnectorProvider.overrideWithValue(server.connect),
        ],
      );
      server.current.establish();
      await tester.pumpAndSettle();
      expect(
        server.current.subscribedChannels,
        contains('private-users.$userId'),
      );
      // The private channel is signed by the API for this connection.
      expect(
        http.sent('POST', '/broadcasting/auth').single.data,
        <String, Object?>{
          'socket_id': '1.1',
          'channel_name': 'private-users.$userId',
        },
      );

      server.current
        ..confirm('private-users.$userId')
        ..push('notification.created', <String, Object?>{
          'id': notificationId,
          'kind': 'ticket_called',
          'ticket': customerTicketId,
        }, channel: 'private-users.$userId');
      await tester.pumpAndSettle();

      expect(notifier.shown.single.title, 'حان دورك');
      expect(notifier.shown.single.body, 'نُوديت التذكرة BAR-024.');

      final ProviderContainer container = ProviderScope.containerOf(
        tester.element(find.byType(DoryApp)),
      );
      container.read(realtimeClientProvider)?.dispose();
    });
  });

  group('device registration', () {
    testWidgets('a signed-in phone with permission registers its token', (
      tester,
    ) async {
      final FakeHttpAdapter http = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
        notifier: FakeLocalNotifier(current: NotificationPermission.granted),
        push: FakePushMessaging(deviceToken: 'fcm-token-ABC'),
      );
      await tester.pumpAndSettle();

      final Object? body = http.sent('POST', '/devices/register').last.data;
      expect(body, <String, Object?>{
        'token': 'fcm-token-ABC',
        'platform': 'android',
        'locale': 'en',
      });
    });

    testWidgets('without permission or without push nothing is registered', (
      tester,
    ) async {
      final FakeHttpAdapter refused = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: refused,
        push: FakePushMessaging(),
      );
      await tester.pumpAndSettle();
      expect(refused.sent('POST', '/devices/register'), isEmpty);

      final FakeHttpAdapter noPush = api();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: noPush,
        notifier: FakeLocalNotifier(current: NotificationPermission.granted),
      );
      await tester.pumpAndSettle();
      expect(noPush.sent('POST', '/devices/register'), isEmpty);
    });

    testWidgets('a refreshed token replaces the previous registration', (
      tester,
    ) async {
      final FakeHttpAdapter http = api();
      final FakePushMessaging push = FakePushMessaging(deviceToken: 'first');
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
        notifier: FakeLocalNotifier(current: NotificationPermission.granted),
        push: push,
      );
      await tester.pumpAndSettle();

      push.deviceToken = 'second';
      push.refreshes.add('second');
      await tester.pumpAndSettle();

      final Object? body = http.sent('POST', '/devices/register').last.data;
      expect(body, containsPair('token', 'second'));
      expect(body, containsPair('replaces', '01jdevicexxxxxxxxxxxxxxxxx'));
    });

    testWidgets('signing out removes this phone first', (tester) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      final FakeHttpAdapter http = api()
        ..reply(
          'DELETE',
          '/devices/01jdevicexxxxxxxxxxxxxxxxx/unregister',
          FakeResponse.ok(null),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
        storage: storage,
        notifier: FakeLocalNotifier(current: NotificationPermission.granted),
        push: FakePushMessaging(),
      );
      await tester.pumpAndSettle();
      expect(storage.readString(DeviceRegistration.userKey(userId)), isNotNull);

      final ProviderContainer container = ProviderScope.containerOf(
        tester.element(find.byType(DoryApp)),
      );
      final Future<void> signingOut = container
          .read(authControllerProvider.notifier)
          .logout();
      for (int i = 0; i < 10; i++) {
        await tester.pump(const Duration(milliseconds: 200));
      }
      await signingOut;

      expect(
        http.sent('DELETE', '/devices/01jdevicexxxxxxxxxxxxxxxxx/unregister'),
        hasLength(1),
      );
      expect(storage.readString(DeviceRegistration.userKey(userId)), isNull);
    });

    testWidgets('a guest ticket registers with its token in a header only', (
      tester,
    ) async {
      final FakeHttpAdapter http = api()
        ..reply(
          'GET',
          '/public/tickets/$customerTicketId',
          FakeResponse.ok(customerTicketJson()),
        )
        ..reply(
          'POST',
          '/public/tickets/$customerTicketId/devices/register',
          FakeResponse.ok(<String, Object?>{
            'id': '01jguestdevicexxxxxxxxxxxx',
          }, status: 201),
        );
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId, guest: true),
        authRepository: FakeAuthRepository(),
        http: http,
        storage: storage,
        secureStore: InMemorySecureStore(<String, String>{
          'dory.guest_ticket_token.$customerTicketId': 'guest-secret-token',
        }),
        notifier: FakeLocalNotifier(current: NotificationPermission.granted),
        push: FakePushMessaging(),
      );
      await tester.pumpAndSettle();

      final request = http
          .sent('POST', '/public/tickets/$customerTicketId/devices/register')
          .last;
      expect(request.headers['X-Ticket-Token'], 'guest-secret-token');
      expect(request.uri.toString(), isNot(contains('guest-secret-token')));
      expect(
        storage.readString(DeviceRegistration.guestKey(customerTicketId)),
        '01jguestdevicexxxxxxxxxxxx',
      );
    });
  });
}
