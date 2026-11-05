import 'dart:math';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/bootstrap.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/business_dashboard/presentation/pages/business_dashboard_page.dart';
import 'package:dory_app/features/businesses/presentation/pages/business_overview_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:material_ui/material_ui.dart';

/// End to end against a running DORY API (docs/FLUTTER_API_INTEGRATION.md):
///
///   php artisan serve            (backend/, port 8000)
///   adb reverse tcp:8000 tcp:8000
///   flutter test integration_test/phase03_e2e_test.dart -d DEVICE_ID
///
/// Registers a new account, creates a business through onboarding, then
/// starts the app again and checks the session was restored from secure
/// storage. Labels come from the active localization (any device language).
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  /// Pumps real frames until [finder] matches (network calls take real time).
  Future<void> pumpUntil(
    WidgetTester tester,
    Finder finder, {
    Duration timeout = const Duration(seconds: 30),
  }) async {
    final DateTime deadline = DateTime.now().add(timeout);
    while (finder.evaluate().isEmpty) {
      if (DateTime.now().isAfter(deadline)) {
        fail('Timed out waiting for $finder');
      }
      await tester.pump(const Duration(milliseconds: 200));
    }
    await tester.pumpAndSettle();
  }

  Future<void> tapText(WidgetTester tester, String text) async {
    final Finder target = find.text(text).last;
    await tester.ensureVisible(target);
    await tester.pumpAndSettle();
    await tester.tap(target);
    await tester.pumpAndSettle();
  }

  Future<void> enterField(
    WidgetTester tester,
    String label,
    String value,
  ) async {
    final Finder field = find.widgetWithText(TextFormField, label);
    await tester.ensureVisible(field);
    await tester.enterText(field, value);
    await tester.pumpAndSettle();
  }

  testWidgets('register, onboard a business and survive a restart', (
    tester,
  ) async {
    final FlutterExceptionHandler? testErrorHandler = FlutterError.onError;
    final int stamp = DateTime.now().millisecondsSinceEpoch;
    // A fresh throwaway password per run (no hardcoded credentials); it
    // meets the API policy: upper and lower case letters and digits.
    final Random random = Random.secure();
    final String password =
        'E2e${List<String>.generate(12, (_) => random.nextInt(36).toRadixString(36)).join()}Q7';
    final String email = 'e2e.$stamp@dory.test';
    final String businessName = 'E2E Clinic $stamp';

    /// Real app start. Any previous app is unmounted first, so its
    /// ProviderScope, session and router are disposed and the new start
    /// reads everything from device storage again (a second runApp alone
    /// would update the existing ProviderScope and keep its state).
    /// bootstrap() installs the app's error handlers; the test framework's
    /// are restored so failures are reported normally.
    Future<void> startApp() async {
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump();
      await bootstrap();
      FlutterError.onError = testErrorHandler;
      PlatformDispatcher.instance.onError = null;
    }

    final Finder welcome = find.byType(WelcomePage);
    final Finder signedIn = find.byWidgetPredicate(
      (w) => w is CustomerHomePage || w is BusinessDashboardPage,
    );

    await startApp();
    await pumpUntil(
      tester,
      find.byWidgetPredicate(
        (w) =>
            w is WelcomePage ||
            w is CustomerHomePage ||
            w is BusinessDashboardPage,
      ),
    );
    AppLocalizations l10n = AppLocalizations.of(
      tester.element(find.byType(Scaffold).first),
    );

    // 0. A session left by an earlier run: log out first.
    if (signedIn.evaluate().isNotEmpty) {
      await tapText(tester, l10n.navAccount);
      await tapText(tester, l10n.accountLogout);
      await tester.tap(find.widgetWithText(FilledButton, l10n.accountLogout));
      await pumpUntil(tester, welcome);
    }
    l10n = AppLocalizations.of(tester.element(welcome));

    // 1. Register a new account (email + password; phone left empty).
    await tapText(tester, l10n.welcomeCreateAccount);
    await enterField(tester, l10n.authFullNameLabel, 'E2E Owner');
    await enterField(tester, l10n.authEmailLabel, email);
    await enterField(tester, l10n.authPasswordLabel, password);
    await enterField(tester, l10n.authConfirmPasswordLabel, password);
    await tapText(tester, l10n.registerSubmit);
    await pumpUntil(tester, find.byType(CustomerHomePage));

    // 2. Open the business workspace: no businesses yet.
    await tapText(tester, l10n.navAccount);
    await tapText(tester, l10n.accountSwitchToBusiness);
    await pumpUntil(tester, find.text(l10n.dashboardSetupAction));
    expect(find.byType(BusinessDashboardPage), findsOneWidget);

    // 3. Onboarding: categories come from the API.
    await tapText(tester, l10n.dashboardSetupAction);
    await tapText(tester, l10n.createBusinessStart);
    await pumpUntil(tester, find.byType(ChoiceChip));
    await enterField(tester, l10n.businessNameLabel, businessName);
    await tester.tap(find.byType(ChoiceChip).first);
    await tester.pumpAndSettle();
    await tapText(tester, l10n.commonNext);
    await enterField(tester, l10n.businessDescriptionLabel, 'Family medicine');
    await enterField(
      tester,
      l10n.businessEmailLabel,
      'clinic.$stamp@dory.test',
    );
    await tapText(tester, l10n.commonNext);
    await enterField(tester, l10n.branchCityLabel, 'Damascus');
    await enterField(tester, l10n.branchLatitudeLabel, '33.5138');
    await enterField(tester, l10n.branchLongitudeLabel, '36.2765');
    await tapText(tester, l10n.commonNext);
    await enterField(tester, l10n.serviceNameLabel, 'General consultation');
    await tapText(tester, l10n.onboardingAddService);
    await tapText(tester, l10n.commonNext);
    await tapText(tester, l10n.onboardingCreate);

    await pumpUntil(tester, find.byType(BusinessOverviewPage));
    await pumpUntil(tester, find.text(businessName));
    await pumpUntil(tester, find.text(l10n.businessCompletionDone));
    debugPrint('E2E: business created through onboarding');

    // 4. Start the app again: the session comes back from secure storage
    //    and, now that the user owns a business, opens the business workspace.
    await startApp();
    await pumpUntil(tester, find.byType(BusinessDashboardPage));
    await pumpUntil(tester, find.text(businessName));
    expect(welcome, findsNothing);
    debugPrint('E2E: session restored after restart for $email');
  });
}
