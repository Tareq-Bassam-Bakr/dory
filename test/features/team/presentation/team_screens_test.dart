import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/businesses/presentation/pages/business_overview_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../../helpers/api_fixtures.dart';
import '../../../helpers/fake_auth_repository.dart';
import '../../../helpers/fake_http_adapter.dart';
import '../../../helpers/test_app.dart';

void main() {
  final String token = 'Z' * 64;
  late FakeHttpAdapter http;

  Map<String, Object?> memberJson({
    required String id,
    required String name,
    required String role,
    bool isYou = false,
    bool canChange = false,
  }) => <String, Object?>{
    'id': id,
    'user': <String, Object?>{
      'id': '01juser$id',
      'name': name,
      'email': '$name@dory.test'.toLowerCase(),
    },
    'role': role,
    'status': 'active',
    'branch_access': 'all',
    'branches': <Object?>[],
    'joined_at': '2026-11-05T10:00:00+00:00',
    'is_you': isYou,
    'permissions': <String, Object?>{'update': canChange, 'remove': canChange},
  };

  Map<String, Object?> invitationJson() => <String, Object?>{
    'id': '01jinvitationxxxxxxxxxxxxx',
    'email': 'sara@example.com',
    'role': 'employee',
    'branch_access': 'all',
    'branches': <Object?>[],
    'status': 'pending',
    'expires_at': '2026-11-12T10:00:00+00:00',
    'invited_by': <String, Object?>{'name': 'Lina'},
    'created_at': '2026-11-05T10:00:00+00:00',
  };

  Map<String, Object?> previewJson({
    bool? canRespond,
    String status = 'pending',
  }) => <String, Object?>{
    'business': <String, Object?>{
      'id': businessId,
      'name': 'Shifa Clinic',
      'category': categoryJson(),
    },
    'role': 'manager',
    'email': 'sa****@example.com',
    'status': status,
    'expires_at': '2026-11-12T10:00:00+00:00',
    'invited_by': <String, Object?>{'name': 'Lina'},
    'can_respond': canRespond,
  };

  setUp(() {
    http = FakeHttpAdapter()
      ..reply(
        'GET',
        '/businesses/manage',
        FakeResponse.ok(<Object?>[businessJson()]),
      )
      ..reply('GET', '/businesses/$businessId', FakeResponse.ok(businessJson()))
      ..reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[branchJson()]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/members',
        FakeResponse.ok(<Object?>[
          memberJson(id: 'm1', name: 'Lina', role: 'owner', isYou: true),
          memberJson(id: 'm2', name: 'Omar', role: 'employee', canChange: true),
        ]),
      )
      ..reply(
        'GET',
        '/businesses/$businessId/invitations',
        FakeResponse.ok(<Object?>[invitationJson()]),
      );
  });

  testWidgets('the team shows roles, the viewer and pending invitations', (
    tester,
  ) async {
    await pumpAppAt(tester, AppRoutes.team(businessId), http: http);

    expect(find.text('Lina'), findsOneWidget);
    expect(find.text('You'), findsOneWidget);
    expect(find.text('Owner · All branches'), findsOneWidget);
    expect(find.text('Employee · All branches'), findsOneWidget);
    expect(find.text('sara@example.com'), findsOneWidget);
    expect(find.text('Revoke'), findsOneWidget);
  });

  testWidgets('an invitation result says honestly that no email was sent', (
    tester,
  ) async {
    http.reply(
      'POST',
      '/businesses/$businessId/invitations',
      FakeResponse.ok(<String, Object?>{
        'invitation': invitationJson(),
        'delivery': <String, Object?>{
          'channel': 'email',
          'status': 'not_configured',
        },
        'share_url': 'dory://app/invitations/$token',
      }, status: 201),
    );
    await pumpAppAt(tester, AppRoutes.teamInvite(businessId), http: http);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Their email address'),
      'Sara@Example.com',
    );
    await tester.ensureVisible(find.text('Create invitation'));
    await tester.tap(find.text('Create invitation'));
    await tester.pumpAndSettle();

    final Map<String, Object?> body =
        http.sent('POST', '/businesses/$businessId/invitations').single.data
            as Map<String, Object?>;
    expect(body, <String, Object?>{
      'email': 'sara@example.com',
      'role': 'employee',
    });
    expect(find.textContaining('no email was sent'), findsOneWidget);
    expect(find.text('dory://app/invitations/$token'), findsOneWidget);
    expect(find.textContaining('emailed'), findsNothing);
  });

  testWidgets('selected-branch invitations require a branch', (tester) async {
    await pumpAppAt(tester, AppRoutes.teamInvite(businessId), http: http);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Their email address'),
      'sara@example.com',
    );
    await tester.tap(find.text('All branches'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Create invitation'));
    await tester.tap(find.text('Create invitation'));
    await tester.pumpAndSettle();

    expect(find.text('Choose at least one branch.'), findsOneWidget);
    expect(http.sent('POST', '/businesses/$businessId/invitations'), isEmpty);
  });

  group('invitation link', () {
    testWidgets('signed out, it explains who sent it and asks to sign in', (
      tester,
    ) async {
      http.reply('GET', '/invitations/$token', FakeResponse.ok(previewJson()));

      await pumpAppAt(
        tester,
        AppRoutes.invitation(token),
        http: http,
        authRepository: FakeAuthRepository(),
      );

      expect(find.text("You're invited to join Shifa Clinic"), findsOneWidget);
      expect(find.text('Role: Manager'), findsOneWidget);
      expect(find.text('Sent to sa****@example.com'), findsOneWidget);
      expect(find.text('Accept and join'), findsNothing);
      expect(find.text('Log in'), findsOneWidget);
    });

    testWidgets('the wrong account cannot accept', (tester) async {
      http.reply(
        'GET',
        '/invitations/$token',
        FakeResponse.ok(previewJson(canRespond: false)),
      );

      await pumpAppAt(tester, AppRoutes.invitation(token), http: http);

      expect(find.textContaining('different email address'), findsOneWidget);
      expect(find.text('Accept and join'), findsNothing);
    });

    testWidgets('the invited account accepts and lands in the business', (
      tester,
    ) async {
      http
        ..reply(
          'GET',
          '/invitations/$token',
          FakeResponse.ok(previewJson(canRespond: true)),
        )
        ..reply(
          'POST',
          '/invitations/$token/accept',
          FakeResponse.ok(businessJson(role: 'manager')),
        );

      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.invitation(token),
        http: http,
      );
      await tester.tap(find.text('Accept and join'));
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/invitations/$token/accept'), hasLength(1));
      expect(currentLocation(router), AppRoutes.business(businessId));
      expect(find.byType(BusinessOverviewPage), findsOneWidget);
    });

    testWidgets('an expired link is explained', (tester) async {
      http.reply(
        'GET',
        '/invitations/$token',
        FakeResponse.ok(previewJson(status: 'expired')),
      );

      await pumpAppAt(tester, AppRoutes.invitation(token), http: http);

      expect(
        find.text('This invitation has expired. Ask for a new one.'),
        findsOneWidget,
      );
      expect(find.text('Accept and join'), findsNothing);
    });

    testWidgets('an unknown link is explained', (tester) async {
      http.reply(
        'GET',
        '/invitations/$token',
        FakeResponse.error(404, 'invitation_not_found'),
      );

      await pumpAppAt(tester, AppRoutes.invitation(token), http: http);

      expect(find.text('This invitation link is not valid.'), findsOneWidget);
    });
  });
}
