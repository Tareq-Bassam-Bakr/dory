import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/router/auth_redirect.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_auth_repository.dart';

void main() {
  final Uri dashboard = Uri.parse(AppRoutes.businessDashboard);

  group('protected locations', () {
    test('wait on the splash while the session is restored', () {
      expect(
        authRedirect(const AuthRestoring(), dashboard),
        '/splash?from=%2Fbusiness%2Fdashboard',
      );
    });

    test('send signed-out users to login, remembering where they were', () {
      expect(
        authRedirect(
          const Unauthenticated(),
          Uri.parse('/business/dashboard/01jx/branches'),
        ),
        '/welcome/login?from=%2Fbusiness%2Fdashboard%2F01jx%2Fbranches',
      );
    });

    test('are shown to signed-in users', () {
      expect(authRedirect(Authenticated(testUser()), dashboard), isNull);
    });
  });

  group('signed-out pages', () {
    test('send signed-in users home', () {
      expect(
        authRedirect(Authenticated(testUser()), Uri.parse(AppRoutes.login)),
        AppRoutes.customerHome,
      );
      expect(
        authRedirect(
          Authenticated(testUser(owns: true)),
          Uri.parse(AppRoutes.welcome),
        ),
        AppRoutes.businessDashboard,
      );
    });

    test('continue to a safe ?from= after login', () {
      final Uri login = Uri.parse('/welcome/login?from=%2Finvitations%2Fabc');

      expect(
        authRedirect(Authenticated(testUser()), login),
        '/invitations/abc',
      );
    });

    test('are shown to signed-out users', () {
      expect(
        authRedirect(const Unauthenticated(), Uri.parse(AppRoutes.login)),
        isNull,
      );
      expect(
        authRedirect(
          const Unauthenticated(),
          Uri.parse(AppRoutes.customerHome),
        ),
        isNull,
      );
    });
  });

  group('safeReturnLocation', () {
    test('accepts only locations inside the app', () {
      expect(
        AppRoutes.safeReturnLocation('/business/dashboard'),
        '/business/dashboard',
      );
      for (final String unsafe in <String>[
        'https://evil.example',
        '//evil.example/path',
        'javascript:alert(1)',
        'business/dashboard',
        '/welcome/login',
        '/splash',
      ]) {
        expect(AppRoutes.safeReturnLocation(unsafe), isNull, reason: unsafe);
      }
    });
  });
}
