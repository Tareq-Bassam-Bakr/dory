import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../../helpers/api_fixtures.dart';
import '../../../helpers/fake_http_adapter.dart';
import '../../../helpers/test_app.dart';

void main() {
  late FakeHttpAdapter http;

  setUp(() {
    http = FakeHttpAdapter()
      ..reply(
        'GET',
        '/businesses/manage',
        FakeResponse.ok(<Object?>[businessJson()]),
      )
      ..reply('GET', '/businesses/$businessId', FakeResponse.ok(businessJson()))
      ..reply('GET', '/branches/$branchId', FakeResponse.ok(branchJson()));
  });

  group('branch list', () {
    testWidgets('shows branches with their service counts', (tester) async {
      http.reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[
          branchJson(),
          branchJson(
            id: '01jbranch2xxxxxxxxxxxxxxxx',
            name: 'Malki',
            services: 0,
            status: 'inactive',
          ),
        ]),
      );

      await pumpAppAt(tester, AppRoutes.branches(businessId), http: http);

      expect(find.text('Mazzeh'), findsOneWidget);
      expect(find.text('Damascus, SY · 2 services'), findsOneWidget);
      expect(find.text('Malki'), findsOneWidget);
      expect(find.text('Inactive'), findsOneWidget);
      expect(find.text('Add branch'), findsOneWidget);
    });

    testWidgets('shows an empty state with the next step', (tester) async {
      http.reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[]),
      );

      await pumpAppAt(tester, AppRoutes.branches(businessId), http: http);

      expect(find.text('No branches yet'), findsOneWidget);
      expect(
        find.text('Add your first branch so customers know where to find you.'),
        findsOneWidget,
      );
    });

    testWidgets('members who cannot create branches get no add button', (
      tester,
    ) async {
      http
        ..reply(
          'GET',
          '/businesses/$businessId',
          FakeResponse.ok(
            businessJson(role: 'employee', permissions: employeePermissions),
          ),
        )
        ..reply(
          'GET',
          '/businesses/$businessId/branches',
          FakeResponse.ok(<Object?>[]),
        );

      await pumpAppAt(tester, AppRoutes.branches(businessId), http: http);

      expect(find.text('No branches are assigned to you.'), findsOneWidget);
      expect(find.text('Add branch'), findsNothing);
    });

    testWidgets('a failed load shows an error with a working retry', (
      tester,
    ) async {
      http.reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.error(500, 'server_error'),
      );

      await pumpAppAt(tester, AppRoutes.branches(businessId), http: http);
      expect(
        find.text(
          'DORY is having trouble right now. Please try again shortly.',
        ),
        findsOneWidget,
      );

      http.reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[branchJson()]),
      );
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Mazzeh'), findsOneWidget);
    });

    testWidgets('a branch that is not reachable reads as not found', (
      tester,
    ) async {
      http.reply(
        'GET',
        '/branches/$branchId',
        FakeResponse.error(404, 'branch_not_found'),
      );

      await pumpAppAt(
        tester,
        AppRoutes.branch(businessId, branchId),
        http: http,
      );

      expect(find.textContaining('may have been removed'), findsOneWidget);
    });
  });

  group('branch details and form', () {
    testWidgets(
      'details show OpenStreetMap coordinates and management actions',
      (tester) async {
        await pumpAppAt(
          tester,
          AppRoutes.branch(businessId, branchId),
          http: http,
        );

        expect(find.text('33.5138, 36.2765'), findsOneWidget);
        expect(find.text('Edit branch'), findsOneWidget);
        expect(find.text('Delete branch'), findsOneWidget);
      },
    );

    testWidgets('read-only members see no edit or delete', (tester) async {
      http.reply(
        'GET',
        '/branches/$branchId',
        FakeResponse.ok(branchJson(canManage: false)),
      );

      await pumpAppAt(
        tester,
        AppRoutes.branch(businessId, branchId),
        http: http,
      );

      expect(find.text('Edit branch'), findsNothing);
      expect(find.text('Delete branch'), findsNothing);
    });

    testWidgets('the create form validates coordinates before sending', (
      tester,
    ) async {
      http.reply(
        'POST',
        '/businesses/$businessId/branches',
        FakeResponse.ok(branchJson(), status: 201),
      );
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.branchCreate(businessId),
        http: http,
      );

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Branch name'),
        'Abu Rummaneh',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Latitude'),
        '95',
      );
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Create'));
      await tester.tap(find.widgetWithText(FilledButton, 'Create'));
      await tester.pumpAndSettle();

      expect(find.text('Latitude must be between -90 and 90.'), findsOneWidget);
      expect(
        find.text('Enter both latitude and longitude, or neither.'),
        findsOneWidget,
      );
      expect(http.sent('POST', '/businesses/$businessId/branches'), isEmpty);

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Latitude'),
        '33.52',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Longitude'),
        '36.29',
      );
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Create'));
      await tester.tap(find.widgetWithText(FilledButton, 'Create'));
      await tester.pumpAndSettle();

      final Map<String, Object?> body =
          http.sent('POST', '/businesses/$businessId/branches').single.data
              as Map<String, Object?>;
      expect(body['name'], 'Abu Rummaneh');
      expect(body['latitude'], 33.52);
      expect(
        body['timezone'],
        'Asia/Damascus',
        reason: 'Defaults to the business time zone.',
      );
      expect(currentLocation(router), AppRoutes.branch(businessId, branchId));
    });
  });

  group('services', () {
    testWidgets('lists services with duration and status', (tester) async {
      http.reply(
        'GET',
        '/branches/$branchId/services',
        FakeResponse.ok(<Object?>[
          serviceJson(),
          serviceJson(
            id: '01jservice2xxxxxxxxxxxxxxx',
            name: 'Vaccination',
            minutes: 10,
            status: 'inactive',
          ),
        ]),
      );

      await pumpAppAt(
        tester,
        AppRoutes.services(businessId, branchId),
        http: http,
      );

      expect(find.text('General consultation'), findsOneWidget);
      expect(find.text('20 min'), findsOneWidget);
      expect(find.text('Inactive'), findsOneWidget);
      expect(find.text('Add service'), findsOneWidget);
    });

    testWidgets('an empty branch explains what to add', (tester) async {
      http.reply(
        'GET',
        '/branches/$branchId/services',
        FakeResponse.ok(<Object?>[]),
      );

      await pumpAppAt(
        tester,
        AppRoutes.services(businessId, branchId),
        http: http,
      );

      expect(find.text('No services yet'), findsOneWidget);
    });

    testWidgets('read-only members cannot add or open services for editing', (
      tester,
    ) async {
      http
        ..reply(
          'GET',
          '/branches/$branchId',
          FakeResponse.ok(branchJson(canManage: false)),
        )
        ..reply(
          'GET',
          '/branches/$branchId/services',
          FakeResponse.ok(<Object?>[serviceJson()]),
        );

      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.services(businessId, branchId),
        http: http,
      );
      await tester.tap(find.text('General consultation'));
      await tester.pumpAndSettle();

      expect(find.text('Add service'), findsNothing);
      expect(currentLocation(router), AppRoutes.services(businessId, branchId));
    });

    testWidgets('the service form validates, then creates the service', (
      tester,
    ) async {
      http
        ..reply(
          'GET',
          '/branches/$branchId/services',
          FakeResponse.ok(<Object?>[]),
        )
        ..reply(
          'POST',
          '/branches/$branchId/services',
          FakeResponse.ok(serviceJson(), status: 201),
        );
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.serviceCreate(businessId, branchId),
        http: http,
      );

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Estimated duration (minutes)'),
        '0',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Create'));
      await tester.pumpAndSettle();
      expect(
        find.text('Enter a name between 2 and 150 characters.'),
        findsOneWidget,
      );
      expect(
        find.text('Enter a duration between 1 and 1440 minutes.'),
        findsOneWidget,
      );

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Service name'),
        'General consultation',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Estimated duration (minutes)'),
        '20',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Create'));
      await tester.pumpAndSettle();

      expect(
        http.sent('POST', '/branches/$branchId/services').single.data,
        <String, Object?>{
          'name': 'General consultation',
          'estimated_duration_minutes': 20,
          'status': 'active',
        },
      );
      expect(currentLocation(router), AppRoutes.services(businessId, branchId));
    });

    testWidgets(
      'a duplicate name reported by the server is shown on the field',
      (tester) async {
        http.reply(
          'POST',
          '/branches/$branchId/services',
          FakeResponse.error(
            422,
            'validation_failed',
            errors: <String, List<String>>{
              'name': <String>[
                'This branch already has a service with this name',
              ],
            },
          ),
        );
        await pumpAppAt(
          tester,
          AppRoutes.serviceCreate(businessId, branchId),
          http: http,
        );

        await tester.enterText(
          find.widgetWithText(TextFormField, 'Service name'),
          'Haircut',
        );
        await tester.tap(find.widgetWithText(FilledButton, 'Create'));
        await tester.pumpAndSettle();

        expect(
          find.text('This branch already has a service with this name'),
          findsOneWidget,
        );
        expect(find.byType(DoryNotice), findsOneWidget);
      },
    );
  });
}
