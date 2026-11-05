import 'package:dio/dio.dart';
import 'package:dory_app/app/app.dart';
import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/theme/app_theme.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import 'fake_auth_repository.dart';
import 'fake_http_adapter.dart';

/// Base URL of the fake API in tests. Nothing listens there: every request
/// goes through the injected [FakeHttpAdapter].
const String testApiBaseUrl = 'http://dory.test/api/v1';

/// Overrides shared by every test app: volatile storage, a fake HTTP
/// transport (unhandled requests get a 404 envelope) and, unless the test
/// passes its own, a signed-out [FakeAuthRepository].
List<Override> testOverrides({
  KeyValueStorage? storage,
  AuthRepository? authRepository,
  HttpClientAdapter? http,
  SecureStore? secureStore,
}) => <Override>[
  keyValueStorageProvider.overrideWithValue(
    storage ?? InMemoryKeyValueStorage(),
  ),
  secureStoreProvider.overrideWithValue(secureStore ?? InMemorySecureStore()),
  apiBaseUrlProvider.overrideWithValue(testApiBaseUrl),
  httpClientAdapterProvider.overrideWithValue(http ?? FakeHttpAdapter()),
  if (authRepository != null)
    authRepositoryProvider.overrideWithValue(authRepository)
  else
    authRepositoryProvider.overrideWithValue(FakeAuthRepository()),
];

/// Creates a [ProviderContainer] wired like `bootstrap()`, but with the
/// test doubles of [testOverrides]. Disposed automatically at the end of
/// the test.
ProviderContainer createTestContainer({
  KeyValueStorage? storage,
  AuthRepository? authRepository,
  HttpClientAdapter? http,
  List<Override> overrides = const <Override>[],
}) {
  return ProviderContainer.test(
    overrides: <Override>[
      ...testOverrides(
        storage: storage,
        authRepository: authRepository,
        http: http,
      ),
      ...overrides,
    ],
  );
}

/// Pumps the full [DoryApp] on top of [container].
Future<void> pumpDoryApp(WidgetTester tester, ProviderContainer container) {
  return tester.pumpWidget(
    UncontrolledProviderScope(container: container, child: const DoryApp()),
  );
}

/// Pumps [child] in a [MaterialApp] configured like [DoryApp] (theme and
/// localization), for testing widgets in isolation.
///
/// Pass `settle: false` for trees with endless animations (e.g. an
/// indeterminate progress indicator), which never settle.
Future<void> pumpLocalizedWidget(
  WidgetTester tester,
  Widget child, {
  Locale locale = AppLocales.english,
  bool settle = true,
  List<Override> overrides = const <Override>[],
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: <Override>[...testOverrides(), ...overrides],
      child: MaterialApp(
        theme: AppTheme.light,
        locale: locale,
        supportedLocales: AppLocales.supported,
        localizationsDelegates: AppLocales.delegates,
        home: Scaffold(body: child),
      ),
    ),
  );
  if (settle) {
    await tester.pumpAndSettle();
  } else {
    await tester.pump();
  }
}

/// Sets a window size for the current test and restores it afterwards.
void setTestWindowSize(WidgetTester tester, Size logicalSize) {
  tester.view
    ..devicePixelRatio = 1
    ..physicalSize = logicalSize;
  addTearDown(tester.view.reset);
}

/// Opens the full app at [location], signed in as a business owner unless
/// [authRepository] says otherwise, and returns its router.
Future<GoRouter> pumpAppAt(
  WidgetTester tester,
  String location, {
  AuthRepository? authRepository,
  HttpClientAdapter? http,
  Size size = const Size(390, 900),
}) async {
  setTestWindowSize(tester, size);
  final ProviderContainer container = createTestContainer(
    authRepository: authRepository ?? FakeAuthRepository.signedIn(owns: true),
    http: http,
  );
  final GoRouter router = container.read(appRouterProvider);
  await pumpDoryApp(tester, container);
  await tester.pump(); // session restore
  router.go(location);
  await tester.pumpAndSettle();
  return router;
}

/// The path the router currently shows.
String currentLocation(GoRouter router) =>
    router.routerDelegate.currentConfiguration.uri.path;
