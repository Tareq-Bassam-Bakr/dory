import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/config/map_config.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:dory_app/features/maps/presentation/providers/map_providers.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fake_location.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

void main() {
  const String chosenCity =
      '{"city":"Damascus","country":"SY","latitude":33.5138,"longitude":36.2765}';

  const MapTileConfig customTiles = MapTileConfig(
    urlTemplate: 'https://tiles.example.test/{z}/{x}/{y}.png?key={key}',
    attribution: 'Example Maps, OpenStreetMap contributors',
    attributionUrl: 'https://example.test/attribution',
    userAgentPackage: 'com.dory.app',
    apiKey: 'injected-at-build-time',
  );

  Future<GoRouter> openMap(
    WidgetTester tester, {
    FakeHttpAdapter? http,
    bool located = true,
    MapTileConfig? tiles = _osmTiles,
    FakeLocationService? location,
  }) async {
    setTestWindowSize(tester, const Size(390, 800));
    final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
    if (located) {
      await storage.writeString(StorageKeys.chosenCity, chosenCity);
    }
    final ProviderContainer container = createTestContainer(
      authRepository: FakeAuthRepository(),
      http: http ?? customerApi(),
      storage: storage,
      location: location,
      overrides: <Override>[mapTileConfigProvider.overrideWithValue(tiles)],
    );
    final GoRouter router = container.read(appRouterProvider);
    await pumpDoryApp(tester, container);
    await tester.pump();
    router.go(AppRoutes.customerMap);
    await tester.pumpAndSettle();
    return router;
  }

  group('map', () {
    testWidgets('shows branches as markers around the location', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi();

      await openMap(tester, http: http);

      expect(find.byType(FlutterMap), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp('Shifa Clinic')), findsOneWidget);
      final Map<String, dynamic> query = http
          .queries('/discovery/nearby')
          .single;
      expect(query['latitude'], 33.5138);
      expect(query['per_page'], 100);
    });

    testWidgets('selecting a marker opens a preview, then its branch', (
      tester,
    ) async {
      final GoRouter router = await openMap(tester);

      await tester.tap(find.bySemanticsLabel(RegExp('Shifa Clinic')));
      await tester.pumpAndSettle();
      expect(find.text('Mazzeh'), findsOneWidget);

      await tester.tap(find.text('Mazzeh'));
      await tester.pumpAndSettle();
      expect(topLocation(router), AppRoutes.customerBranch(publicBranchId));
    });

    testWidgets('branches without coordinates are not placed anywhere', (
      tester,
    ) async {
      await openMap(
        tester,
        http: customerApi()
          ..reply(
            'GET',
            '/discovery/nearby',
            paged(<Map<String, Object?>>[publicBranchJson(latitude: null)]),
          ),
      );

      expect(find.bySemanticsLabel(RegExp('Shifa Clinic')), findsNothing);
      expect(find.text('No branches found in this area.'), findsOneWidget);
    });

    testWidgets('categories filter what is visible', (tester) async {
      final FakeHttpAdapter http = customerApi();
      await openMap(tester, http: http);

      await tapChip(tester, 'Beauty & Salons');

      expect(
        http.queries('/discovery/nearby').last['category'],
        'beauty-salons',
      );
    });

    testWidgets('the attribution is always visible and credits the data', (
      tester,
    ) async {
      await openMap(tester);

      expect(find.byType(SimpleAttributionWidget), findsOneWidget);
      expect(find.text('OpenStreetMap contributors'), findsOneWidget);

      final SimpleAttributionWidget attribution = tester.widget(
        find.byType(SimpleAttributionWidget),
      );
      expect(attribution.onTap, isNotNull);
    });

    testWidgets('a selected branch does not cover the attribution', (
      tester,
    ) async {
      await openMap(tester);

      await tester.tap(find.bySemanticsLabel(RegExp('Shifa Clinic')));
      await tester.pumpAndSettle();

      final Rect attribution = tester.getRect(
        find.text('OpenStreetMap contributors'),
      );
      final Rect card = tester.getRect(find.text('Mazzeh'));
      expect(attribution.top, greaterThan(card.bottom));
    });

    testWidgets('the tile provider is configuration, not code', (tester) async {
      await openMap(tester, tiles: customTiles);

      final TileLayer layer = tester.widget(find.byType(TileLayer));
      expect(layer.urlTemplate, customTiles.urlTemplate);
      expect(layer.additionalOptions['key'], 'injected-at-build-time');
      expect(find.text(customTiles.attribution), findsOneWidget);
    });

    testWidgets('tile requests identify the app', (tester) async {
      await openMap(tester);

      final TileLayer layer = tester.widget(find.byType(TileLayer));
      expect(
        layer.tileProvider.headers['User-Agent'],
        contains('com.dory.app'),
      );
    });

    testWidgets('without a tile provider the list stays available', (
      tester,
    ) async {
      final GoRouter router = await openMap(tester, tiles: null);

      expect(find.byType(FlutterMap), findsNothing);
      expect(find.text('Map not available'), findsOneWidget);

      await tester.tap(find.byIcon(Icons.view_list_rounded));
      await tester.pumpAndSettle();
      expect(currentLocation(router), AppRoutes.customerExplore);
    });

    testWidgets('without a location it asks for one first', (tester) async {
      await openMap(tester, located: false);

      expect(find.byType(FlutterMap), findsNothing);
      expect(find.text('Set your location'), findsOneWidget);
    });

    testWidgets('the customer marker appears only for a device location', (
      tester,
    ) async {
      final FakeLocationService location = FakeLocationService(
        access: LocationAccess.granted,
        position: const GeoPoint(33.5, 36.3),
      );
      await openMap(tester, location: location);
      expect(find.bySemanticsLabel('Your location'), findsNothing);

      final BuildContext context = tester.element(find.byType(FlutterMap));
      await ProviderScope.containerOf(
        context,
      ).read(customerLocationProvider.notifier).useDevice();
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel('Your location'), findsOneWidget);
    });

    testWidgets('recentering moves the map back', (tester) async {
      await openMap(tester);

      await tester.tap(find.byIcon(Icons.my_location_rounded));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
    });
  });

  group('map configuration', () {
    test('debug builds fall back to the public OSM server for development', () {
      final MapTileConfig? tiles = MapConfig.tiles;

      expect(tiles?.urlTemplate, MapConfig.developmentTileUrl);
      expect(tiles?.attribution, MapConfig.osmAttribution);
      expect(tiles?.attributionUrl, 'https://www.openstreetmap.org/copyright');
      expect(tiles?.apiKey, isNull);
    });
  });
}

const MapTileConfig _osmTiles = MapTileConfig(
  urlTemplate: MapConfig.developmentTileUrl,
  attribution: MapConfig.osmAttribution,
  attributionUrl: MapConfig.osmCopyrightUrl,
  userAgentPackage: MapConfig.userAgentPackage,
);
