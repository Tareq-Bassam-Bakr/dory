import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/api_fixtures.dart';
import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fake_location.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

void main() {
  Future<void> scrollTo(WidgetTester tester, Finder target) async {
    await tester.scrollUntilVisible(
      target,
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
  }

  group('customer home', () {
    testWidgets('shows real sections to a visitor without an account', (
      tester,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      expect(find.text('Where are you heading today?'), findsOneWidget);
      expect(find.text('Healthcare & Clinics'), findsWidgets);
      await scrollTo(tester, find.text('General consultation'));
      expect(find.text('General consultation'), findsOneWidget);
      await scrollTo(tester, find.text('Businesses on DORY'));
      await scrollTo(tester, find.text('Shifa Clinic').last);
      expect(find.text('Shifa Clinic'), findsWidgets);
    });

    testWidgets('greets a signed-in customer', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: customerApi(),
      );

      expect(find.text('Hello, Lina'), findsOneWidget);
    });

    testWidgets('categories come from the API', (tester) async {
      final FakeHttpAdapter http = customerApi();

      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
      );

      expect(http.sent('GET', '/business-categories'), hasLength(1));
      expect(find.text('Beauty & Salons'), findsWidgets);
    });

    testWidgets('a category opens Explore already filtered', (tester) async {
      final FakeHttpAdapter http = customerApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
      );

      await tester.tap(find.text('Beauty & Salons').first);
      await tester.pumpAndSettle();

      expect(currentLocation(router), AppRoutes.customerExplore);
      expect(
        http.sent('GET', '/discovery/businesses').last.queryParameters,
        containsPair('category', 'beauty-salons'),
      );
    });

    testWidgets('location is never asked for on opening the app', (
      tester,
    ) async {
      final FakeLocationService location = FakeLocationService();

      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
        locationService: location,
      );

      expect(location.checks, 0);
      expect(location.requests, 0);
      await scrollTo(tester, find.text('Set your location'));
      expect(find.text('Set your location'), findsOneWidget);
    });

    testWidgets('sections fail alone and offer a retry when offline', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('GET', '/discovery/queues', FakeResponse.offline);

      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
      );

      expect(find.text('Try again'), findsOneWidget);
      expect(find.text('Beauty & Salons'), findsWidgets);

      http.reply(
        'GET',
        '/discovery/queues',
        paged(<Map<String, Object?>>[availableQueueJson()]),
      );
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('General consultation'), findsOneWidget);
    });

    testWidgets('says so when no queue is open', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('GET', '/discovery/queues', paged(<Map<String, Object?>>[]));

      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
      );

      expect(find.text('No queues open right now'), findsOneWidget);
    });

    testWidgets('notifications are an honest placeholder', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      await tester.tap(find.byIcon(Icons.notifications_none_rounded));
      await tester.pump();

      expect(find.textContaining('upcoming update'), findsOneWidget);
      await tester.pumpAndSettle(const Duration(seconds: 5));
    });
  });

  group('workspace switching', () {
    testWidgets('owners get a shortcut to their business', (tester) async {
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(owns: true),
        http: customerApi()
          ..reply(
            'GET',
            '/businesses/manage',
            FakeResponse.ok(<Object?>[businessJson()]),
          ),
      );

      await tester.tap(find.byIcon(Icons.storefront_outlined));
      await tester.pumpAndSettle();

      expect(currentLocation(router), AppRoutes.businessDashboard);
    });

    testWidgets('customers without a business do not see the shortcut', (
      tester,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository.signedIn(),
        http: customerApi(),
      );

      expect(find.byIcon(Icons.storefront_outlined), findsNothing);
    });

    testWidgets('owners can move back to the customer side', (tester) async {
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.businessAccount,
        authRepository: FakeAuthRepository.signedIn(owns: true),
        http: customerApi(),
      );

      await tester.tap(find.byIcon(Icons.swap_horiz_rounded));
      await tester.pumpAndSettle();

      expect(currentLocation(router), AppRoutes.customerHome);
    });
  });

  group('explore', () {
    Future<void> openExplore(WidgetTester tester, FakeHttpAdapter http) =>
        pumpAppAt(
          tester,
          AppRoutes.customerExplore,
          authRepository: FakeAuthRepository(),
          http: http,
        );

    Finder searchField() => find.byType(TextField);

    testWidgets('typing searches once, after a pause', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await openExplore(tester, http);
      final int before = http.sent('GET', '/discovery/businesses').length;

      await tester.enterText(searchField(), 'c');
      await tester.pump(const Duration(milliseconds: 100));
      await tester.enterText(searchField(), 'cl');
      await tester.pump(const Duration(milliseconds: 100));
      await tester.enterText(searchField(), 'cli');
      await tester.pump(const Duration(milliseconds: 100));

      expect(http.sent('GET', '/discovery/businesses'), hasLength(before));

      await tester.pump(searchDebounce);
      await tester.pumpAndSettle();

      final List<RequestSent> sent = http.queries('/discovery/businesses');
      expect(sent, hasLength(before + 1));
      expect(sent.last['q'], 'cli');
    });

    testWidgets('results come from the server', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..on(
          'GET',
          '/discovery/businesses',
          (request) => paged(<Map<String, Object?>>[
            publicBusinessJson(
              name: request.queryParameters['q'] == 'dental'
                  ? 'Dental Care'
                  : 'Shifa Clinic',
            ),
          ]),
        );
      await openExplore(tester, http);

      await tester.enterText(searchField(), 'dental');
      await tester.pump(searchDebounce);
      await tester.pumpAndSettle();

      expect(find.text('Dental Care'), findsOneWidget);
      expect(find.text('Shifa Clinic'), findsNothing);
    });

    testWidgets('quick filters are applied by the server', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await openExplore(tester, http);

      await tapChip(tester, 'Accepting tickets');

      expect(http.queries('/discovery/businesses').last['accepting'], 1);
    });

    testWidgets('nearby asks for a location first, then filters', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi();
      await openExplore(tester, http);

      await tester.tap(find.text('Nearby'));
      await tester.pumpAndSettle();
      expect(find.text('Your location'), findsOneWidget);

      await tester.tap(find.widgetWithText(ListTile, 'Damascus'));
      await tester.pumpAndSettle();

      final Map<String, dynamic> last = http
          .queries('/discovery/businesses')
          .last;
      expect(last['latitude'], 33.5138);
      expect(last['radius_km'], 10.0);
    });

    testWidgets('the filter sheet sets wait, rating and sort', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await openExplore(tester, http);

      await tester.tap(find.text('Filters'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('About 30 min'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('4.0 and up'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Lowest wait'));
      await tester.pumpAndSettle();

      final Map<String, dynamic> last = http
          .queries('/discovery/businesses')
          .last;
      expect(last['max_wait'], 30);
      expect(last['min_rating'], 4.0);
      expect(last['sort'], 'wait');
    });

    testWidgets('more results load on scrolling, a page at a time', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..on('GET', '/discovery/businesses', (request) {
          final int page = request.queryParameters['page']! as int;
          return paged(
            <Map<String, Object?>>[
              for (int index = 0; index < 15; index++)
                publicBusinessJson(
                  id: 'p${page}i$index',
                  name: 'Business $page-$index',
                ),
            ],
            page: page,
            lastPage: 2,
          );
        });
      await openExplore(tester, http);

      await tester.drag(find.byType(ListView).last, const Offset(0, -2000));
      await tester.pumpAndSettle();

      final List<int> pages = http
          .queries('/discovery/businesses')
          .map((query) => query['page'] as int)
          .toList();
      expect(pages, <int>[1, 2]);
    });

    testWidgets('no results and failures are explained', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'GET',
          '/discovery/businesses',
          paged(<Map<String, Object?>>[]),
        );
      await openExplore(tester, http);
      expect(find.text('No results'), findsOneWidget);

      http.reply('GET', '/discovery/businesses', FakeResponse.offline);
      await tester.enterText(searchField(), 'clinic');
      await tester.pump(searchDebounce);
      await tester.pumpAndSettle();
      expect(find.text('Try again'), findsOneWidget);

      http.reply(
        'GET',
        '/discovery/businesses',
        paged(<Map<String, Object?>>[publicBusinessJson()]),
      );
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Shifa Clinic'), findsOneWidget);
    });

    testWidgets('recent searches are kept and can be cleared', (tester) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      await pumpAppAt(
        tester,
        AppRoutes.customerExplore,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
        storage: storage,
      );

      await tester.enterText(searchField(), 'clinic');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pump(searchDebounce);
      await tester.pumpAndSettle();
      expect(storage.readString(StorageKeys.recentSearches), '["clinic"]');

      await tester.tap(find.byIcon(Icons.close_rounded));
      await tester.pumpAndSettle();
      expect(find.text('Recent searches'), findsOneWidget);
      expect(find.widgetWithText(ActionChip, 'clinic'), findsOneWidget);

      await tester.tap(find.text('Clear').last);
      await tester.pumpAndSettle();
      expect(find.text('Recent searches'), findsNothing);
      expect(storage.readString(StorageKeys.recentSearches), '[]');
    });
  });

  group('location picker', () {
    Future<void> openPicker(
      WidgetTester tester,
      FakeLocationService location,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
        locationService: location,
      );
      await tester.tap(find.text('Set location').first);
      await tester.pumpAndSettle();
    }

    testWidgets('explains why before any system prompt', (tester) async {
      final FakeLocationService location = FakeLocationService();

      await openPicker(tester, location);

      expect(find.textContaining('not stored or tracked'), findsOneWidget);
      expect(location.requests, 0);
    });

    testWidgets('granted: nearby results use the position', (tester) async {
      final FakeHttpAdapter http = customerApi();
      final FakeLocationService location = FakeLocationService(
        afterRequest: LocationAccess.granted,
        position: const GeoPoint(33.5, 36.3),
      );
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
        locationService: location,
      );
      await tester.tap(find.text('Set location').first);
      await tester.pumpAndSettle();

      await tester.tap(find.text('Use my location'));
      await tester.pumpAndSettle();

      expect(find.text('Near me'), findsOneWidget);
      expect(http.queries('/discovery/nearby').single['latitude'], 33.5);
    });

    testWidgets('denied: offers the manual choice and keeps working', (
      tester,
    ) async {
      await openPicker(tester, FakeLocationService());

      await tester.tap(find.text('Use my location'));
      await tester.pumpAndSettle();

      expect(find.textContaining('was not allowed'), findsOneWidget);
      expect(find.widgetWithText(ListTile, 'Damascus'), findsOneWidget);
    });

    testWidgets('permanently denied: leads to system settings', (tester) async {
      final FakeLocationService location = FakeLocationService(
        access: LocationAccess.permanentlyDenied,
      );
      await openPicker(tester, location);

      await tester.tap(find.text('Use my location'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Open settings'));
      await tester.pumpAndSettle();

      expect(location.appSettingsOpened, 1);
    });

    testWidgets('a city chosen by hand drives nearby results', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await pumpAppAt(
        tester,
        AppRoutes.customerHome,
        authRepository: FakeAuthRepository(),
        http: http,
      );
      await tester.tap(find.text('Set location').first);
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(ListTile, 'Damascus'));
      await tester.pumpAndSettle();

      expect(find.text('Damascus'), findsWidgets);
      expect(http.queries('/discovery/nearby').single['longitude'], 36.2765);
    });
  });

  group('business and branch pages', () {
    testWidgets('a business shows its profile and branches', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerBusiness(publicSlug),
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      expect(
        find.text('Family medicine for the whole neighbourhood'),
        findsOneWidget,
      );
      expect(find.text('+963933000000'), findsOneWidget);
      expect(find.text('Mazzeh'), findsOneWidget);
    });

    testWidgets('an unavailable business says it was not found', (
      tester,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerBusiness('gone'),
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      expect(find.text('Try again'), findsOneWidget);
    });

    testWidgets('choosing a branch and a service leads to the queue', (
      tester,
    ) async {
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerBusiness(publicSlug),
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      await tester.tap(find.text('Mazzeh'));
      await tester.pumpAndSettle();
      expect(topLocation(router), AppRoutes.customerBranch(publicBranchId));
      expect(find.text('Choose a service'), findsOneWidget);
      expect(find.text('About 15 min'), findsOneWidget);

      await tester.tap(find.text('General consultation'));
      await tester.pumpAndSettle();
      expect(topLocation(router), AppRoutes.customerQueue(publicQueueId));
    });

    testWidgets('states come from the queue, with no invented hours', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'GET',
          '/discovery/branches/$publicBranchId/services',
          paged(<Map<String, Object?>>[
            publicServiceJson(
              id: 'a',
              name: 'Closed one',
              queue: queueAvailabilityJson(status: 'closed', accepting: false),
            ),
            publicServiceJson(
              id: 'b',
              name: 'Paused one',
              queue: queueAvailabilityJson(status: 'paused', accepting: false),
            ),
            publicServiceJson(
              id: 'c',
              name: 'Full one',
              queue: queueAvailabilityJson(accepting: false, full: true),
            ),
            publicServiceJson(id: 'd', name: 'No queue', withQueue: false),
          ]),
        );

      await pumpAppAt(
        tester,
        AppRoutes.customerBranch(publicBranchId),
        authRepository: FakeAuthRepository(),
        http: http,
      );

      expect(find.text('Closed'), findsOneWidget);
      expect(find.text('Paused'), findsOneWidget);
      expect(find.text('Full'), findsOneWidget);
      expect(find.text('No queue for this service'), findsOneWidget);
      expect(find.text('Open'), findsNothing);
    });
  });
}
