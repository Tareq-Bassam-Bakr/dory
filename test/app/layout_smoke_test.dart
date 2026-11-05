import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../helpers/test_app.dart';

/// Renders every screen in both languages on small and large windows.
///
/// Layout overflows are reported as exceptions by the framework, so this
/// catches text that does not fit (Arabic strings are often longer, and RTL
/// mirrors every row).
void main() {
  const List<String> locations = <String>[
    AppRoutes.welcome,
    AppRoutes.login,
    AppRoutes.register,
    AppRoutes.customerHome,
    AppRoutes.customerTickets,
    AppRoutes.customerAccount,
    AppRoutes.businessDashboard,
    AppRoutes.businessQueues,
    AppRoutes.businessAccount,
  ];
  const Map<String, Size> windows = <String, Size>{
    'small phone': Size(360, 640),
    'tablet': Size(1024, 768),
  };

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
          );
          await pumpDoryApp(tester, container);
          container.read(appRouterProvider).go(location);
          await tester.pumpAndSettle();

          expect(tester.takeException(), isNull);
        });
      }
    }
  }
}
