import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

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
  ];
  const Map<String, Size> windows = <String, Size>{
    'small phone': Size(360, 640),
    'tablet': Size(1024, 768),
  };

  FakeHttpAdapter api() => FakeHttpAdapter()
    ..reply('GET', '/business-categories', FakeResponse.ok(categoriesJson()))
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
