import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
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

  // Business workspace. Requires a signed-in user.
  static const String businessRoot = '/business';
  static const String businessDashboard = '$businessRoot/dashboard';
  static const String businessQueues = '$businessRoot/queues';
  static const String businessAccount = '$businessRoot/account';

  // Business management, inside the dashboard tab.
  static const String businessCreate = '$businessRoot/create';
  static const String businessOnboarding = '$businessCreate/onboarding';
  static String business(String businessId) => '$businessDashboard/$businessId';
  static String businessEdit(String businessId) =>
      '${business(businessId)}/edit';
  static String branches(String businessId) =>
      '${business(businessId)}/branches';
  static String branchCreate(String businessId) =>
      '${branches(businessId)}/new';
  static String branch(String businessId, String branchId) =>
      '${branches(businessId)}/$branchId';
  static String branchEdit(String businessId, String branchId) =>
      '${branch(businessId, branchId)}/edit';
  static String services(String businessId, String branchId) =>
      '${branch(businessId, branchId)}/services';
  static String serviceCreate(String businessId, String branchId) =>
      '${services(businessId, branchId)}/new';
  static String serviceEdit(
    String businessId,
    String branchId,
    String serviceId,
  ) => '${services(businessId, branchId)}/$serviceId/edit';
  static String team(String businessId) => '${business(businessId)}/team';
  static String teamInvite(String businessId) => '${team(businessId)}/invite';

  // Invitations (deep link target: dory://app/invitations/<token>).
  static const String invitations = '/invitations';
  static String invitation(String token) => '$invitations/$token';

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

  /// Where a user lands after signing in: their businesses when they work
  /// in one, otherwise the customer home.
  static String homeFor(AuthUser user) => workspaceHome(
    user.hasActiveMemberships ? AppWorkspace.business : AppWorkspace.customer,
  );

  /// Locations that need a signed-in user.
  static bool requiresAuthentication(String path) =>
      _isWithin(path, businessRoot);

  /// Locations meant for signed-out users only.
  static bool isForSignedOutUsers(String path) =>
      path == welcome || path == login || path == register;

  /// [location] with [from] remembered as `?from=`.
  static String withFrom(String location, Uri from) => Uri(
    path: location,
    queryParameters: <String, String>{'from': from.toString()},
  ).toString();

  /// [from] if it is a location inside the app, otherwise null. Prevents
  /// `?from=` from sending users to other sites or schemes.
  static String? safeReturnLocation(String? from) {
    if (from == null || !from.startsWith('/') || from.startsWith('//')) {
      return null;
    }
    final Uri? uri = Uri.tryParse(from);
    if (uri == null || uri.hasScheme || uri.hasAuthority) {
      return null;
    }
    return isForSignedOutUsers(uri.path) || uri.path == splash ? null : from;
  }

  static bool _isWithin(String path, String root) =>
      path == root || path.startsWith('$root/');
}
