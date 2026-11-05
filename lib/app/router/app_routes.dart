import 'package:dory_app/shared/enums/app_workspace.dart';

/// Every route location in the app. Navigate with these, never with literals.
///
/// Features may import this file to navigate; only `app_router.dart` builds
/// the route tree.
abstract final class AppRoutes {
  static const String root = '/';
  static const String splash = '/splash';

  // Entry flow. Login and registration are children of welcome, so the back
  // button always leads to welcome, including after a deep link.
  static const String welcome = '/welcome';
  static const String loginSegment = 'login';
  static const String registerSegment = 'register';
  static const String login = '$welcome/$loginSegment';
  static const String register = '$welcome/$registerSegment';

  // Customer workspace.
  static const String customerRoot = '/customer';
  static const String customerHome = '$customerRoot/home';
  static const String customerTickets = '$customerRoot/tickets';
  static const String customerAccount = '$customerRoot/account';

  // Business workspace.
  static const String businessRoot = '/business';
  static const String businessDashboard = '$businessRoot/dashboard';
  static const String businessQueues = '$businessRoot/queues';
  static const String businessAccount = '$businessRoot/account';

  /// Landing location of a workspace.
  ///
  /// Throws for [AppWorkspace.administration], which has no mobile shell.
  static String workspaceHome(AppWorkspace workspace) => switch (workspace) {
    AppWorkspace.customer => customerHome,
    AppWorkspace.business => businessDashboard,
    AppWorkspace.administration => throw UnsupportedError(
      'The administration workspace has no mobile shell.',
    ),
  };
}
