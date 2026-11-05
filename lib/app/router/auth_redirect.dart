import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';

/// Route guard: decides where a location may not be shown for the current
/// session, and where to send the user instead (null: show it).
///
/// - Protected locations (business workspace, invitations to accept):
///   while the session is being restored, wait on the splash; signed out,
///   go to login. Both remember the location in `?from=`.
/// - Welcome, login and registration are for signed-out users: a signed-in
///   user continues to `from` or to their home workspace.
///
/// This only shapes navigation. The API authorizes every request itself.
String? authRedirect(AuthState auth, Uri location) {
  final String path = location.path;

  if (AppRoutes.requiresAuthentication(path)) {
    return switch (auth) {
      AuthRestoring() => AppRoutes.withFrom(AppRoutes.splash, location),
      Unauthenticated() => AppRoutes.withFrom(AppRoutes.login, location),
      Authenticated() => null,
    };
  }

  if (auth is Authenticated && AppRoutes.isForSignedOutUsers(path)) {
    return AppRoutes.safeReturnLocation(location.queryParameters['from']) ??
        AppRoutes.homeFor(auth.user);
  }

  return null;
}
