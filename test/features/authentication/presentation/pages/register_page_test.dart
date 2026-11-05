import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../../../helpers/fake_auth_repository.dart';
import '../../../../helpers/test_app.dart';

void main() {
  Future<GoRouter> openRegister(
    WidgetTester tester,
    FakeAuthRepository repository,
  ) async {
    setTestWindowSize(tester, const Size(390, 1400));
    final ProviderContainer container = createTestContainer(
      authRepository: repository,
    );
    final GoRouter router = container.read(appRouterProvider);
    await pumpDoryApp(tester, container);
    await tester.pump();
    router.go(AppRoutes.register);
    await tester.pumpAndSettle();
    return router;
  }

  Future<void> fill(
    WidgetTester tester, {
    String name = 'Lina Haddad',
    String email = 'Lina@Dory.test',
    String phone = '',
    String password = 'Correct1Horse',
    String? confirmation,
  }) async {
    final Finder fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), name);
    await tester.enterText(fields.at(1), email);
    await tester.enterText(fields.at(2), phone);
    await tester.enterText(fields.at(3), password);
    await tester.enterText(fields.at(4), confirmation ?? password);
    await tester.tap(find.widgetWithText(FilledButton, 'Create account'));
    await tester.pumpAndSettle();
  }

  testWidgets('weak and mismatched passwords are rejected locally', (
    tester,
  ) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    await openRegister(tester, repository);

    await fill(tester, password: 'weakpass', confirmation: 'different');

    expect(
      find.text('The password does not meet the requirements below.'),
      findsOneWidget,
    );
    expect(find.text('The passwords do not match.'), findsOneWidget);
    expect(repository.lastRegistration, isNull);
  });

  testWidgets('an invalid optional phone is rejected locally', (tester) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    await openRegister(tester, repository);

    await fill(tester, phone: '0912 345 678');

    expect(
      find.text('Use the international format, e.g. +963912345678.'),
      findsOneWidget,
    );
    expect(repository.lastRegistration, isNull);
  });

  testWidgets('server field errors appear under the field', (tester) async {
    final FakeAuthRepository repository = FakeAuthRepository()
      ..nextFailure = const AuthFailure(
        AuthFailureReason.validation,
        fieldErrors: <String, String>{
          'email': 'The email has already been taken.',
        },
      );
    final GoRouter router = await openRegister(tester, repository);

    await fill(tester);

    expect(find.text('The email has already been taken.'), findsOneWidget);
    expect(find.text('Please correct the highlighted fields.'), findsOneWidget);
    expect(
      router.routerDelegate.currentConfiguration.uri.path,
      AppRoutes.register,
    );
  });

  testWidgets('a valid form creates the account and signs in', (tester) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    final GoRouter router = await openRegister(tester, repository);

    await fill(tester, phone: '+963 912-345-678');

    final RegistrationDetails details = repository.lastRegistration!;
    expect(details.name, 'Lina Haddad');
    expect(details.email, 'lina@dory.test');
    expect(details.phone, '+963912345678');
    expect(details.locale, 'en');
    expect(
      router.routerDelegate.currentConfiguration.uri.path,
      AppRoutes.customerHome,
    );
  });

  testWidgets('the phone number is optional', (tester) async {
    final FakeAuthRepository repository = FakeAuthRepository();
    await openRegister(tester, repository);

    await fill(tester);

    expect(repository.lastRegistration?.phone, isNull);
  });
}
