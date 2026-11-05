import 'package:dory_app/core/constants/app_constants.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/authentication/presentation/pages/splash_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/business_dashboard/presentation/pages/business_dashboard_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../helpers/fake_auth_repository.dart';
import '../helpers/test_app.dart';

void main() {
  group('Application startup', () {
    testWidgets('shows the splash brand, then continues to welcome', (
      tester,
    ) async {
      await pumpDoryApp(tester, createTestContainer());
      await tester.pump();

      expect(find.byType(SplashPage), findsOneWidget);
      expect(find.text(AppConstants.appName), findsOneWidget);
      expect(find.text('Your Turn. Your Time.'), findsOneWidget);

      await tester.pump(AppConstants.splashMinimumDuration);
      await tester.pumpAndSettle();

      expect(find.byType(SplashPage), findsNothing);
      expect(find.byType(WelcomePage), findsOneWidget);
      expect(find.text('Smart queues for every service'), findsOneWidget);
    });

    testWidgets('does not leave the splash before the minimum duration', (
      tester,
    ) async {
      await pumpDoryApp(tester, createTestContainer());
      await tester.pump(
        AppConstants.splashMinimumDuration - const Duration(milliseconds: 1),
      );

      expect(find.byType(SplashPage), findsOneWidget);

      await tester.pump(const Duration(milliseconds: 1));
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePage), findsOneWidget);
    });

    testWidgets('restores the persisted language before the first frame', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage(
        <String, String>{StorageKeys.languageCode: 'ar'},
      );
      await pumpDoryApp(tester, createTestContainer(storage: storage));
      await tester.pump();

      expect(find.text('دورك. وقتك.'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.byType(SplashPage))),
        TextDirection.rtl,
      );

      await tester.pump(AppConstants.splashMinimumDuration);
      await tester.pumpAndSettle();
    });

    testWidgets(
      'a stored session survives a restart: owners land on their businesses',
      (tester) async {
        await pumpDoryApp(
          tester,
          createTestContainer(
            authRepository: FakeAuthRepository.signedIn(owns: true),
          ),
        );
        await tester.pump();
        expect(find.byType(SplashPage), findsOneWidget);

        await tester.pump(AppConstants.splashMinimumDuration);
        await tester.pumpAndSettle();

        expect(find.byType(BusinessDashboardPage), findsOneWidget);
        expect(find.byType(WelcomePage), findsNothing);
      },
    );

    testWidgets('a restored customer lands on the customer home', (
      tester,
    ) async {
      await pumpDoryApp(
        tester,
        createTestContainer(authRepository: FakeAuthRepository.signedIn()),
      );
      await tester.pump(AppConstants.splashMinimumDuration);
      await tester.pumpAndSettle();

      expect(find.byType(CustomerHomePage), findsOneWidget);
    });
  });
}
