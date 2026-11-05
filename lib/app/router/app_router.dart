import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/router/auth_redirect.dart';
import 'package:dory_app/app/router/not_found_page.dart';
import 'package:dory_app/app/router/workspace_shell.dart';
import 'package:dory_app/features/authentication/presentation/pages/login_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/register_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/splash_page.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/branch_management/presentation/pages/branch_details_page.dart';
import 'package:dory_app/features/branch_management/presentation/pages/branch_form_page.dart';
import 'package:dory_app/features/branch_management/presentation/pages/branch_list_page.dart';
import 'package:dory_app/features/business_dashboard/presentation/pages/business_dashboard_page.dart';
import 'package:dory_app/features/businesses/presentation/pages/business_onboarding_page.dart';
import 'package:dory_app/features/businesses/presentation/pages/business_overview_page.dart';
import 'package:dory_app/features/businesses/presentation/pages/create_business_page.dart';
import 'package:dory_app/features/businesses/presentation/pages/edit_business_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:dory_app/features/profile/presentation/pages/account_page.dart';
import 'package:dory_app/features/queues/presentation/pages/business_queues_page.dart';
import 'package:dory_app/features/services/presentation/pages/service_form_page.dart';
import 'package:dory_app/features/services/presentation/pages/services_list_page.dart';
import 'package:dory_app/features/team/presentation/pages/invitation_details_page.dart';
import 'package:dory_app/features/team/presentation/pages/invite_member_page.dart';
import 'package:dory_app/features/team/presentation/pages/team_members_page.dart';
import 'package:dory_app/features/tickets/presentation/pages/tickets_page.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The application [GoRouter].
///
/// Access control (UX only; the API authorizes every request): the
/// top-level `redirect` applies [authRedirect] to every navigation, and the
/// router re-evaluates it whenever the session changes (login, logout, or
/// a token the server rejected).
final Provider<GoRouter> appRouterProvider = Provider<GoRouter>((ref) {
  final ValueNotifier<AuthState> session = ValueNotifier<AuthState>(
    ref.read(authControllerProvider),
  );
  ref.listen<AuthState>(
    authControllerProvider,
    (previous, next) => session.value = next,
  );

  final GoRouter router = GoRouter(
    initialLocation: AppRoutes.splash,
    debugLogDiagnostics: kDebugMode,
    refreshListenable: session,
    redirect: (context, state) => authRedirect(session.value, state.uri),
    errorBuilder: (context, state) => const NotFoundPage(),
    routes: _buildRoutes(),
  );
  ref.onDispose(() {
    router.dispose();
    session.dispose();
  });
  return router;
}, name: 'appRouterProvider');

/// `?from=` of the current location, if it points inside the app.
String? _from(GoRouterState state) =>
    AppRoutes.safeReturnLocation(state.uri.queryParameters['from']);

/// Builds a fresh route tree. Shell branches own navigator keys, so route
/// objects must never be shared between two [GoRouter] instances.
List<RouteBase> _buildRoutes() => <RouteBase>[
  GoRoute(path: AppRoutes.root, redirect: (_, _) => AppRoutes.splash),
  GoRoute(
    path: AppRoutes.splash,
    builder: (context, state) => SplashPage(from: _from(state)),
  ),
  GoRoute(
    path: AppRoutes.welcome,
    builder: (context, state) => const WelcomePage(),
    routes: <RouteBase>[
      GoRoute(
        path: AppRoutes.loginSegment,
        builder: (context, state) => LoginPage(from: _from(state)),
      ),
      GoRoute(
        path: AppRoutes.registerSegment,
        builder: (context, state) => RegisterPage(from: _from(state)),
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
  // Business creation is full screen (outside the tab shell).
  GoRoute(
    path: AppRoutes.businessCreate,
    builder: (context, state) => const CreateBusinessPage(),
    routes: <RouteBase>[
      GoRoute(
        path: 'onboarding',
        builder: (context, state) => const BusinessOnboardingPage(),
      ),
    ],
  ),
  // Invitation links (dory://app/invitations/<token>). Viewing is public;
  // accepting requires signing in with the invited account.
  GoRoute(
    path: '${AppRoutes.invitations}/:token',
    builder: (context, state) =>
        InvitationDetailsPage(token: state.pathParameters['token']!),
  ),
  StatefulShellRoute.indexedStack(
    builder: (context, state, navigationShell) => WorkspaceShell(
      navigationShell: navigationShell,
      destinations: _businessDestinations,
    ),
    branches: <StatefulShellBranch>[
      StatefulShellBranch(
        routes: <RouteBase>[
          GoRoute(
            path: AppRoutes.businessDashboard,
            builder: (context, state) => const BusinessDashboardPage(),
            routes: _businessManagementRoutes(),
          ),
        ],
      ),
      _branch(AppRoutes.businessQueues, (_) => const BusinessQueuesPage()),
      _branch(
        AppRoutes.businessAccount,
        (_) => const AccountPage(workspace: AppWorkspace.business),
      ),
    ],
  ),
];

/// Screens of one business, nested under the dashboard tab:
/// /business/dashboard/:businessId/...
List<RouteBase> _businessManagementRoutes() {
  String business(GoRouterState state) => state.pathParameters['businessId']!;
  String branch(GoRouterState state) => state.pathParameters['branchId']!;

  return <RouteBase>[
    GoRoute(
      path: ':businessId',
      builder: (context, state) =>
          BusinessOverviewPage(businessId: business(state)),
      routes: <RouteBase>[
        GoRoute(
          path: 'edit',
          builder: (context, state) =>
              EditBusinessPage(businessId: business(state)),
        ),
        GoRoute(
          path: 'branches',
          builder: (context, state) =>
              BranchListPage(businessId: business(state)),
          routes: <RouteBase>[
            // Before ':branchId': "new" is never a branch id (ULIDs).
            GoRoute(
              path: 'new',
              builder: (context, state) =>
                  CreateBranchPage(businessId: business(state)),
            ),
            GoRoute(
              path: ':branchId',
              builder: (context, state) => BranchDetailsPage(
                businessId: business(state),
                branchId: branch(state),
              ),
              routes: <RouteBase>[
                GoRoute(
                  path: 'edit',
                  builder: (context, state) => EditBranchPage(
                    businessId: business(state),
                    branchId: branch(state),
                  ),
                ),
                GoRoute(
                  path: 'services',
                  builder: (context, state) => ServicesListPage(
                    businessId: business(state),
                    branchId: branch(state),
                  ),
                  routes: <RouteBase>[
                    GoRoute(
                      path: 'new',
                      builder: (context, state) => CreateServicePage(
                        businessId: business(state),
                        branchId: branch(state),
                      ),
                    ),
                    GoRoute(
                      path: ':serviceId/edit',
                      builder: (context, state) => EditServicePage(
                        businessId: business(state),
                        branchId: branch(state),
                        serviceId: state.pathParameters['serviceId']!,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: 'team',
          builder: (context, state) =>
              TeamMembersPage(businessId: business(state)),
          routes: <RouteBase>[
            GoRoute(
              path: 'invite',
              builder: (context, state) =>
                  InviteMemberPage(businessId: business(state)),
            ),
          ],
        ),
      ],
    ),
  ];
}

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
