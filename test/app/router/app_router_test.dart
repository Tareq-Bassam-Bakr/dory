import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/router/not_found_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/login_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/register_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/splash_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/business_dashboard/presentation/pages/business_dashboard_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:dory_app/features/profile/presentation/pages/account_page.dart';
import 'package:dory_app/features/queues/presentation/pages/business_queues_page.dart';
import 'package:dory_app/features/tickets/presentation/pages/tickets_page.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

void main() {
  late ProviderContainer container;
  late GoRouter router;

  String currentPath() => router.routerDelegate.currentConfiguration.uri.path;

  /// Typical phone viewport. The default 800x600 test surface is wider than
  /// the compact breakpoint and would render the tablet layout.
  const Size phoneSize = Size(390, 844);

  Future<void> pumpAppAt(
    WidgetTester tester,
    String location, {
    Size windowSize = phoneSize,
  }) async {
    setTestWindowSize(tester, windowSize);
    container = createTestContainer();
    router = container.read(appRouterProvider);
    await pumpDoryApp(tester, container);
    router.go(location);
    await tester.pumpAndSettle();
  }

  Future<void> tapText(WidgetTester tester, String text) async {
    final Finder target = find.text(text);
    await tester.ensureVisible(target);
    await tester.tap(target);
    await tester.pumpAndSettle();
  }

  group('Router configuration', () {
    testWidgets('starts at the splash route', (tester) async {
      container = createTestContainer();
      router = container.read(appRouterProvider);
      await pumpDoryApp(tester, container);
      await tester.pump();

      expect(currentPath(), AppRoutes.splash);
      expect(find.byType(SplashPage), findsOneWidget);

      // Let the splash timer complete so no timer is left pending.
      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();
    });

    final Map<String, Type> pagesByLocation = <String, Type>{
      AppRoutes.welcome: WelcomePage,
      AppRoutes.login: LoginPage,
      AppRoutes.register: RegisterPage,
      AppRoutes.customerHome: CustomerHomePage,
      AppRoutes.customerTickets: TicketsPage,
      AppRoutes.customerAccount: AccountPage,
      AppRoutes.businessDashboard: BusinessDashboardPage,
      AppRoutes.businessQueues: BusinessQueuesPage,
      AppRoutes.businessAccount: AccountPage,
    };

    for (final MapEntry<String, Type> entry in pagesByLocation.entries) {
      testWidgets('${entry.key} renders ${entry.value}', (tester) async {
        await pumpAppAt(tester, entry.key);

        expect(currentPath(), entry.key);
        expect(find.byType(entry.value), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }

    final Map<String, String> redirects = <String, String>{
      AppRoutes.root: AppRoutes.splash,
      AppRoutes.customerRoot: AppRoutes.customerHome,
      AppRoutes.businessRoot: AppRoutes.businessDashboard,
    };

    for (final MapEntry<String, String> entry in redirects.entries) {
      testWidgets('${entry.key} redirects to ${entry.value}', (tester) async {
        await pumpAppAt(tester, entry.key);
        expect(currentPath(), entry.value);
        if (entry.value == AppRoutes.splash) {
          await tester.pump(const Duration(seconds: 2));
          await tester.pumpAndSettle();
        }
      });
    }

    testWidgets('unknown locations show the not-found page', (tester) async {
      await pumpAppAt(tester, '/does-not-exist');
      expect(find.byType(NotFoundPage), findsOneWidget);

      await tester.tap(find.text('Back to start'));
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePage), findsOneWidget);
    });

    testWidgets('login and register stack on top of welcome', (tester) async {
      await pumpAppAt(tester, AppRoutes.login);
      expect(router.canPop(), isTrue);

      router.pop();
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.welcome);
      expect(find.byType(WelcomePage), findsOneWidget);
    });

    testWidgets('welcome buttons open login, registration, and guest home', (
      tester,
    ) async {
      await pumpAppAt(tester, AppRoutes.welcome);
      await tapText(tester, 'Log in');
      expect(find.byType(LoginPage), findsOneWidget);

      router.go(AppRoutes.welcome);
      await tester.pumpAndSettle();
      await tapText(tester, 'Create account');
      expect(find.byType(RegisterPage), findsOneWidget);

      router.go(AppRoutes.welcome);
      await tester.pumpAndSettle();
      await tapText(tester, 'Explore as a guest');
      expect(currentPath(), AppRoutes.customerHome);
    });
  });

  group('Navigation shells', () {
    testWidgets('customer shell switches tabs with the navigation bar', (
      tester,
    ) async {
      await pumpAppAt(tester, AppRoutes.customerHome);
      expect(find.byType(NavigationBar), findsOneWidget);

      await tester.tap(find.text('Tickets'));
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.customerTickets);
      expect(find.byType(TicketsPage), findsOneWidget);

      await tester.tap(find.text('Account').last);
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.customerAccount);
    });

    testWidgets('business shell switches tabs with the navigation bar', (
      tester,
    ) async {
      await pumpAppAt(tester, AppRoutes.businessDashboard);

      await tester.tap(find.text('Queues').last);
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.businessQueues);
      expect(find.byType(BusinessQueuesPage), findsOneWidget);
    });

    testWidgets('account page switches between workspaces', (tester) async {
      await pumpAppAt(tester, AppRoutes.customerAccount);

      await tester.tap(find.text('Switch to business workspace'));
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.businessDashboard);

      router.go(AppRoutes.businessAccount);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Switch to customer workspace'));
      await tester.pumpAndSettle();
      expect(currentPath(), AppRoutes.customerHome);
    });

    testWidgets('wide windows use a navigation rail instead of a bar', (
      tester,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.businessDashboard,
        windowSize: const Size(1200, 900),
      );

      expect(find.byType(NavigationRail), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
    });
  });

  group('AppRoutes.workspaceHome', () {
    test('maps each mobile workspace to its landing page', () {
      expect(
        AppRoutes.workspaceHome(AppWorkspace.customer),
        AppRoutes.customerHome,
      );
      expect(
        AppRoutes.workspaceHome(AppWorkspace.business),
        AppRoutes.businessDashboard,
      );
    });

    test('rejects the administration workspace, which has no shell', () {
      expect(
        () => AppRoutes.workspaceHome(AppWorkspace.administration),
        throwsUnsupportedError,
      );
    });
  });
}
