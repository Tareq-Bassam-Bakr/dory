import 'package:dory_app/app/router/app_routes.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/api_fixtures.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';
import 'queue_fixtures.dart';

void main() {
  late FakeHttpAdapter http;

  Map<String, Object?> bodyOf(String method, String path) =>
      http.sent(method, path).single.data as Map<String, Object?>;

  setUp(() {
    http = FakeHttpAdapter()
      ..reply(
        'GET',
        '/businesses/manage',
        FakeResponse.ok(<Object?>[businessJson()]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/queues',
        FakeResponse.ok(<Object?>[
          queueJson(session: queueSessionJson()),
          queueJson(
            id: '01jqueuetwoxxxxxxxxxxxxxxx',
            name: 'Vaccinations',
            prefix: 'VAC',
          ),
        ]),
      )
      ..reply('GET', '/queues/$queueId', FakeResponse.ok(queueJson()))
      ..reply(
        'GET',
        '/queue-sessions/$sessionId/dashboard',
        FakeResponse.ok(dashboardJson()),
      );
  });

  // ----- Overview and configuration ------------------------------------

  testWidgets('the overview lists queues with whether they are open', (
    tester,
  ) async {
    final GoRouter router = await pumpAppAt(
      tester,
      AppRoutes.businessQueues,
      http: http,
    );

    expect(find.text('Consultations'), findsOneWidget);
    expect(find.text('Mazzeh branch · MED-001'), findsOneWidget);
    expect(find.text('Open'), findsOneWidget);
    expect(find.text('Vaccinations'), findsOneWidget);
    expect(find.text('Closed'), findsOneWidget);

    await tester.tap(find.text('New queue'));
    await tester.pumpAndSettle();
    expect(currentLocation(router), AppRoutes.queueCreate(businessId));
  });

  testWidgets('a queue is created for a service of the branch', (tester) async {
    http
      ..reply(
        'GET',
        '/businesses/$businessId/queues',
        FakeResponse.ok(<Object?>[]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[branchJson()]),
      )
      ..reply(
        'GET',
        '/branches/$branchId/services',
        FakeResponse.ok(<Object?>[serviceJson()]),
      )
      ..reply(
        'POST',
        '/branches/$branchId/queues',
        FakeResponse.ok(queueJson(prefix: 'GEN'), status: 201),
      );
    final GoRouter router = await pumpAppAt(
      tester,
      AppRoutes.queueCreate(businessId),
      http: http,
    );

    expect(find.text('Mazzeh'), findsOneWidget);
    expect(find.text('General consultation'), findsWidgets);
    expect(find.textContaining('GEN-001'), findsOneWidget);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Maximum waiting customers'),
      '25',
    );
    await tester.ensureVisible(find.text('Create'));
    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();

    expect(bodyOf('POST', '/branches/$branchId/queues'), <String, Object?>{
      'service': serviceId,
      'name': 'General consultation',
      'ticket_prefix': 'GEN',
      'status': 'active',
      'capacity': 25,
      'allow_guest_join': true,
      'require_guest_name': true,
      'require_guest_phone': false,
      'estimated_service_duration': null,
      'grace_period_seconds': 120,
    });
    expect(currentLocation(router), AppRoutes.queue(queueId));
  });

  testWidgets('an invalid prefix is caught before sending', (tester) async {
    http
      ..reply(
        'GET',
        '/businesses/$businessId/queues',
        FakeResponse.ok(<Object?>[]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[branchJson()]),
      )
      ..reply(
        'GET',
        '/branches/$branchId/services',
        FakeResponse.ok(<Object?>[serviceJson()]),
      );
    await pumpAppAt(tester, AppRoutes.queueCreate(businessId), http: http);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Ticket prefix'),
      '1AB',
    );
    await tester.ensureVisible(find.text('Create'));
    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Use 1 to 6 capital letters or digits, starting with a letter.',
      ),
      findsOneWidget,
    );
    expect(http.sent('POST', '/branches/$branchId/queues'), isEmpty);
  });

  testWidgets('the server explains a taken prefix', (tester) async {
    http.reply(
      'PUT',
      '/queues/$queueId',
      FakeResponse.error(
        422,
        'validation_failed',
        errors: <String, List<String>>{
          'ticket_prefix': <String>[
            'Another queue in this branch already uses this prefix',
          ],
        },
      ),
    );
    await pumpAppAt(tester, AppRoutes.queueEdit(queueId), http: http);

    await tester.ensureVisible(find.text('Save'));
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(
      find.text('Another queue in this branch already uses this prefix'),
      findsWidgets,
    );
  });

  // ----- Sessions --------------------------------------------------------

  testWidgets('a closed queue is opened from its details', (tester) async {
    http.reply(
      'POST',
      '/queues/$queueId/open',
      FakeResponse.ok(queueSessionJson(), status: 201),
    );
    final GoRouter router = await pumpAppAt(
      tester,
      AppRoutes.queue(queueId),
      http: http,
    );

    expect(
      find.text('The queue is closed. Open it to start accepting customers.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Open queue'));
    await tester.pumpAndSettle();
    expect(currentLocation(router), AppRoutes.queueOpen(queueId));
    expect(find.textContaining('MED-001 each day'), findsOneWidget);

    await tester.tap(find.text('Open queue'));
    await tester.pumpAndSettle();

    expect(http.sent('POST', '/queues/$queueId/open'), hasLength(1));
    expect(currentLocation(router), AppRoutes.queueSession(sessionId));
    expect(find.text('Live queue'), findsNothing);
    expect(find.text('Consultations'), findsWidgets);
  });

  testWidgets('an open queue is paused and closed after confirming', (
    tester,
  ) async {
    http
      ..reply(
        'GET',
        '/queues/$queueId',
        FakeResponse.ok(queueJson(session: queueSessionJson())),
      )
      ..reply(
        'POST',
        '/queues/$queueId/pause',
        FakeResponse.ok(queueSessionJson(status: 'paused')),
      )
      ..reply(
        'POST',
        '/queues/$queueId/close',
        FakeResponse.ok(queueSessionJson(status: 'closed')),
      );
    await pumpAppAt(tester, AppRoutes.queue(queueId), http: http);

    await tester.tap(find.text('Pause'));
    await tester.pumpAndSettle();
    expect(http.sent('POST', '/queues/$queueId/pause'), hasLength(1));
    expect(find.text('The queue is paused.'), findsOneWidget);

    await tester.ensureVisible(find.text('Close queue'));
    await tester.tap(find.text('Close queue'));
    await tester.pumpAndSettle();
    expect(find.text('Close the queue?'), findsOneWidget);
    await tester.tap(find.text('Close queue').last);
    await tester.pumpAndSettle();

    expect(http.sent('POST', '/queues/$queueId/close'), hasLength(1));
  });

  testWidgets('session history shows each day and how it ended', (
    tester,
  ) async {
    http.reply(
      'GET',
      '/queues/$queueId/sessions',
      FakeResponse.ok(<Object?>[
        queueSessionJson(number: 2, tickets: 0),
        queueSessionJson(
          id: '01jsessionoldxxxxxxxxxxxxx',
          status: 'closed',
          tickets: 2,
          closeReason: 'closed_by_staff',
        ),
      ]),
    );
    await pumpAppAt(tester, AppRoutes.queueSessions(queueId), http: http);

    expect(find.textContaining('session 2'), findsOneWidget);
    expect(find.textContaining('2 tickets'), findsOneWidget);
    expect(find.textContaining('Closed by staff'), findsOneWidget);
  });

  // ----- Serving ---------------------------------------------------------

  testWidgets('the dashboard shows the line and calls the next customer', (
    tester,
  ) async {
    http.reply(
      'POST',
      '/queue-sessions/$sessionId/call-next',
      FakeResponse.ok(
        staffTicketJson(
          id: '01jticketaxxxxxxxxxxxxxxxx',
          number: 'MED-004',
          status: 'called',
          position: null,
          servedBy: 'Lina',
          servedByMe: true,
        ),
      ),
    );
    await pumpAppAt(tester, AppRoutes.queueSession(sessionId), http: http);

    expect(
      find.text('Updates automatically every 15 seconds.'),
      findsOneWidget,
    );
    expect(find.text('~40 min'), findsOneWidget);
    expect(find.text('MED-004'), findsOneWidget);
    expect(find.text('Omar'), findsOneWidget);
    expect(find.text('+963••••••456'), findsNothing);

    await tester.tap(find.text('Call next customer'));
    await tester.pumpAndSettle();

    expect(
      http.sent('POST', '/queue-sessions/$sessionId/call-next'),
      hasLength(1),
    );
    expect(find.text('Called MED-004.'), findsOneWidget);
  });

  testWidgets('the operator serves their called customer', (tester) async {
    final Map<String, Object?> mine = staffTicketJson(
      status: 'called',
      position: null,
      servedBy: 'Lina',
      servedByMe: true,
      noShowAllowedAt: '2026-11-06T06:17:00+00:00',
    );
    http
      ..reply(
        'GET',
        '/queue-sessions/$sessionId/dashboard',
        FakeResponse.ok(
          dashboardJson(
            called: 1,
            inService: <Map<String, Object?>>[mine],
            mine: mine,
          ),
        ),
      )
      ..reply(
        'POST',
        '/tickets/$ticketId/start',
        FakeResponse.ok(staffTicketJson(status: 'serving', position: null)),
      );
    await pumpAppAt(tester, AppRoutes.queueSession(sessionId), http: http);

    expect(find.text('Your customer'), findsOneWidget);
    expect(find.text('Call next customer'), findsNothing);
    expect(find.text('Back to the line'), findsOneWidget);
    expect(find.text('No-show'), findsOneWidget);

    await tester.tap(find.text('Start service'));
    await tester.pumpAndSettle();

    expect(http.sent('POST', '/tickets/$ticketId/start'), hasLength(1));
    expect(find.text('Done.'), findsOneWidget);
  });

  testWidgets('a busy staff member is told why calling failed', (tester) async {
    http.reply(
      'POST',
      '/queue-sessions/$sessionId/call-next',
      FakeResponse.error(409, 'staff_busy'),
    );
    await pumpAppAt(tester, AppRoutes.queueSession(sessionId), http: http);

    await tester.tap(find.text('Call next customer'));
    await tester.pumpAndSettle();

    expect(
      find.text('Finish your current customer before calling the next one.'),
      findsOneWidget,
    );
  });

  testWidgets('viewers without operate permission cannot call', (tester) async {
    http.reply(
      'GET',
      '/queue-sessions/$sessionId/dashboard',
      FakeResponse.ok(dashboardJson(manage: false, operate: false)),
    );
    await pumpAppAt(tester, AppRoutes.queueSession(sessionId), http: http);

    expect(find.text('Call next customer'), findsNothing);
    expect(find.text('MED-004'), findsOneWidget);
  });

  testWidgets('the waiting list shows customers in order', (tester) async {
    http.on('GET', '/queue-sessions/$sessionId/tickets', (request) {
      expect(request.queryParameters['status'], 'waiting');
      return FakeResponse.ok(<Object?>[
        staffTicketJson(number: 'MED-004'),
        staffTicketJson(
          id: '01jticketbxxxxxxxxxxxxxxxx',
          number: 'MED-005',
          position: 2,
        ),
      ]);
    });
    await pumpAppAt(
      tester,
      AppRoutes.queueSessionWaiting(sessionId),
      http: http,
    );

    expect(find.text('Waiting customers'), findsOneWidget);
    expect(find.textContaining('#1 in line'), findsOneWidget);
    expect(find.textContaining('#2 in line'), findsOneWidget);
  });

  testWidgets('ticket details show contact, history and confirm a skip', (
    tester,
  ) async {
    http
      ..reply(
        'GET',
        '/tickets/$ticketId',
        FakeResponse.ok(
          staffTicketJson(
            guestName: 'Omar',
            phone: '+963944123456',
            events: <Map<String, Object?>>[
              <String, Object?>{
                'type': 'joined',
                'from': null,
                'to': 'waiting',
                'actor': null,
                'reason': null,
                'at': '2026-11-06T06:10:00+00:00',
              },
            ],
          ),
        ),
      )
      ..reply(
        'POST',
        '/tickets/$ticketId/skip',
        FakeResponse.ok(staffTicketJson(status: 'skipped', position: null)),
      );
    await pumpAppAt(
      tester,
      AppRoutes.queueTicket(sessionId, ticketId),
      http: http,
    );

    expect(find.text('Ticket MED-001'), findsOneWidget);
    expect(find.text('Omar'), findsOneWidget);
    expect(find.text('+963944123456'), findsOneWidget);
    expect(find.text('Joined'), findsWidgets);

    await tester.ensureVisible(find.text('Skip'));
    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    expect(find.text('Skip this customer?'), findsOneWidget);
    await tester.tap(find.text('Skip').last);
    await tester.pumpAndSettle();

    expect(http.sent('POST', '/tickets/$ticketId/skip'), hasLength(1));
  });

  // ----- Staff and QR ----------------------------------------------------

  testWidgets('managers assign eligible team members', (tester) async {
    http
      ..reply(
        'GET',
        '/queues/$queueId/staff',
        FakeResponse.ok(<Object?>[
          <String, Object?>{
            'id': '01jassignmentxxxxxxxxxxxxx',
            'member': <String, Object?>{
              'id': 'm2',
              'name': 'Omar',
              'email': 'omar@dory.test',
              'role': 'employee',
            },
            'assigned_at': '2026-11-06T06:00:00+00:00',
            'assigned_by': 'Lina',
            'unassigned_at': null,
            'active': true,
          },
        ]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/members',
        FakeResponse.ok(<Object?>[
          _member('m1', 'Lina', 'owner'),
          _member('m2', 'Omar', 'employee'),
          _member('m3', 'Sara', 'employee'),
        ]),
      )
      ..reply(
        'POST',
        '/queues/$queueId/staff',
        FakeResponse.ok(<String, Object?>{}, status: 201),
      );
    await pumpAppAt(tester, AppRoutes.queueStaff(queueId), http: http);

    expect(find.text('Omar'), findsOneWidget);
    expect(find.text('Lina'), findsNothing);
    expect(find.text('Sara'), findsOneWidget);

    await tester.tap(find.text('Assign'));
    await tester.pumpAndSettle();

    expect(bodyOf('POST', '/queues/$queueId/staff'), <String, Object?>{
      'member': 'm3',
    });
  });

  testWidgets('the QR page shows the link and replaces it after confirming', (
    tester,
  ) async {
    const String url =
        'http://dory.test/join/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AbCd';
    String? copied;
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      SystemChannels.platform,
      (call) async {
        if (call.method == 'Clipboard.setData') {
          copied = (call.arguments as Map<Object?, Object?>)['text'] as String?;
        }
        return null;
      },
    );
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        null,
      ),
    );
    http
      ..reply(
        'GET',
        '/queues/$queueId/public-link',
        FakeResponse.ok(<String, Object?>{
          'url': url,
          'qr_payload': url,
          'rotated_at': '2026-11-06T06:00:00+00:00',
          'allow_guest_join': true,
        }),
      )
      ..reply(
        'POST',
        '/queues/$queueId/rotate-public-token',
        FakeResponse.ok(<String, Object?>{
          'url': '${url}x',
          'qr_payload': '${url}x',
          'rotated_at': '2026-11-06T07:00:00+00:00',
          'allow_guest_join': true,
        }),
      );
    final SemanticsHandle semantics = tester.ensureSemantics();
    await pumpAppAt(tester, AppRoutes.queueQr(queueId), http: http);

    expect(find.text(url), findsOneWidget);
    expect(
      find.bySemanticsLabel(RegExp('QR code to join Consultations')),
      findsOneWidget,
    );
    semantics.dispose();

    await tester.ensureVisible(find.text('Copy'));
    await tester.tap(find.text('Copy'));
    await tester.pumpAndSettle();
    expect(copied, url);

    await tester.ensureVisible(find.text('Replace link and QR code'));
    await tester.tap(find.text('Replace link and QR code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Replace link and QR code').last);
    await tester.pumpAndSettle();

    expect(
      http.sent('POST', '/queues/$queueId/rotate-public-token'),
      hasLength(1),
    );
    expect(
      find.text('New link created. The old one no longer works.'),
      findsOneWidget,
    );
  });
}

Map<String, Object?> _member(String id, String name, String role) =>
    <String, Object?>{
      'id': id,
      'user': <String, Object?>{
        'id': '01juser$id',
        'name': name,
        'email': '${name.toLowerCase()}@dory.test',
      },
      'role': role,
      'status': 'active',
      'branch_access': 'all',
      'branches': <Object?>[],
      'joined_at': '2026-11-05T10:00:00+00:00',
      'is_you': role == 'owner',
      'permissions': <String, Object?>{'update': false, 'remove': false},
    };
