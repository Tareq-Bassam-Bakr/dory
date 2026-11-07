import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/bootstrap.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/discovery/presentation/pages/explore_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:dory_app/features/tickets/presentation/pages/customer_ticket_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:material_ui/material_ui.dart';

/// The customer journey on a real device, against a running DORY API:
///
///   php artisan serve            (backend/, port 8000)
///   adb reverse tcp:8000 tcp:8000
///   flutter test integration_test/phase05_e2e_test.dart -d DEVICE_ID
///
/// Needs a public business with a queue accepting guests, such as the demo
/// one (docs/CUSTOMER_EXPERIENCE.md, "Trying it locally"). Never point it at
/// production data: it joins and cancels a real ticket.
///
/// Browses as a guest, joins a queue, starts the app again to prove the
/// ticket and its secure token come back from device storage, then leaves the
/// queue. Labels come from the active localization, so it runs in any device
/// language.
///
/// The second start happens inside the same process, as a test must. Check a
/// real process restart by hand: `adb shell am force-stop com.dory.app`.
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

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
    await tester.pump(const Duration(milliseconds: 500));
  }

  Future<void> tap(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder.first);
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(finder.first);
    await tester.pump(const Duration(milliseconds: 500));
  }

  AppLocalizations l10nOf(WidgetTester tester, Type page) =>
      AppLocalizations.of(tester.element(find.byType(page).first));

  Future<void> startApp(WidgetTester tester) async {
    // bootstrap() installs the production error handlers. Putting the test
    // framework's handler back keeps the real message of a failure.
    final FlutterExceptionHandler? testErrorHandler = FlutterError.onError;
    await bootstrap();
    FlutterError.onError = testErrorHandler;
    await tester.pump(const Duration(seconds: 2));
  }

  testWidgets('a guest finds a queue, joins, restarts and leaves', (
    tester,
  ) async {
    await startApp(tester);

    // Welcome → browse without an account.
    await pumpUntil(tester, find.byType(WelcomePage));
    await tap(
      tester,
      find.text(l10nOf(tester, WelcomePage).welcomeExploreAsGuest),
    );
    await pumpUntil(tester, find.byType(CustomerHomePage));
    final AppLocalizations l10n = l10nOf(tester, CustomerHomePage);

    // Explore → first business → first branch → first open service.
    await tap(tester, find.text(l10n.navExplore));
    await pumpUntil(tester, find.byType(ExplorePage));
    await pumpUntil(tester, find.byType(Card));
    await tap(tester, find.byType(Card));
    await pumpUntil(tester, find.text(l10n.businessDetailsBranches));
    await pumpUntil(tester, find.byIcon(Icons.chevron_right_rounded));
    await tap(tester, find.byIcon(Icons.chevron_right_rounded));
    await pumpUntil(tester, find.text(l10n.branchDetailsServices));
    await pumpUntil(tester, find.text(l10n.queueStateOpen));
    await tap(tester, find.text(l10n.queueStateOpen));

    // Join as a guest, giving only what the queue requires.
    await pumpUntil(tester, find.text(l10n.joinAsGuestAction));
    final Finder name = find.byType(TextFormField);
    if (name.evaluate().isNotEmpty) {
      await tester.enterText(name.first, 'Device Guest');
    }
    await tap(tester, find.text(l10n.joinAsGuestAction));
    await pumpUntil(tester, find.byType(CustomerTicketPage));
    await pumpUntil(tester, find.text(l10n.ticketCancelAction));
    expect(find.text(l10n.ticketHeadlineWaiting), findsOneWidget);

    // A new app: the ticket and its token come back from device storage.
    await tester.pumpWidget(const SizedBox.shrink());
    await startApp(tester);
    await pumpUntil(tester, find.byType(WelcomePage));
    await tap(tester, find.text(l10n.welcomeExploreAsGuest));
    await pumpUntil(tester, find.byType(CustomerHomePage));
    await tap(tester, find.text(l10n.navMyTickets));
    // Guests also see a sign-in card here, so look for the ticket's status.
    final Finder storedTicket = find.descendant(
      of: find.byType(Card),
      matching: find.text(l10n.queueTicketStatusWaiting),
    );
    await pumpUntil(tester, storedTicket);
    await tap(tester, storedTicket);
    await pumpUntil(tester, find.text(l10n.ticketCancelAction));

    // Leave the queue.
    await tap(tester, find.text(l10n.ticketCancelAction));
    await pumpUntil(tester, find.byType(AlertDialog));
    await tap(
      tester,
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text(l10n.ticketCancelAction),
      ),
    );
    await pumpUntil(tester, find.text(l10n.ticketHeadlineCancelled));
    expect(find.text(l10n.ticketCancelAction), findsNothing);
    debugPrint('Phase 05 device journey completed.');
  });
}
