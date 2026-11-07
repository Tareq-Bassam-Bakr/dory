import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

/// The heart on a business; the Favorites tab has a heart icon of its own.
Finder savedHeart() => find.descendant(
  of: find.byType(IconButton),
  matching: find.byIcon(Icons.favorite_rounded),
);

void main() {
  group('favorites', () {
    testWidgets('signed-in customers save on the server', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'POST',
          '/me/favorites',
          FakeResponse.ok(publicBusinessJson(favorite: true), status: 201),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerBusiness(publicSlug),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.byIcon(Icons.favorite_outline_rounded));
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/me/favorites').single.data, <String, Object?>{
        'business': publicBusinessId,
      });
      expect(savedHeart(), findsOneWidget);
    });

    testWidgets('the saved list loads from the server and can shrink', (
      tester,
    ) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'DELETE',
          '/me/favorites/$publicBusinessId',
          FakeResponse.ok(null),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerFavorites,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );
      expect(find.text('Shifa Clinic'), findsOneWidget);

      await tester.tap(savedHeart());
      await tester.pumpAndSettle();

      expect(
        http.sent('DELETE', '/me/favorites/$publicBusinessId'),
        hasLength(1),
      );
      expect(find.text('Shifa Clinic'), findsNothing);
      expect(find.text('Nothing saved yet'), findsOneWidget);
    });

    testWidgets('a refused change leaves the heart as it was', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('POST', '/me/favorites', FakeResponse.offline);
      await pumpAppAt(
        tester,
        AppRoutes.customerBusiness(publicSlug),
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.byIcon(Icons.favorite_outline_rounded));
      await tester.pumpAndSettle();

      expect(find.byIcon(Icons.favorite_outline_rounded), findsOneWidget);
      expect(savedHeart(), findsNothing);
    });

    testWidgets('visitors save on this device, and are told so', (
      tester,
    ) async {
      final InMemoryKeyValueStorage storage = InMemoryKeyValueStorage();
      final FakeHttpAdapter http = customerApi();
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerBusiness(publicSlug),
        authRepository: FakeAuthRepository(),
        http: http,
        storage: storage,
      );

      await tester.tap(find.byIcon(Icons.favorite_outline_rounded));
      await tester.pumpAndSettle();

      expect(http.sent('POST', '/me/favorites'), isEmpty);
      expect(
        storage.readString(StorageKeys.localFavorites),
        contains(publicSlug),
      );

      router.go(AppRoutes.customerFavorites);
      await tester.pumpAndSettle();

      expect(find.textContaining('on this device only'), findsOneWidget);
      expect(find.text('Shifa Clinic'), findsOneWidget);

      await tester.tap(savedHeart());
      await tester.pumpAndSettle();
      expect(find.text('Shifa Clinic'), findsNothing);
    });
  });

  group('customer profile', () {
    final FakeAuthRepository withPhone = FakeAuthRepository(
      storedSession: const AuthSession(
        user: AuthUser(
          id: '01jtestuserxxxxxxxxxxxxxxx',
          name: 'Lina Haddad',
          email: 'lina@dory.test',
          phone: '+963933555666',
          locale: 'en',
          timezone: 'UTC',
        ),
      ),
    );

    testWidgets('shows the account and links to tickets and favorites', (
      tester,
    ) async {
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.customerAccount,
        authRepository: withPhone,
        http: customerApi(),
      );

      expect(find.text('Lina Haddad'), findsOneWidget);
      expect(find.text('lina@dory.test'), findsOneWidget);
      expect(find.text('+963933555666'), findsOneWidget);
      expect(find.text('Language'), findsOneWidget);

      await tester.tap(find.text('Saved businesses'));
      await tester.pumpAndSettle();
      expect(currentLocation(router), AppRoutes.customerFavorites);

      router.go(AppRoutes.customerAccount);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ticket history'));
      await tester.pumpAndSettle();
      expect(currentLocation(router), AppRoutes.customerTickets);
    });

    testWidgets('the name and phone can be edited', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply('PUT', '/users/profile', FakeResponse.ok(<String, Object?>{}));
      await pumpAppAt(
        tester,
        AppRoutes.customerAccount,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.byIcon(Icons.edit_outlined));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, 'Lina H');
      await tester.enterText(find.byType(TextFormField).last, '+963933555666');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(http.sent('PUT', '/users/profile').single.data, <String, Object?>{
        'name': 'Lina H',
        'phone': '+963933555666',
      });
    });

    testWidgets('a field refused by the server is shown on it', (tester) async {
      final FakeHttpAdapter http = customerApi()
        ..reply(
          'PUT',
          '/users/profile',
          FakeResponse.error(
            422,
            'validation_failed',
            errors: <String, List<String>>{
              'phone': <String>['This phone number is taken'],
            },
          ),
        );
      await pumpAppAt(
        tester,
        AppRoutes.customerProfileEdit,
        authRepository: FakeAuthRepository.signedIn(),
        http: http,
      );

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(find.text('This phone number is taken'), findsOneWidget);
    });

    testWidgets('visitors are invited to sign in, never forced', (
      tester,
    ) async {
      await pumpAppAt(
        tester,
        AppRoutes.customerAccount,
        authRepository: FakeAuthRepository(),
        http: customerApi(),
      );

      expect(find.textContaining('without one'), findsOneWidget);
      expect(find.text('Ticket history'), findsOneWidget);
    });
  });
}
