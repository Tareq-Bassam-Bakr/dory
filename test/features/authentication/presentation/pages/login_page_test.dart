import 'dart:async';

import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/presentation/pages/login_page.dart';
import 'package:dory_app/features/home/presentation/pages/customer_home_page.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../../../helpers/fake_auth_repository.dart';
import '../../../../helpers/test_app.dart';

/// Holds login until the test releases it, to observe the loading state.
class _SlowAuthRepository extends FakeAuthRepository {
  final Completer<void> release = Completer<void>();

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    await release.future;
    return super.login(email: email, password: password);
  }
}

void main() {
  const Size phone = Size(390, 844);

  Future<GoRouter> openLogin(
    WidgetTester tester,
    FakeAuthRepository repository, {
    String location = AppRoutes.login,
  }) async {
    setTestWindowSize(tester, phone);
    final ProviderContainer container = createTestContainer(
      authRepository: repository,
    );
    final GoRouter router = container.read(appRouterProvider);
    await pumpDoryApp(tester, container);
    await tester.pump(); // session restore
    router.go(location);
    await tester.pumpAndSettle();
    return router;
  }

  Future<void> fillAndSubmit(
    WidgetTester tester, {
    required String email,
    required String password,
  }) async {
    await tester.enterText(find.byType(TextFormField).at(0), email);
    await tester.enterText(find.byType(TextFormField).at(1), password);
    await tester.tap(find.widgetWithText(FilledButton, 'Log in'));
  }

  String path(GoRouter router) =>
      router.routerDelegate.currentConfiguration.uri.path;

  testWidgets('empty fields are flagged without calling the API', (
    tester,
  ) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    await openLogin(tester, repository);

    await tester.tap(find.widgetWithText(FilledButton, 'Log in'));
    await tester.pumpAndSettle();

    expect(find.text('Enter your email address.'), findsOneWidget);
    expect(find.text('Enter your password.'), findsOneWidget);
    expect(repository.loginCalls, 0);
  });

  testWidgets('wrong credentials show an error and stay on login', (
    tester,
  ) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    final GoRouter router = await openLogin(tester, repository);

    await fillAndSubmit(tester, email: 'lina@dory.test', password: 'wrong');
    await tester.pumpAndSettle();

    expect(find.text('The email or password is incorrect.'), findsOneWidget);
    expect(path(router), AppRoutes.login);
  });

  testWidgets('connectivity problems are explained', (tester) async {
    final FakeAuthRepository repository = FakeAuthRepository()
      ..nextFailure = const AuthFailure(AuthFailureReason.network);
    await openLogin(tester, repository);

    await fillAndSubmit(tester, email: 'lina@dory.test', password: 'x');
    await tester.pumpAndSettle();

    expect(
      find.text('Cannot reach DORY. Check your connection and try again.'),
      findsOneWidget,
    );
  });

  testWidgets('a successful login opens the home workspace', (tester) async {
    final GoRouter router = await openLogin(tester, FakeAuthRepository());

    await fillAndSubmit(
      tester,
      email: FakeAuthRepository.validEmail,
      password: FakeAuthRepository.validPassword,
    );
    await tester.pumpAndSettle();

    expect(path(router), AppRoutes.customerHome);
    expect(find.byType(CustomerHomePage), findsOneWidget);
  });

  testWidgets('after login the user continues where they were going', (
    tester,
  ) async {
    final GoRouter router = await openLogin(
      tester,
      FakeAuthRepository(),
      location: AppRoutes.businessDashboard,
    );
    expect(path(router), AppRoutes.login, reason: 'Guests are sent to login.');

    await fillAndSubmit(
      tester,
      email: FakeAuthRepository.validEmail,
      password: FakeAuthRepository.validPassword,
    );
    await tester.pumpAndSettle();

    expect(path(router), AppRoutes.businessDashboard);
  });

  testWidgets('the button shows progress and ignores repeated taps', (
    tester,
  ) async {
    final _SlowAuthRepository repository = _SlowAuthRepository();
    await openLogin(tester, repository);

    await fillAndSubmit(
      tester,
      email: FakeAuthRepository.validEmail,
      password: FakeAuthRepository.validPassword,
    );
    await tester.pump();

    expect(
      find.descendant(
        of: find.byType(FilledButton),
        matching: find.byType(CircularProgressIndicator),
      ),
      findsOneWidget,
    );
    await tester.tap(find.byType(FilledButton), warnIfMissed: false);
    await tester.pump();

    repository.release.complete();
    await tester.pumpAndSettle();
    expect(repository.loginCalls, 1);
    expect(find.byType(LoginPage), findsNothing);
  });
}
