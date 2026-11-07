import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/bootstrap.dart';
import 'package:dory_app/features/authentication/presentation/pages/splash_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

/// On-device smoke test: real bootstrap (platform storage, error handlers),
/// splash, welcome, then the customer shell.
///
/// Run on a device or emulator: `flutter test integration_test -d <device-id>`
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('launches through splash to welcome and opens the guest shell', (
    tester,
  ) async {
    // bootstrap() installs production error handlers; putting the test
    // framework's handler back keeps the real message of a failure.
    final FlutterExceptionHandler? testErrorHandler = FlutterError.onError;

    await bootstrap();
    FlutterError.onError = testErrorHandler;
    await tester.pump();
    expect(find.byType(SplashPage), findsOneWidget);

    // Wait out the splash on the real clock.
    await tester.pumpAndSettle();
    await Future<void>.delayed(const Duration(seconds: 2));
    await tester.pumpAndSettle();
    expect(find.byType(WelcomePage), findsOneWidget);

    // Locale-independent: read labels from the active localization.
    final AppLocalizations l10n = AppLocalizations.of(
      tester.element(find.byType(WelcomePage)),
    );
    final Finder guestButton = find.text(l10n.welcomeExploreAsGuest);
    await tester.ensureVisible(guestButton);
    await tester.tap(guestButton);
    await tester.pumpAndSettle();

    expect(find.byType(CustomerHomePage), findsOneWidget);
    expect(find.text(l10n.navMyTickets), findsOneWidget);
  });
}
