import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/data/datasources/auth_local_data_source.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_failure.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_session.dart';
import 'package:dory_app/features/authentication/domain/entities/registration_details.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_providers.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../helpers/api_fixtures.dart';
import '../../../helpers/fake_http_adapter.dart';
import '../../../helpers/test_app.dart';

/// The real repository, API client, interceptors and secure storage
/// adapter, with only the HTTP transport faked.
void main() {
  late FakeHttpAdapter http;
  late InMemorySecureStore secure;
  late ProviderContainer container;
  late AuthRepository repository;

  setUp(() {
    http = FakeHttpAdapter();
    secure = InMemorySecureStore();
    container = ProviderContainer.test(
      overrides: [
        keyValueStorageProvider.overrideWithValue(InMemoryKeyValueStorage()),
        secureStoreProvider.overrideWithValue(secure),
        apiBaseUrlProvider.overrideWithValue(testApiBaseUrl),
        httpClientAdapterProvider.overrideWithValue(http),
      ],
    );
    repository = container.read(authRepositoryProvider);
  });

  String? storedToken() => secure.values[AuthLocalDataSource.tokenKey];

  Future<AuthFailure> failureOf(Future<Object?> Function() action) async {
    try {
      await action();
    } on AuthFailure catch (failure) {
      return failure;
    }
    fail('Expected an AuthFailure.');
  }

  group('login', () {
    test(
      'signs in, stores the token securely and uses it afterwards',
      () async {
        http.reply(
          'POST',
          '/auth/login',
          FakeResponse.ok(
            authPayloadJson(user: userJson(memberships: [membershipJson()])),
          ),
        );
        http.reply('GET', '/auth/me', FakeResponse.ok(userJson()));

        final AuthSession session = await repository.login(
          email: 'lina@dory.test',
          password: 'Correct1Horse',
        );

        expect(session.user.email, 'lina@dory.test');
        expect(session.user.memberships.single.role, BusinessRole.owner);
        expect(session.expiresAt, DateTime.utc(2099));
        expect(storedToken(), '1|dory_testtoken');
        expect(
          container.read(sessionTokenHolderProvider).token,
          '1|dory_testtoken',
        );

        final Map<String, Object?> body =
            http.sent('POST', '/auth/login').single.data
                as Map<String, Object?>;
        expect(body['email'], 'lina@dory.test');
        expect(body['password'], 'Correct1Horse');
        expect(body['device_name'], isNotEmpty);
        expect(
          http.sent('POST', '/auth/login').single.headers['Authorization'],
          isNull,
        );

        await repository.currentUser();
        expect(
          http.sent('GET', '/auth/me').single.headers['Authorization'],
          'Bearer 1|dory_testtoken',
        );
      },
    );

    test('wrong credentials fail without storing anything', () async {
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(401, 'invalid_credentials'),
      );

      final AuthFailure failure = await failureOf(
        () => repository.login(email: 'lina@dory.test', password: 'wrong'),
      );

      expect(failure.reason, AuthFailureReason.invalidCredentials);
      expect(secure.values, isEmpty);
      expect(container.read(sessionTokenHolderProvider).token, isNull);
    });

    test('a suspended account is reported as inactive', () async {
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(403, 'account_inactive'),
      );

      final AuthFailure failure = await failureOf(
        () => repository.login(email: 'lina@dory.test', password: 'x'),
      );

      expect(failure.reason, AuthFailureReason.accountInactive);
    });

    test('rate limiting and connectivity problems are distinguished', () async {
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(429, 'too_many_requests'),
      );
      expect(
        (await failureOf(
          () => repository.login(email: 'a@b.co', password: 'x'),
        )).reason,
        AuthFailureReason.rateLimited,
      );

      http.reply('POST', '/auth/login', FakeResponse.offline);
      expect(
        (await failureOf(
          () => repository.login(email: 'a@b.co', password: 'x'),
        )).reason,
        AuthFailureReason.network,
      );

      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(500, 'server_error'),
      );
      expect(
        (await failureOf(
          () => repository.login(email: 'a@b.co', password: 'x'),
        )).reason,
        AuthFailureReason.server,
      );
    });

    test('failures never carry the password or token in their text', () async {
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.error(401, 'invalid_credentials'),
      );

      final AuthFailure failure = await failureOf(
        () => repository.login(
          email: 'lina@dory.test',
          password: 'Sup3rSecretPw',
        ),
      );

      expect(failure.toString(), isNot(contains('Sup3rSecretPw')));
    });
  });

  group('register', () {
    const RegistrationDetails details = RegistrationDetails(
      name: 'Lina Haddad',
      email: 'lina@dory.test',
      phone: '+963912345678',
      password: 'Correct1Horse',
      passwordConfirmation: 'Correct1Horse',
      locale: 'ar',
    );

    test('creates the account and signs in', () async {
      http.reply(
        'POST',
        '/auth/register',
        FakeResponse.ok(authPayloadJson(), status: 201),
      );

      final AuthSession session = await repository.register(details);

      expect(session.user.name, 'Lina Haddad');
      expect(storedToken(), '1|dory_testtoken');
      final Map<String, Object?> body =
          http.sent('POST', '/auth/register').single.data
              as Map<String, Object?>;
      expect(body, containsPair('password_confirmation', 'Correct1Horse'));
      expect(body, containsPair('phone', '+963912345678'));
      expect(body, containsPair('locale', 'ar'));
      expect(body.containsKey('is_platform_admin'), isFalse);
    });

    test('server validation errors are returned per field', () async {
      http.reply(
        'POST',
        '/auth/register',
        FakeResponse.error(
          422,
          'validation_failed',
          errors: <String, List<String>>{
            'email': <String>['The email has already been taken.'],
            'password': <String>['The password is too weak.'],
          },
        ),
      );

      final AuthFailure failure = await failureOf(
        () => repository.register(details),
      );

      expect(failure.reason, AuthFailureReason.validation);
      expect(failure.fieldErrors['email'], 'The email has already been taken.');
      expect(failure.fieldErrors['password'], 'The password is too weak.');
      expect(secure.values, isEmpty);
    });
  });

  group('session restoration', () {
    Future<void> storeSession({
      String? expiresAt = '2099-01-01T00:00:00Z',
    }) async {
      http.reply(
        'POST',
        '/auth/login',
        FakeResponse.ok(authPayloadJson(expiresAt: expiresAt)),
      );
      await repository.login(
        email: 'lina@dory.test',
        password: 'Correct1Horse',
      );
      container.read(sessionTokenHolderProvider).token =
          null; // a fresh app start
      http.requests.clear();
    }

    test('without a stored token there is nothing to restore', () async {
      expect(await repository.restoreSession(), isNull);
      expect(http.requests, isEmpty);
    });

    test('a stored token is verified with the server', () async {
      await storeSession();
      http.reply('GET', '/auth/me', FakeResponse.ok(userJson(name: 'Lina H.')));

      final AuthSession? session = await repository.restoreSession();

      expect(session?.user.name, 'Lina H.');
      expect(session?.isStale, isFalse);
      expect(
        http.sent('GET', '/auth/me').single.headers['Authorization'],
        'Bearer 1|dory_testtoken',
      );
    });

    test('an expired token is discarded without calling the server', () async {
      await storeSession(expiresAt: '2020-01-01T00:00:00Z');

      expect(await repository.restoreSession(), isNull);
      expect(http.requests, isEmpty);
      expect(storedToken(), isNull);
    });

    test('a token the server rejects is discarded', () async {
      await storeSession();
      http.reply('GET', '/auth/me', FakeResponse.error(401, 'unauthenticated'));

      expect(await repository.restoreSession(), isNull);
      expect(secure.values, isEmpty);
      expect(container.read(sessionTokenHolderProvider).token, isNull);
    });

    test('offline, the last known user is restored as stale', () async {
      await storeSession();
      http.reply('GET', '/auth/me', FakeResponse.offline);

      final AuthSession? session = await repository.restoreSession();

      expect(session?.isStale, isTrue);
      expect(session?.user.email, 'lina@dory.test');
      expect(storedToken(), '1|dory_testtoken');
    });
  });

  group('logout', () {
    test('revokes the token on the server and forgets it', () async {
      http.reply('POST', '/auth/login', FakeResponse.ok(authPayloadJson()));
      http.reply('POST', '/auth/logout', FakeResponse.ok(null));
      await repository.login(
        email: 'lina@dory.test',
        password: 'Correct1Horse',
      );

      await repository.logout();

      expect(
        http.sent('POST', '/auth/logout').single.headers['Authorization'],
        'Bearer 1|dory_testtoken',
      );
      expect(secure.values, isEmpty);
      expect(container.read(sessionTokenHolderProvider).token, isNull);
    });

    test(
      'forgets the session even when the server cannot be reached',
      () async {
        http.reply('POST', '/auth/login', FakeResponse.ok(authPayloadJson()));
        http.reply('POST', '/auth/logout', FakeResponse.offline);
        await repository.login(
          email: 'lina@dory.test',
          password: 'Correct1Horse',
        );

        await repository.logout();

        expect(secure.values, isEmpty);
      },
    );
  });
}
