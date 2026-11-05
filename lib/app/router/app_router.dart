import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/router/not_found_page.dart';
import 'package:dory_app/app/router/workspace_shell.dart';
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
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The application [GoRouter].
///
/// Access control: Phase 01 has no session, so no route is guarded. Phase 02
/// adds a top-level `redirect` that reads the session and uses
/// `UserRoleSet.canAccess` to keep users out of workspaces their roles do
/// not grant, with `refreshListenable` tied to session changes.
final Provider<GoRouter> appRouterProvider = Provider<GoRouter>((ref) {
  final GoRouter router = GoRouter(
    initialLocation: AppRoutes.splash,
    debugLogDiagnostics: kDebugMode,
    errorBuilder: (context, state) => const NotFoundPage(),
    routes: _buildRoutes(),
  );
  ref.onDispose(router.dispose);
  return router;
}, name: 'appRouterProvider');

/// Builds a fresh route tree. Shell branches own navigator keys, so route
/// objects must never be shared between two [GoRouter] instances.
List<RouteBase> _buildRoutes() => <RouteBase>[
  GoRoute(path: AppRoutes.root, redirect: (_, _) => AppRoutes.splash),
  GoRoute(
    path: AppRoutes.splash,
    builder: (context, state) => const SplashPage(),
  ),
  GoRoute(
    path: AppRoutes.welcome,
    builder: (context, state) => const WelcomePage(),
    routes: <RouteBase>[
      GoRoute(
        path: AppRoutes.loginSegment,
        builder: (context, state) => const LoginPage(),
      ),
      GoRoute(
        path: AppRoutes.registerSegment,
        builder: (context, state) => const RegisterPage(),
      ),
    ],
  ),
  GoRoute(
    path: AppRoutes.customerRoot,
    redirect: (_, _) => AppRoutes.workspaceHome(AppWorkspace.customer),
  ),
  StatefulShellRoute.indexedStack(
    builder: (context, state, navigationShell) => WorkspaceShell(
      navigationShell: navigationShell,
      destinations: _customerDestinations,
    ),
    branches: <StatefulShellBranch>[
      _branch(AppRoutes.customerHome, (_) => const CustomerHomePage()),
      _branch(AppRoutes.customerTickets, (_) => const TicketsPage()),
      _branch(
        AppRoutes.customerAccount,
        (_) => const AccountPage(workspace: AppWorkspace.customer),
      ),
    ],
  ),
  GoRoute(
    path: AppRoutes.businessRoot,
    redirect: (_, _) => AppRoutes.workspaceHome(AppWorkspace.business),
  ),
  StatefulShellRoute.indexedStack(
    builder: (context, state, navigationShell) => WorkspaceShell(
      navigationShell: navigationShell,
      destinations: _businessDestinations,
    ),
    branches: <StatefulShellBranch>[
      _branch(
        AppRoutes.businessDashboard,
        (_) => const BusinessDashboardPage(),
      ),
      _branch(AppRoutes.businessQueues, (_) => const BusinessQueuesPage()),
      _branch(
        AppRoutes.businessAccount,
        (_) => const AccountPage(workspace: AppWorkspace.business),
      ),
    ],
  ),
];

StatefulShellBranch _branch(String path, WidgetBuilder builder) {
  return StatefulShellBranch(
    routes: <RouteBase>[
      GoRoute(path: path, builder: (context, state) => builder(context)),
    ],
  );
}

// Destination order must match the branch order above.
final List<ShellDestination> _customerDestinations = <ShellDestination>[
  ShellDestination(
    icon: Icons.home_outlined,
    selectedIcon: Icons.home_rounded,
    label: (l10n) => l10n.navHome,
  ),
  ShellDestination(
    icon: Icons.confirmation_number_outlined,
    selectedIcon: Icons.confirmation_number_rounded,
    label: (l10n) => l10n.navTickets,
  ),
  ShellDestination(
    icon: Icons.person_outline_rounded,
    selectedIcon: Icons.person_rounded,
    label: (l10n) => l10n.navAccount,
  ),
];

final List<ShellDestination> _businessDestinations = <ShellDestination>[
  ShellDestination(
    icon: Icons.space_dashboard_outlined,
    selectedIcon: Icons.space_dashboard_rounded,
    label: (l10n) => l10n.navDashboard,
  ),
  ShellDestination(
    icon: Icons.format_list_numbered_rounded,
    selectedIcon: Icons.format_list_numbered_rounded,
    label: (l10n) => l10n.navQueues,
  ),
  ShellDestination(
    icon: Icons.person_outline_rounded,
    selectedIcon: Icons.person_rounded,
    label: (l10n) => l10n.navAccount,
  ),
];
