import 'package:dory_app/app/app.dart';
import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/theme/app_theme.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

/// Creates a [ProviderContainer] wired like `bootstrap()`, but with volatile
/// storage. Disposed automatically at the end of the test.
ProviderContainer createTestContainer({KeyValueStorage? storage}) {
  return ProviderContainer.test(
    overrides: [
      keyValueStorageProvider.overrideWithValue(
        storage ?? InMemoryKeyValueStorage(),
      ),
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
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        keyValueStorageProvider.overrideWithValue(InMemoryKeyValueStorage()),
      ],
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
