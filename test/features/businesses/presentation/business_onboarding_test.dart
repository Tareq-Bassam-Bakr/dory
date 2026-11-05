import 'package:dio/dio.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/businesses/presentation/pages/business_overview_page.dart';
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
      ..reply('GET', '/business-categories', FakeResponse.ok(categoriesJson()))
      ..reply('GET', '/businesses/manage', FakeResponse.ok(<Object?>[]))
      ..reply('GET', '/businesses/$businessId', FakeResponse.ok(businessJson()))
      ..reply(
        'POST',
        '/businesses',
        FakeResponse.ok(businessJson(), status: 201),
      );
  });

  Future<GoRouter> openOnboarding(WidgetTester tester) =>
      pumpAppAt(tester, AppRoutes.businessOnboarding, http: http);

  Future<void> tapLabel(WidgetTester tester, String label) async {
    final Finder button = find.text(label).last;
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
  }

  Future<void> fillBasics(WidgetTester tester) async {
    await tester.enterText(find.byType(TextFormField).first, 'Shifa Clinic');
    await tester.tap(find.text('Healthcare & Clinics'));
    await tester.pumpAndSettle();
  }

  testWidgets('the first step needs a name and a category from the API', (
    tester,
  ) async {
    await openOnboarding(tester);
    expect(find.text('Step 1 of 5'), findsOneWidget);
    expect(
      find.text('Beauty & Salons'),
      findsOneWidget,
      reason: 'Categories come from the API.',
    );

    await tapLabel(tester, 'Next');

    expect(
      find.text('Enter a name between 2 and 150 characters.'),
      findsOneWidget,
    );
    expect(find.text('Choose a category.'), findsOneWidget);
    expect(find.text('Step 1 of 5'), findsOneWidget);
  });

  testWidgets('entered data is kept when moving between steps', (tester) async {
    await openOnboarding(tester);
    await fillBasics(tester);
    await tapLabel(tester, 'Next');
    expect(find.text('Step 2 of 5'), findsOneWidget);
    await tester.enterText(
      find.byType(TextFormField).first,
      'Family medicine and pediatrics',
    );

    await tapLabel(tester, 'Back');
    expect(find.text('Step 1 of 5'), findsOneWidget);
    expect(find.text('Shifa Clinic'), findsOneWidget);

    await tapLabel(tester, 'Next');
    expect(find.text('Family medicine and pediatrics'), findsOneWidget);
  });

  testWidgets('invalid optional details block the step until corrected', (
    tester,
  ) async {
    await openOnboarding(tester);
    await fillBasics(tester);
    await tapLabel(tester, 'Next');

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Website'),
      'not a url',
    );
    await tapLabel(tester, 'Next');

    expect(
      find.text('Enter a full web address, e.g. https://example.com.'),
      findsOneWidget,
    );
    expect(find.text('Step 2 of 5'), findsOneWidget);
  });

  testWidgets(
    'all five steps create the business, its branch and services once',
    (tester) async {
      final GoRouter router = await openOnboarding(tester);
      await fillBasics(tester);
      await tapLabel(tester, 'Next');
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Business email'),
        'Hello@Shifa.Example',
      );
      await tapLabel(tester, 'Next');

      expect(
        find.text('Main branch'),
        findsWidgets,
        reason: 'A default branch name is suggested.',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'City'),
        'Damascus',
      );
      await tapLabel(tester, 'Next');

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Service name'),
        'Consultation',
      );
      await tapLabel(tester, 'Add service');
      expect(find.text('15 min'), findsOneWidget);
      await tapLabel(tester, 'Next');

      expect(find.text('Step 5 of 5'), findsOneWidget);
      expect(find.text('Shifa Clinic'), findsOneWidget);
      expect(find.text('hello@shifa.example'), findsOneWidget);
      expect(find.text('Consultation'), findsOneWidget);

      // Two quick taps: the second one hits the loading button and is ignored.
      final Finder create = find.text('Create business').last;
      await tester.ensureVisible(create);
      await tester.tap(create);
      await tester.pump();
      await tester.tap(find.byType(FilledButton).last, warnIfMissed: false);
      await tester.pumpAndSettle();

      final List<RequestOptions> posts = http.sent('POST', '/businesses');
      expect(posts, hasLength(1));
      final Map<String, Object?> body =
          posts.single.data as Map<String, Object?>;
      expect(body['name'], 'Shifa Clinic');
      expect(body['category'], 'healthcare-clinics');
      expect(body['email'], 'hello@shifa.example');
      final Map<String, Object?> branch =
          body['branch']! as Map<String, Object?>;
      expect(branch['name'], 'Main branch');
      expect(branch['city'], 'Damascus');
      expect((branch['services']! as List<Object?>).single, <String, Object?>{
        'name': 'Consultation',
        'estimated_duration_minutes': 15,
        'status': 'active',
      });
      expect(
        posts.single.headers['Idempotency-Key'],
        matches(RegExp(r'^[0-9a-f]{32}$')),
      );

      expect(currentLocation(router), AppRoutes.business(businessId));
      expect(find.byType(BusinessOverviewPage), findsOneWidget);
    },
  );

  testWidgets('server field errors send the user back to the right step', (
    tester,
  ) async {
    http.reply(
      'POST',
      '/businesses',
      FakeResponse.error(
        422,
        'validation_failed',
        errors: <String, List<String>>{
          'name': <String>['The name contains invalid characters.'],
        },
      ),
    );
    await openOnboarding(tester);
    await fillBasics(tester);
    for (int step = 1; step < 5; step++) {
      await tapLabel(tester, 'Next');
    }

    await tapLabel(tester, 'Create business');

    expect(find.text('Step 1 of 5'), findsOneWidget);
    expect(find.text('The name contains invalid characters.'), findsOneWidget);
    expect(find.text('Please correct the highlighted fields.'), findsOneWidget);
  });
}
