import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/config/ticket_config.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

void main() {
  const String guestTicketPath = '/public/tickets/$customerTicketId';
  const String positionPath = '/tickets/$customerTicketId/position';

  FakeHttpAdapter ticketApi({Map<String, Object?>? ticket}) => customerApi()
    ..reply(
      'GET',
      positionPath,
      FakeResponse.ok(ticket ?? customerTicketJson()),
    )
    ..reply(
      'GET',
      guestTicketPath,
      FakeResponse.ok(ticket ?? customerTicketJson()),
    );

  /// The queue join screen, ready to submit.
  Future<GoRouter> openJoin(
    WidgetTester tester,
    FakeHttpAdapter http, {
    bool signedIn = false,
    InMemoryKeyValueStorage? storage,
    InMemorySecureStore? secure,
  }) => pumpAppAt(
    tester,
    AppRoutes.customerQueue(publicQueueId),
    authRepository: signedIn
        ? FakeAuthRepository.signedIn()
        : FakeAuthRepository(),
    http: http,
    storage: storage,
    secureStore: secure,
  );

  /// Leaves the live ticket screen so its polling timer ends with the test.
  Future<void> closeTicket(WidgetTester tester, GoRouter router) async {
    router.go(AppRoutes.customerHome);
    await tester.pumpAndSettle();
  }

  group('authenticated joining', () {
    testWidgets('joins through the existing API and shows the ticket', (
      tester,
    ) async {
      final FakeHttpAdapter http = ticketApi()
        ..reply(
          'POST',
          '/queues/$publicQueueId/join',
          FakeResponse.ok(customerTicketJson(), status: 201),
        );
      final GoRouter router = await openJoin(tester, http, signedIn: true);

      expect(find.text('Continue as a guest'), findsNothing);
      await tester.tap(find.text('Get a ticket'));
      await tester.pumpAndSettle();

      expect(currentLocation(router), '/customer/ticket/$customerTicketId');
      expect(find.text('BAR-024'), findsOneWidget);
      expect(find.text('Your position: 5'), findsOneWidget);
      expect(find.textContaining('Your ticket is BAR-024'), findsOneWidget);

      final String key =
          http
                  .sent('POST', '/queues/$publicQueueId/join')
                  .single
                  .headers['Idempotency-Key']!
              as String;
      expect(key, hasLength(32));
      await closeTicket(tester, router);
    });

    testWidgets('a repeated tap sends one request', (tester) async {
      final FakeHttpAdapter http = ticketApi()
        ..reply(
          'POST',
          '/queues/$publicQueueId/join',
          FakeResponse.ok(customerTicketJson(), status: 201),
        );
      final GoRouter router = await openJoin(tester, http, signedIn: true);

      await tester.tap(find.text('Get a ticket'));
      await tester.tap(find.text('Get a ticket'), warnIfMissed: false);
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/queues/$publicQueueId/join'), hasLength(1));
      await closeTicket(tester, router);
    });

    testWidgets('a retry after a failure reuses the idempotency key', (
      tester,
    ) async {
      final FakeHttpAdapter http = ticketApi()
        ..reply('POST', '/queues/$publicQueueId/join', FakeResponse.offline);
      final GoRouter router = await openJoin(tester, http, signedIn: true);

      await tester.tap(find.text('Get a ticket'));
      await tester.pumpAndSettle();

      http.reply(
        'POST',
        '/queues/$publicQueueId/join',
        FakeResponse.ok(customerTicketJson(), status: 201),
      );
      await tester.tap(find.text('Get a ticket'));
      await tester.pumpAndSettle();

      final List<Object?> keys = http
          .sent('POST', '/queues/$publicQueueId/join')
          .map((request) => request.headers['Idempotency-Key'])
          .toList();
      expect(keys, hasLength(2));
      expect(keys.first, keys.last);
      await closeTicket(tester, router);
    });

    testWidgets('a duplicate ticket is explained', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'POST',
          '/queues/$publicQueueId/join',
          FakeResponse.error(409, 'duplicate_active_ticket'),
        );
      await openJoin(tester, http, signedIn: true);

      await tester.tap(find.text('Get a ticket'));
      await tester.pumpAndSettle();

      expect(
        find.textContaining('already have an active ticket'),
        findsOneWidget,
      );
    });
  });

  group('states that block joining', () {
    Future<void> expectBlocked(
      WidgetTester tester,
      Map<String, Object?> queue,
      String message,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('GET', '/public/queues/$publicQueueId', FakeResponse.ok(queue));

      await openJoin(tester, http, signedIn: true);

      expect(find.textContaining(message), findsOneWidget);
      expect(find.text('Get a ticket'), findsNothing);
    }

    testWidgets('closed', (tester) async {
      await expectBlocked(
        tester,
        joinableQueueJson(status: 'closed', accepting: false),
        'closed right now',
      );
    });

    testWidgets('paused', (tester) async {
      await expectBlocked(
        tester,
        joinableQueueJson(status: 'paused', accepting: false),
        'is paused',
      );
    });

    testWidgets('full', (tester) async {
      await expectBlocked(
        tester,
        joinableQueueJson(accepting: false, full: true),
        'is full',
      );
    });

    testWidgets('service unavailable', (tester) async {
      await expectBlocked(
        tester,
        joinableQueueJson(
          status: 'inactive',
          reason: 'service_unavailable',
          accepting: false,
        ),
        'service is not available',
      );
    });

    testWidgets('branch inactive', (tester) async {
      await expectBlocked(
        tester,
        joinableQueueJson(
          status: 'inactive',
          reason: 'branch_inactive',
          accepting: false,
        ),
        'branch is not taking customers',
      );
    });

    testWidgets('a refusal from the server is shown and rechecked', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'POST',
          '/queues/$publicQueueId/join',
          FakeResponse.error(409, 'queue_full'),
        );
      await openJoin(tester, http, signedIn: true);
      final int before = http
          .sent('GET', '/public/queues/$publicQueueId')
          .length;

      await tester.tap(find.text('Get a ticket'));
      await tester.pumpAndSettle();

      expect(find.textContaining('is full'), findsOneWidget);
      expect(
        http.sent('GET', '/public/queues/$publicQueueId').length,
        before + 1,
      );
    });

    testWidgets('network problems are explained', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('GET', '/public/queues/$publicQueueId', FakeResponse.offline);

      await openJoin(tester, http);

      expect(find.text('Try again'), findsOneWidget);
    });
  });

  group('guest joining', () {
    FakeHttpAdapter guestApi() => ticketApi()
      ..reply(
        'POST',
        '/public/queues/$publicQueueId/join',
        FakeResponse.ok(
          customerTicketJson(accessToken: guestAccessToken),
          status: 201,
        ),
      );

    testWidgets('signing in from the join screen comes back to it', (
      tester,
    ) async {
      // Found on a phone (the same screen pattern): a pushed login screen
      // stayed after signing in.
      final GoRouter router = await openJoin(tester, guestApi());
      await tester.ensureVisible(find.text('Log in').last);
      await tester.tap(find.text('Log in').last);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextFormField).at(0),
        FakeAuthRepository.validEmail,
      );
      await tester.enterText(
        find.byType(TextFormField).at(1),
        FakeAuthRepository.validPassword,
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Log in'));
      await tester.pumpAndSettle();

      expect(topLocation(router), AppRoutes.customerQueue(publicQueueId));
    });

    testWidgets('asks only for what the queue requires', (tester) async {
      await openJoin(
        tester,
        customerApi()..reply(
          'GET',
          '/public/queues/$publicQueueId',
          FakeResponse.ok(joinableQueueJson(guestName: false)),
        ),
      );

      expect(find.text('Your name'), findsNothing);
      expect(find.text('Phone number'), findsNothing);
      expect(find.text('Get a ticket as a guest'), findsOneWidget);
    });

    testWidgets('a required phone number needs consent', (tester) async {
      await openJoin(
        tester,
        customerApi()..reply(
          'GET',
          '/public/queues/$publicQueueId',
          FakeResponse.ok(joinableQueueJson(guestPhone: true)),
        ),
      );

      final Finder button = find.ancestor(
        of: find.text('Get a ticket as a guest'),
        matching: find.byWidgetPredicate(
          (widget) => widget is ButtonStyleButton,
        ),
      );
      expect(tester.widget<ButtonStyleButton>(button).onPressed, isNull);

      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      expect(tester.widget<ButtonStyleButton>(button).onPressed, isNotNull);
    });

    testWidgets('joins without an account and stores the token securely', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      final InMemorySecureStore secure = InMemorySecureStore();
      final FakeHttpAdapter http = guestApi();
      final GoRouter router = await openJoin(
        tester,
        http,
        storage: storage,
        secure: secure,
      );

      await tester.enterText(find.byType(TextFormField), 'Rami');
      await tester.tap(find.text('Get a ticket as a guest'));
      await tester.pumpAndSettle();

      expect(find.text('BAR-024'), findsOneWidget);
      expect(
        router.routerDelegate.currentConfiguration.uri.toString(),
        '/customer/ticket/$customerTicketId?guest=1',
      );

      expect(secure.values.values, contains(guestAccessToken));
      expect(
        storage.readString(StorageKeys.guestTickets),
        isNot(contains(guestAccessToken)),
      );

      final RequestSent body =
          http.sent('POST', '/public/queues/$publicQueueId/join').single.data
              as RequestSent;
      expect(body, <String, Object?>{'name': 'Rami'});
      await closeTicket(tester, router);
    });

    testWidgets('the token travels only in the X-Ticket-Token header', (
      tester,
    ) async {
      final FakeHttpAdapter http = guestApi();
      final GoRouter router = await openJoin(tester, http);

      await tester.enterText(find.byType(TextFormField), 'Rami');
      await tester.tap(find.text('Get a ticket as a guest'));
      await tester.pumpAndSettle();

      final request = http.sent('GET', guestTicketPath).single;
      expect(request.headers['X-Ticket-Token'], guestAccessToken);
      expect(request.uri.toString(), isNot(contains(guestAccessToken)));
      expect(find.textContaining(guestAccessToken), findsNothing);
      await closeTicket(tester, router);
    });

    testWidgets('a queue without guests asks to sign in', (tester) async {
      await openJoin(
        tester,
        customerApi()..reply(
          'GET',
          '/public/queues/$publicQueueId',
          FakeResponse.ok(joinableQueueJson(guests: false)),
        ),
      );

      expect(find.textContaining('needs an account'), findsOneWidget);
      expect(find.text('Get a ticket as a guest'), findsNothing);
    });

    testWidgets('server field errors appear on the form', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'POST',
          '/public/queues/$publicQueueId/join',
          FakeResponse.error(
            422,
            'validation_failed',
            errors: <String, List<String>>{
              'name': <String>['Name is too short'],
            },
          ),
        );
      await openJoin(tester, http);

      await tester.enterText(find.byType(TextFormField), 'Ra');
      await tester.tap(find.text('Get a ticket as a guest'));
      await tester.pumpAndSettle();

      expect(find.text('Name is too short'), findsOneWidget);
    });
  });

  group('digital ticket', () {
    Future<GoRouter> openTicket(
      WidgetTester tester,
      Map<String, Object?> ticket, {
      FakeHttpAdapter? http,
    }) => pumpAppAt(
      tester,
      AppRoutes.customerTicket(customerTicketId),
      authRepository: FakeAuthRepository.signedIn(),
      http: http ?? ticketApi(ticket: ticket),
    );

    testWidgets('shows everything a waiting customer needs', (tester) async {
      final GoRouter router = await openTicket(tester, customerTicketJson());

      expect(find.text('BAR-024'), findsOneWidget);
      expect(find.text('Your position: 5'), findsOneWidget);
      expect(find.text('4 people ahead of you'), findsOneWidget);
      expect(find.text('About 15 min'), findsOneWidget);
      expect(find.text('Shifa Clinic'), findsOneWidget);
      expect(find.textContaining('Mazzeh'), findsWidgets);
      expect(find.text('General consultation'), findsOneWidget);
      expect(find.text('Leave the queue'), findsOneWidget);
      expect(find.textContaining('not instant'), findsOneWidget);
      await closeTicket(tester, router);
    });

    const Map<String, String> headlines = <String, String>{
      'waiting': 'You are in the queue',
      'called': 'It is your turn. Please go to the counter',
      'serving': 'You are being served',
      'completed': 'Service completed',
      'cancelled': 'This ticket was cancelled',
      'skipped': 'This ticket was skipped',
      'no_show': 'You were not at the counter when called',
      'expired': 'This ticket expired',
    };

    for (final MapEntry<String, String> state in headlines.entries) {
      testWidgets('shows the ${state.key} state', (tester) async {
        final bool waiting = state.key == 'waiting';
        final GoRouter router = await openTicket(
          tester,
          customerTicketJson(
            status: state.key,
            position: waiting ? 5 : null,
            ahead: waiting ? 4 : null,
            minutes: waiting ? 15 : null,
            canCancel: waiting,
          ),
        );

        expect(find.text(state.value), findsOneWidget);
        await closeTicket(tester, router);
      });
    }

    testWidgets('finished tickets offer no ordinary actions', (tester) async {
      await openTicket(
        tester,
        customerTicketJson(
          status: 'cancelled',
          position: null,
          ahead: null,
          minutes: null,
          canCancel: false,
        ),
      );

      expect(find.text('Leave the queue'), findsNothing);
      expect(find.textContaining('not instant'), findsNothing);
    });

    testWidgets('a called customer sees when to arrive', (tester) async {
      final GoRouter router = await openTicket(
        tester,
        customerTicketJson(
          status: 'called',
          position: null,
          ahead: null,
          minutes: null,
          canCancel: false,
          arriveBy: '2026-11-07T09:30:00+00:00',
        ),
      );

      expect(find.textContaining('Please arrive by'), findsOneWidget);
      await closeTicket(tester, router);
    });

    testWidgets('leaving the queue asks first, then updates the ticket', (
      tester,
    ) async {
      final FakeHttpAdapter http = ticketApi()
        ..reply(
          'POST',
          '/tickets/$customerTicketId/cancel',
          FakeResponse.ok(
            customerTicketJson(
              status: 'cancelled',
              position: null,
              ahead: null,
              minutes: null,
              canCancel: false,
            ),
          ),
        );
      await openTicket(tester, customerTicketJson(), http: http);

      await tester.ensureVisible(find.text('Leave the queue'));
      await tester.tap(find.text('Leave the queue'));
      await tester.pumpAndSettle();
      expect(http.sent('POST', '/tickets/$customerTicketId/cancel'), isEmpty);

      await tester.tap(find.text('Leave the queue').last);
      await tester.pumpAndSettle();

      expect(
        http.sent('POST', '/tickets/$customerTicketId/cancel'),
        hasLength(1),
      );
      expect(find.text('This ticket was cancelled'), findsOneWidget);
    });

    testWidgets('a refused cancellation is explained', (tester) async {
      final FakeHttpAdapter http = ticketApi()
        ..reply(
          'POST',
          '/tickets/$customerTicketId/cancel',
          FakeResponse.error(409, 'cancellation_not_allowed'),
        );
      final GoRouter router = await openTicket(
        tester,
        customerTicketJson(),
        http: http,
      );

      await tester.ensureVisible(find.text('Leave the queue'));
      await tester.tap(find.text('Leave the queue'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Leave the queue').last);
      await tester.pumpAndSettle();

      expect(find.textContaining('can no longer be cancelled'), findsOneWidget);
      await closeTicket(tester, router);
    });

    testWidgets('a completed ticket can be rated once', (tester) async {
      final FakeHttpAdapter http =
          ticketApi(
            ticket: customerTicketJson(
              status: 'completed',
              position: null,
              ahead: null,
              minutes: null,
              canCancel: false,
              canReview: true,
            ),
          )..reply(
            'POST',
            '/tickets/$customerTicketId/review',
            FakeResponse.ok(
              customerTicketJson(
                status: 'completed',
                position: null,
                ahead: null,
                minutes: null,
                canCancel: false,
                myRating: 4,
              ),
              status: 201,
            ),
          );
      await openTicket(tester, customerTicketJson(), http: http);

      await tester.ensureVisible(find.byIcon(Icons.star_outline_rounded).first);
      await tester.tap(find.byIcon(Icons.star_outline_rounded).at(3));
      await tester.pumpAndSettle();

      expect(
        http.sent('POST', '/tickets/$customerTicketId/review').single.data,
        <String, Object?>{'rating': 4},
      );
      expect(find.text('Your rating'), findsOneWidget);
      expect(find.byIcon(Icons.star_rounded), findsNWidgets(4));
    });
  });

  group('position polling', () {
    testWidgets('refreshes while open and stops when left', (tester) async {
      final FakeHttpAdapter http = ticketApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );
      expect(http.sent('GET', positionPath), hasLength(1));

      await tester.pump(TicketConfig.pollInterval);
      await tester.pump();
      expect(http.sent('GET', positionPath), hasLength(2));

      await tester.pump(TicketConfig.pollInterval);
      await tester.pump();
      expect(http.sent('GET', positionPath), hasLength(3));

      await closeTicket(tester, router);
      await tester.pump(TicketConfig.pollInterval * 5);
      expect(http.sent('GET', positionPath), hasLength(3));
    });

    testWidgets('a new position appears without any action', (tester) async {
      Map<String, Object?> ticket = customerTicketJson();
      final FakeHttpAdapter http = customerApi()
        ..on('GET', positionPath, (_) => FakeResponse.ok(ticket));
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );
      expect(find.text('Your position: 5'), findsOneWidget);

      ticket = customerTicketJson(position: 2, ahead: 1);
      await tester.pump(TicketConfig.pollInterval);
      await tester.pump();

      expect(find.text('Your position: 2'), findsOneWidget);
      await closeTicket(tester, router);
    });

    testWidgets('stops in the background and refreshes on return', (
      tester,
    ) async {
      final FakeHttpAdapter http = ticketApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      await tester.pump(TicketConfig.pollInterval * 5);
      expect(http.sent('GET', positionPath), hasLength(1));

      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pump(const Duration(seconds: 1));
      expect(http.sent('GET', positionPath), hasLength(2));

      await closeTicket(tester, router);
    });

    testWidgets('stops while another screen covers the ticket', (tester) async {
      final FakeHttpAdapter http = ticketApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      unawaitedPush(router, AppRoutes.customerBusiness(publicSlug));
      await tester.pumpAndSettle();
      await tester.pump(TicketConfig.pollInterval * 5);

      expect(http.sent('GET', positionPath), hasLength(1));

      router.pop();
      await tester.pumpAndSettle();
      expect(http.sent('GET', positionPath), hasLength(2));

      await closeTicket(tester, router);
    });

    testWidgets('a finished ticket stops polling for good', (tester) async {
      final FakeHttpAdapter http = ticketApi(
        ticket: customerTicketJson(
          status: 'completed',
          position: null,
          ahead: null,
          minutes: null,
          canCancel: false,
        ),
      );
      await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.pump(TicketConfig.pollInterval * 5);

      expect(http.sent('GET', positionPath), hasLength(1));
    });

    testWidgets('a lost connection keeps the last ticket on screen', (
      tester,
    ) async {
      final FakeHttpAdapter http = ticketApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      http.reply('GET', positionPath, FakeResponse.offline);
      await tester.pump(TicketConfig.pollInterval);
      await tester.pump();

      expect(find.text('BAR-024'), findsOneWidget);
      await closeTicket(tester, router);
    });
  });

  group('guest ticket restoration', () {
    testWidgets('a guest ticket reopens after an app restart', (tester) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      final InMemorySecureStore secure = InMemorySecureStore();
      final FakeHttpAdapter http = ticketApi()
        ..reply(
          'POST',
          '/public/queues/$publicQueueId/join',
          FakeResponse.ok(
            customerTicketJson(accessToken: guestAccessToken),
            status: 201,
          ),
        );
      final GoRouter first = await openJoin(
        tester,
        http,
        storage: storage,
        secure: secure,
      );
      await tester.enterText(find.byType(TextFormField), 'Rami');
      await tester.tap(find.text('Get a ticket as a guest'));
      await tester.pumpAndSettle();
      await closeTicket(tester, first);

      // A brand-new app on the same device storage.
      await tester.pumpWidget(const SizedBox.shrink());
      final ProviderContainer restarted = createTestContainer(
        authRepository: FakeAuthRepository(),
        http: http,
        storage: storage,
        secureStore: secure,
      );
      final GoRouter router = restarted.read(appRouterProvider);
      await pumpDoryApp(tester, restarted);
      await tester.pump();
      router.go(AppRoutes.customerTickets);
      await tester.pumpAndSettle();

      expect(find.text('BAR-024'), findsOneWidget);

      await tester.tap(find.text('BAR-024'));
      await tester.pumpAndSettle();
      expect(
        http.sent('GET', guestTicketPath).last.headers['X-Ticket-Token'],
        guestAccessToken,
      );
      expect(find.text('Your position: 5'), findsOneWidget);
      await closeTicket(tester, router);
    });

    testWidgets('a lost token is explained and the ticket can be removed', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await storage.writeString(
        StorageKeys.guestTickets,
        '[{"id":"$customerTicketId","number":"BAR-024","status":"waiting",'
        '"business":"Shifa Clinic","branch":"Mazzeh","service":null,'
        '"joined_at":"2026-11-07T09:00:00Z"}]',
      );
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId, guest: true),
        authRepository: FakeAuthRepository(),
        http: ticketApi(),
        storage: storage,
        secureStore: InMemorySecureStore(),
      );

      expect(find.textContaining('can no longer be opened'), findsOneWidget);

      await tester.tap(find.text('Remove from this device'));
      await tester.pumpAndSettle();

      expect(currentLocation(router), AppRoutes.customerTickets);
      expect(find.text('BAR-024'), findsNothing);
      expect(storage.readString(StorageKeys.guestTickets), '[]');
    });

    testWidgets('a token the server no longer accepts is handled', (
      tester,
    ) async {
      final InMemorySecureStore secure = InMemorySecureStore(<String, String>{
        'dory.guest_ticket_token.$customerTicketId': 'old-token',
      });
      await pumpAppAt(
        tester,
        AppRoutes.customerTicket(customerTicketId, guest: true),
        authRepository: FakeAuthRepository(),
        http: customerApi()
          ..reply(
            'GET',
            guestTicketPath,
            FakeResponse.error(404, 'ticket_not_found'),
          ),
        secureStore: secure,
      );

      expect(find.textContaining('can no longer be opened'), findsOneWidget);
    });
  });

  group('ticket center', () {
    testWidgets('a signed-in customer sees tickets from the server', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi();
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      expect(find.text('BAR-024'), findsOneWidget);
      expect(http.queries('/me/tickets').single['group'], 'active');
    });

    testWidgets('sections map to server filters', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tapChip(tester, 'Completed');
      expect(http.queries('/me/tickets').last['status'], 'completed');

      await tapChip(tester, 'History');
      expect(http.queries('/me/tickets').last['group'], 'history');
    });

    testWidgets('history pages load on scrolling', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..on('GET', '/me/tickets', (request) {
          final int page = request.queryParameters['page']! as int;
          return paged(
            <Map<String, Object?>>[
              for (int index = 0; index < 20; index++)
                customerTicketJson(
                  id: 'p${page}i$index',
                  number: 'B-$page$index',
                ),
            ],
            page: page,
            lastPage: 2,
          );
        });
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.drag(find.byType(ListView).last, const Offset(0, -3000));
      await tester.pumpAndSettle();

      expect(http.queries('/me/tickets').map((query) => query['page']), <int>[
        1,
        2,
      ]);
    });

    testWidgets('a visitor with no tickets gets a way forward', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      expect(find.text('No active tickets'), findsOneWidget);
      expect(find.text('Explore businesses'), findsOneWidget);
      expect(find.textContaining('Sign in to keep them'), findsOneWidget);
    });

    testWidgets('guest tickets are never presented as account tickets', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await storage.writeString(
        StorageKeys.guestTickets,
        '[{"id":"guest1","number":"GST-001","status":"waiting",'
        '"business":"Shifa Clinic","branch":"Mazzeh","service":null,'
        '"joined_at":"2026-11-07T09:00:00Z"}]',
      );

      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository.signedIn(),
        http: customerApi(),
        storage: storage,
      );

      expect(find.text('On this device'), findsOneWidget);
      expect(find.textContaining('not linked to your account'), findsOneWidget);
      expect(find.text('GST-001'), findsOneWidget);
    });

    testWidgets('finished guest tickets can be removed from the device', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await storage.writeString(
        StorageKeys.guestTickets,
        '[{"id":"guest1","number":"GST-001","status":"completed",'
        '"business":"Shifa Clinic","branch":"Mazzeh","service":null,'
        '"joined_at":"2026-11-07T09:00:00Z"}]',
      );
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
        storage: storage,
      );

      await tapChip(tester, 'History');
      await tester.tap(find.byIcon(Icons.delete_outline_rounded));
      await tester.pumpAndSettle();

      expect(find.text('GST-001'), findsNothing);
      expect(storage.readString(StorageKeys.guestTickets), '[]');
    });

    testWidgets('opening the list reads active guest tickets once only', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await storage.writeString(
        StorageKeys.guestTickets,
        '[{"id":"$customerTicketId","number":"BAR-024","status":"waiting",'
        '"business":"Shifa Clinic","branch":"Mazzeh","service":null,'
        '"joined_at":"2026-11-07T09:00:00Z"}]',
      );
      final FakeHttpAdapter http = ticketApi();
      await pumpAppAt(
        tester,
        AppRoutes.customerTickets,
        authRepository: FakeAuthRepository(),
        http: http,
        storage: storage,
        secureStore: InMemorySecureStore(<String, String>{
          'dory.guest_ticket_token.$customerTicketId': guestAccessToken,
        }),
      );

      await tester.pump(TicketConfig.pollInterval * 4);

      expect(http.sent('GET', guestTicketPath), hasLength(1));
    });
  });
}

void unawaitedPush(GoRouter router, String location) {
  router.push<void>(location).ignore();
}
