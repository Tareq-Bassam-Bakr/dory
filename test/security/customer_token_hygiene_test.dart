import 'dart:io';

import 'package:dory_app/core/network/api_exception.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guest tokens are the only credential a guest has. These checks read the
/// source, so a careless change fails here rather than in someone's logs.
void main() {
  Iterable<(String, String)> sources() sync* {
    for (final File file in Directory(
      'lib',
    ).listSync(recursive: true).whereType<File>()) {
      if (file.path.endsWith('.dart') && !file.path.contains('generated')) {
        yield (file.path.replaceAll(r'\', '/'), file.readAsStringSync());
      }
    }
  }

  test('nothing prints to the console', () {
    for (final (String path, String source) in sources()) {
      expect(source, isNot(contains('print(')), reason: path);
    }
  });

  test('no HTTP logging interceptor is installed', () {
    for (final (String path, String source) in sources()) {
      expect(source, isNot(contains('LogInterceptor')), reason: path);
    }
  });

  test('the guest token header is set only by the ticket repository', () {
    for (final (String path, String source) in sources()) {
      if (path.endsWith('features/tickets/data/api_ticket_repository.dart')) {
        continue;
      }
      // Documentation may name the header; code may not.
      final String code = source
          .split('\n')
          .where((line) => !line.trimLeft().startsWith('//'))
          .join('\n');
      expect(code, isNot(contains('X-Ticket-Token')), reason: path);
    }
  });

  test('the guest token is never sent as a query parameter', () {
    final String repository = File(
      'lib/features/tickets/data/api_ticket_repository.dart',
    ).readAsStringSync();

    expect(repository, isNot(contains("'token':")));
    expect(repository, isNot(contains("'access_token':")));
    expect(repository, isNot(contains('?token')));
  });

  test('tokens are written only through the vault', () {
    for (final (String path, String source) in sources()) {
      if (path.endsWith('features/tickets/data/guest_ticket_vault.dart')) {
        continue;
      }
      expect(source, isNot(contains('dory.guest_ticket_token')), reason: path);
    }
  });

  test('the storage allow-list holds no secret', () {
    final String keys = File(
      'lib/core/storage/storage_keys.dart',
    ).readAsStringSync();

    expect(keys, isNot(contains('token =')));
    expect(keys, isNot(contains('password')));
  });

  test('API errors never print a request or response body', () {
    const ApiException error = ApiException(
      ApiErrorKind.notFound,
      statusCode: 404,
      code: 'ticket_not_found',
      message: 'secret-looking text',
    );

    expect(error.toString(), isNot(contains('secret-looking text')));
  });
}
