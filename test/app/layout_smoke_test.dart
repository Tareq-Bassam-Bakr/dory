import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../features/queues/queue_fixtures.dart';
import '../helpers/api_fixtures.dart';
import '../helpers/fake_auth_repository.dart';
import '../helpers/fake_http_adapter.dart';
import '../helpers/test_app.dart';

/// Renders every screen in both languages on small and large windows.
///
/// Layout overflows are reported as exceptions by the framework, so this
/// catches text that does not fit (Arabic strings are often longer, and RTL
/// mirrors every row). Business screens render real-shaped API data.
void main() {
  final String token = 'T' * 64;
  final List<String> locations = <String>[
    AppRoutes.welcome,
    AppRoutes.login,
    AppRoutes.register,
    AppRoutes.customerHome,
    AppRoutes.customerTickets,
    AppRoutes.customerAccount,
    AppRoutes.businessDashboard,
    AppRoutes.businessQueues,
    AppRoutes.businessAccount,
    AppRoutes.businessCreate,
    AppRoutes.businessOnboarding,
    AppRoutes.business(businessId),
    AppRoutes.businessEdit(businessId),
    AppRoutes.branches(businessId),
    AppRoutes.branchCreate(businessId),
    AppRoutes.branch(businessId, branchId),
    AppRoutes.branchEdit(businessId, branchId),
    AppRoutes.services(businessId, branchId),
    AppRoutes.serviceCreate(businessId, branchId),
    AppRoutes.serviceEdit(businessId, branchId, serviceId),
    AppRoutes.team(businessId),
    AppRoutes.teamInvite(businessId),
    AppRoutes.invitation(token),
    AppRoutes.queueCreate(businessId),
    AppRoutes.queue(queueId),
    AppRoutes.queueEdit(queueId),
    AppRoutes.queueOpen(queueId),
    AppRoutes.queueStaff(queueId),
    AppRoutes.queueSessions(queueId),
    AppRoutes.queueQr(queueId),
    AppRoutes.queueSession(sessionId),
    AppRoutes.queueSessionWaiting(sessionId),
    AppRoutes.queueSessionServing(sessionId),
    AppRoutes.queueTicket(sessionId, ticketId),
  ];
  const Map<String, Size> windows = <String, Size>{
    'small phone': Size(360, 640),
    'tablet': Size(1024, 768),
  };

  // Long Arabic names on purpose: they must still fit small phones.
  final Map<String, Object?> called = staffTicketJson(
    status: 'called',
    position: null,
    guestName: 'عبد الرحمن الحلبي',
    servedBy: 'لينا حداد',
    servedByMe: true,
    noShowAllowedAt: '2026-11-06T06:17:00+00:00',
  );

  FakeHttpAdapter api() => FakeHttpAdapter()
    ..reply('GET', '/business-categories', FakeResponse.ok(categoriesJson()))
    ..reply(
      'GET',
      '/businesses/$businessId/queues',
      FakeResponse.ok(<Object?>[
        queueJson(session: queueSessionJson()),
        queueJson(
          id: '01jqueuetwoxxxxxxxxxxxxxxx',
          name: 'استشارات طب الأسرة والأطفال',
          prefix: 'FAMILY',
        ),
      ]),
    )
    ..reply(
      'GET',
      '/businesses/01jsecondxxxxxxxxxxxxxxxxx/queues',
      FakeResponse.ok(<Object?>[]),
    )
    ..reply(
      'GET',
      '/queues/$queueId',
      FakeResponse.ok(queueJson(session: queueSessionJson(status: 'paused'))),
    )
    ..reply(
      'GET',
      '/queues/$queueId/sessions',
      FakeResponse.ok(<Object?>[
        queueSessionJson(status: 'paused', tickets: 12),
        queueSessionJson(
          id: '01jsessionoldxxxxxxxxxxxxx',
          status: 'closed',
          tickets: 48,
          closeReason: 'day_ended',
        ),
      ]),
    )
    ..reply(
      'GET',
      '/queues/$queueId/public-link',
      FakeResponse.ok(<String, Object?>{
        'url': 'https://dory.example/join/${'Q' * 40}',
        'qr_payload': 'https://dory.example/join/${'Q' * 40}',
        'rotated_at': '2026-11-06T06:00:00+00:00',
        'allow_guest_join': false,
      }),
    )
    ..reply(
      'GET',
      '/queues/$queueId/staff',
      FakeResponse.ok(<Object?>[
        <String, Object?>{
          'id': '01jassignmentxxxxxxxxxxxxx',
          'member': <String, Object?>{
            'id': '01jmembertwoxxxxxxxxxxxxxx',
            'name': 'عمر الخطيب',
            'email': 'omar@dory.test',
            'role': 'employee',
          },
          'assigned_at': '2026-11-06T06:00:00+00:00',
          'assigned_by': 'لينا حداد',
          'unassigned_at': null,
          'active': true,
        },
      ]),
    )
    ..reply(
      'GET',
      '/queue-sessions/$sessionId/dashboard',
      FakeResponse.ok(
        dashboardJson(
          called: 1,
          status: 'paused',
          inService: <Map<String, Object?>>[called],
          mine: called,
        ),
      ),
    )
    ..reply(
      'GET',
      '/queue-sessions/$sessionId/tickets',
      FakeResponse.ok(<Object?>[called, staffTicketJson()]),
    )
    ..reply(
      'GET',
      '/tickets/$ticketId',
      FakeResponse.ok(
        staffTicketJson(
          guestName: 'عبد الرحمن الحلبي',
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
      'GET',
      '/businesses/manage',
      FakeResponse.ok(<Object?>[
        businessJson(),
        businessJson(
          id: '01jsecondxxxxxxxxxxxxxxxxx',
          name: 'مركز الشفاء الطبي للعائلة',
          role: 'employee',
          permissions: employeePermissions,
        ),
      ]),
    )
    ..reply('GET', '/businesses/$businessId', FakeResponse.ok(businessJson()))
    ..reply(
      'GET',
      '/businesses/$businessId/branches',
      FakeResponse.ok(<Object?>[branchJson()]),
    )
    ..reply('GET', '/branches/$branchId', FakeResponse.ok(branchJson()))
    ..reply(
      'GET',
      '/branches/$branchId/services',
      FakeResponse.ok(<Object?>[serviceJson()]),
    )
    ..reply('GET', '/services/$serviceId', FakeResponse.ok(serviceJson()))
    ..reply(
      'GET',
      '/businesses/$businessId/members',
      FakeResponse.ok(<Object?>[
        <String, Object?>{
          'id': '01jmemberxxxxxxxxxxxxxxxxx',
          'user': <String, Object?>{
            'id': ownerId,
            'name': 'لينا حداد',
            'email': 'lina@dory.test',
          },
          'role': 'owner',
          'status': 'active',
          'branch_access': 'all',
          'branches': <Object?>[],
          'joined_at': '2026-11-05T10:00:00+00:00',
          'is_you': true,
          'permissions': <String, Object?>{'update': false, 'remove': false},
        },
      ]),
    )
    ..reply(
      'GET',
      '/businesses/$businessId/invitations',
      FakeResponse.ok(<Object?>[]),
    )
    ..reply(
      'GET',
      '/invitations/$token',
      FakeResponse.ok(<String, Object?>{
        'business': <String, Object?>{
          'id': businessId,
          'name': 'Shifa Clinic',
          'category': categoryJson(),
        },
        'role': 'manager',
        'email': 'sa****@example.com',
        'status': 'pending',
        'expires_at': '2026-11-12T10:00:00+00:00',
        'invited_by': <String, Object?>{'name': 'Lina'},
        'can_respond': true,
      }),
    );

  for (final String languageCode in <String>['en', 'ar']) {
    for (final MapEntry<String, Size> window in windows.entries) {
      for (final String location in locations) {
        testWidgets('$location fits a ${window.key} in "$languageCode"', (
          tester,
        ) async {
          setTestWindowSize(tester, window.value);
          final ProviderContainer container = createTestContainer(
            storage: InMemoryKeyValueStorage(<String, String>{
              StorageKeys.languageCode: languageCode,
            }),
            // The business workspace is only shown to signed-in users.
            authRepository: AppRoutes.requiresAuthentication(location)
                ? FakeAuthRepository.signedIn(owns: true)
                : null,
            http: api(),
          );
          await pumpDoryApp(tester, container);
          await tester.pump();
          final GoRouter router = container.read(appRouterProvider);
          router.go(location);
          await tester.pumpAndSettle();

          expect(tester.takeException(), isNull);
          expect(
            currentLocation(router),
            location,
            reason: 'The screen itself was rendered.',
          );
        });
      }
    }
  }
}
