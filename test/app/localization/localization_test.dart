import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

/// Storage whose writes always fail, to test error handling.
class _FailingStorage implements KeyValueStorage {
  @override
  String? readString(String key) => null;

  @override
  Future<void> writeString(String key, String value) =>
      Future<void>.error(StateError('disk full'));

  @override
  Future<void> remove(String key) => Future<void>.error(StateError('disk'));
}

void main() {
  group('AppLocales', () {
    test('supports English (fallback first) and Arabic', () {
      expect(AppLocales.supported, <Locale>[
        const Locale('en'),
        const Locale('ar'),
      ]);
    });

    test('generated localizations support exactly the app locales', () {
      expect(
        AppLocalizations.supportedLocales.toSet(),
        AppLocales.supported.toSet(),
      );
    });
  });

  group('AppLanguage.fromStorageCode', () {
    test('parses supported codes', () {
      expect(AppLanguage.fromStorageCode('en'), AppLanguage.english);
      expect(AppLanguage.fromStorageCode('ar'), AppLanguage.arabic);
    });

    test('falls back to the device language for missing or unknown codes', () {
      expect(AppLanguage.fromStorageCode(null), AppLanguage.system);
      expect(AppLanguage.fromStorageCode('fr'), AppLanguage.system);
      expect(AppLanguage.fromStorageCode(''), AppLanguage.system);
    });
  });

  group('LanguageController', () {
    test('starts from the persisted language', () {
      final ProviderContainer container = createTestContainer(
        storage: InMemoryKeyValueStorage(<String, String>{
          StorageKeys.languageCode: 'ar',
        }),
      );
      expect(container.read(languageControllerProvider), AppLanguage.arabic);
    });

    test('defaults to the device language', () {
      final ProviderContainer container = createTestContainer();
      expect(container.read(languageControllerProvider), AppLanguage.system);
    });

    test('persists a selected language and clears it for system', () async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      final ProviderContainer container = createTestContainer(storage: storage);
      final LanguageController controller = container.read(
        languageControllerProvider.notifier,
      );

      await controller.select(AppLanguage.arabic);
      expect(container.read(languageControllerProvider), AppLanguage.arabic);
      expect(storage.readString(StorageKeys.languageCode), 'ar');

      await controller.select(AppLanguage.system);
      expect(container.read(languageControllerProvider), AppLanguage.system);
      expect(storage.readString(StorageKeys.languageCode), isNull);
    });

    test('keeps the selection when persisting fails', () async {
      final ProviderContainer container = createTestContainer(
        storage: _FailingStorage(),
      );

      await container
          .read(languageControllerProvider.notifier)
          .select(AppLanguage.arabic);

      expect(container.read(languageControllerProvider), AppLanguage.arabic);
    });
  });

  group('Localized rendering', () {
    Future<ProviderContainer> pumpWelcome(
      WidgetTester tester, {
      KeyValueStorage? storage,
    }) async {
      final ProviderContainer container = createTestContainer(storage: storage);
      await pumpDoryApp(tester, container);
      container.read(appRouterProvider).go(AppRoutes.welcome);
      await tester.pumpAndSettle();
      return container;
    }

    TextDirection welcomeDirection(WidgetTester tester) =>
        Directionality.of(tester.element(find.byType(WelcomePage)));

    testWidgets('English renders left-to-right', (tester) async {
      await pumpWelcome(
        tester,
        storage: InMemoryKeyValueStorage(<String, String>{
          StorageKeys.languageCode: 'en',
        }),
      );
      expect(welcomeDirection(tester), TextDirection.ltr);
      expect(find.text('Smart queues for every service'), findsOneWidget);
    });

    testWidgets('Arabic renders right-to-left', (tester) async {
      await pumpWelcome(
        tester,
        storage: InMemoryKeyValueStorage(<String, String>{
          StorageKeys.languageCode: 'ar',
        }),
      );
      expect(welcomeDirection(tester), TextDirection.rtl);
      expect(find.text('طوابير ذكية لكل خدمة'), findsOneWidget);
    });

    testWidgets('Material widgets are localized through material_ui', (
      tester,
    ) async {
      await pumpWelcome(
        tester,
        storage: InMemoryKeyValueStorage(<String, String>{
          StorageKeys.languageCode: 'ar',
        }),
      );
      final MaterialLocalizations material = MaterialLocalizations.of(
        tester.element(find.byType(WelcomePage)),
      );
      expect(material.okButtonLabel, isNot('OK'));
    });

    testWidgets('switching language from the picker updates the UI', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage(
        <String, String>{StorageKeys.languageCode: 'en'},
      );
      await pumpWelcome(tester, storage: storage);
      expect(welcomeDirection(tester), TextDirection.ltr);

      await tester.tap(find.byIcon(Icons.translate_rounded));
      await tester.pumpAndSettle();
      await tester.tap(find.text('العربية'));
      await tester.pumpAndSettle();

      expect(welcomeDirection(tester), TextDirection.rtl);
      expect(find.text('طوابير ذكية لكل خدمة'), findsOneWidget);
      expect(storage.readString(StorageKeys.languageCode), 'ar');

      await tester.tap(find.byIcon(Icons.translate_rounded));
      await tester.pumpAndSettle();
      await tester.tap(find.text('English'));
      await tester.pumpAndSettle();

      expect(welcomeDirection(tester), TextDirection.ltr);
      expect(find.text('Smart queues for every service'), findsOneWidget);
    });

    testWidgets('device language is followed when no choice is stored', (
      tester,
    ) async {
      tester.platformDispatcher.localesTestValue = const <Locale>[
        Locale('ar', 'SY'),
      ];
      addTearDown(tester.platformDispatcher.clearLocalesTestValue);

      await pumpWelcome(tester);
      expect(welcomeDirection(tester), TextDirection.rtl);
    });

    testWidgets('unsupported device languages fall back to English', (
      tester,
    ) async {
      tester.platformDispatcher.localesTestValue = const <Locale>[
        Locale('fr', 'FR'),
      ];
      addTearDown(tester.platformDispatcher.clearLocalesTestValue);

      await pumpWelcome(tester);
      expect(find.text('Smart queues for every service'), findsOneWidget);
    });
  });
}
