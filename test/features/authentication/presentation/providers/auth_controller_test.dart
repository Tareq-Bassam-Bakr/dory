import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_exception.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../../helpers/api_fixtures.dart';
import '../../../../helpers/fake_auth_repository.dart';
import '../../../../helpers/fake_http_adapter.dart';
import '../../../../helpers/test_app.dart';

void main() {
  group('with a fake repository', () {
    test('restores a stored session on start', () async {
      final ProviderContainer container = createTestContainer(
        authRepository: FakeAuthRepository.signedIn(),
      );

      expect(container.read(authControllerProvider), isA<AuthRestoring>());
      await container.read(authControllerProvider.notifier).ready;

      final AuthState state = container.read(authControllerProvider);
      expect(state, isA<Authenticated>());
      expect((state as Authenticated).user.email, 'lina@dory.test');
    });

    test('starts signed out without a stored session', () async {
      final ProviderContainer container = createTestContainer();
      await container.read(authControllerProvider.notifier).ready;

      expect(container.read(authControllerProvider), isA<Unauthenticated>());
    });

    test('login and logout change the session', () async {
      final FakeAuthRepository repository = FakeAuthRepository();
      final ProviderContainer container = createTestContainer(
        authRepository: repository,
      );
      final AuthController controller = container.read(
        authControllerProvider.notifier,
      );
      await controller.ready;

      await controller.login(
        email: FakeAuthRepository.validEmail,
        password: FakeAuthRepository.validPassword,
      );
      expect(container.read(authControllerProvider), isA<Authenticated>());

      await controller.logout();
      expect(container.read(authControllerProvider), isA<Unauthenticated>());
      expect(repository.logoutCalls, 1);
    });

    test('a failed login leaves the user signed out', () async {
      final ProviderContainer container = createTestContainer();
      final AuthController controller = container.read(
        authControllerProvider.notifier,
      );
      await controller.ready;

      await expectLater(
        controller.login(email: 'lina@dory.test', password: 'nope'),
        throwsA(isA<AuthFailure>()),
      );
      expect(container.read(authControllerProvider), isA<Unauthenticated>());
    });
  });

  group('with the real stack', () {
    test('a 401 on any authenticated request ends the session', () async {
      final FakeHttpAdapter http = FakeHttpAdapter();
      final InMemorySecureStore secure = InMemorySecureStore();
      final ProviderContainer container = ProviderContainer.test(
        overrides: [
          keyValueStorageProvider.overrideWithValue(InMemoryKeyValueStorage()),
          secureStoreProvider.overrideWithValue(secure),
          apiBaseUrlProvider.overrideWithValue(testApiBaseUrl),
          httpClientAdapterProvider.overrideWithValue(http),
        ],
      );
      http.reply('POST', '/auth/login', FakeResponse.ok(authPayloadJson()));
      final AuthController controller = container.read(
        authControllerProvider.notifier,
      );
      await controller.ready;
      await controller.login(
        email: 'lina@dory.test',
        password: 'Correct1Horse',
      );
      expect(secure.values, isNotEmpty);

      // The token is revoked elsewhere; the next API call is rejected.
      http.reply(
        'GET',
        '/businesses/manage',
        FakeResponse.error(401, 'unauthenticated'),
      );
      await expectLater(
        container.read(apiClientProvider).get('/businesses/manage'),
        throwsA(
          isA<ApiException>().having(
            (e) => e.kind,
            'kind',
            ApiErrorKind.unauthorized,
          ),
        ),
      );
      await pumpEventQueue();

      final AuthState state = container.read(authControllerProvider);
      expect(state, isA<Unauthenticated>());
      expect((state as Unauthenticated).sessionExpired, isTrue);
      expect(secure.values, isEmpty);
      expect(container.read(sessionTokenHolderProvider).token, isNull);
    });

    test('a wrong password does not end anything', () async {
      final FakeHttpAdapter http = FakeHttpAdapter();
      final ProviderContainer container = ProviderContainer.test(
        overrides: [
          keyValueStorageProvider.overrideWithValue(InMemoryKeyValueStorage()),
          secureStoreProvider.overrideWithValue(InMemorySecureStore()),
          apiBaseUrlProvider.overrideWithValue(testApiBaseUrl),
          httpClientAdapterProvider.overrideWithValue(http),
        ],
      );
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(401, 'invalid_credentials'),
      );
      final AuthController controller = container.read(
        authControllerProvider.notifier,
      );
      await controller.ready;

      await expectLater(
        controller.login(email: 'lina@dory.test', password: 'wrong'),
        throwsA(isA<AuthFailure>()),
      );
      await pumpEventQueue();

      final AuthState state = container.read(authControllerProvider);
      expect((state as Unauthenticated).sessionExpired, isFalse);
      expect(container.read(apiClientProvider), isA<ApiClient>());
    });
  });
}
