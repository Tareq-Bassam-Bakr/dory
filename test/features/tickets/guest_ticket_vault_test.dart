import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/tickets/data/api_ticket_repository.dart';
import 'package:dory_app/features/tickets/data/guest_ticket_vault.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:flutter_test/flutter_test.dart';

import '../customer/customer_fixtures.dart';

/// A secure store that fails, as a corrupted Android keystore does.
class _BrokenSecureStore extends InMemorySecureStore {
  @override
  Future<String?> read(String key) async => throw Exception('Keystore reset');
}

void main() {
  late InMemoryKeyValueStorage storage;
  late InMemorySecureStore secure;
  late GuestTicketVault vault;

  CustomerTicket ticket({
    String id = customerTicketId,
    String number = 'BAR-024',
    String status = 'waiting',
  }) => ApiTicketRepository.ticketFromJson(
    customerTicketJson(id: id, number: number, status: status),
  );

  setUp(() {
    storage = InMemoryKeyValueStorage();
    secure = InMemorySecureStore();
    vault = GuestTicketVault(storage, secure);
  });

  test('a saved ticket survives an app restart', () async {
    await vault.save(ticket(), guestAccessToken);

    // A new vault on the same stores is what the next launch sees.
    final GuestTicketVault restarted = GuestTicketVault(storage, secure);

    expect(restarted.list().single.number, 'BAR-024');
    expect(restarted.list().single.businessName, 'Shifa Clinic');
    expect(await restarted.tokenFor(customerTicketId), guestAccessToken);
  });

  test('the token is only ever in secure storage', () async {
    await vault.save(ticket(), guestAccessToken);

    final String stored = storage.readString(StorageKeys.guestTickets)!;

    expect(stored, isNot(contains(guestAccessToken)));
    expect(stored, isNot(contains('token')));
    expect(secure.values.values, contains(guestAccessToken));
  });

  test('stored details hold no personal data', () async {
    await vault.save(ticket(), guestAccessToken);

    expect(vault.list().single.toJson().keys, <String>[
      'id',
      'number',
      'status',
      'business',
      'branch',
      'service',
      'joined_at',
    ]);
  });

  test('several guest tickets are kept, newest first', () async {
    await vault.save(ticket(), guestAccessToken);
    await vault.save(ticket(id: 'second', number: 'BAR-025'), 'second-token');

    expect(vault.list().map((item) => item.id), <String>[
      'second',
      customerTicketId,
    ]);
    expect(await vault.tokenFor('second'), 'second-token');
    expect(await vault.tokenFor(customerTicketId), guestAccessToken);
  });

  test('a newer token for the same ticket replaces the old one', () async {
    await vault.save(ticket(), 'first-token');
    await vault.save(ticket(), 'rotated-token');

    expect(vault.list(), hasLength(1));
    expect(await vault.tokenFor(customerTicketId), 'rotated-token');
  });

  test('updating keeps the token and refreshes the details', () async {
    await vault.save(ticket(), guestAccessToken);

    await vault.update(ticket(status: 'completed'));

    expect(vault.list().single.status, TicketStatus.completed);
    expect(await vault.tokenFor(customerTicketId), guestAccessToken);
  });

  test('removing deletes the details and the token', () async {
    await vault.save(ticket(), guestAccessToken);

    await vault.remove(customerTicketId);

    expect(vault.list(), isEmpty);
    expect(await vault.tokenFor(customerTicketId), isNull);
    expect(secure.values, isEmpty);
  });

  test('a ticket whose token is gone stays listed', () async {
    await vault.save(ticket(), guestAccessToken);
    secure.values.clear();

    expect(vault.list(), hasLength(1));
    expect(await vault.tokenFor(customerTicketId), isNull);
  });

  test('a secure store that fails reads as a missing token', () async {
    final GuestTicketVault broken = GuestTicketVault(
      storage,
      _BrokenSecureStore(),
    );

    expect(await broken.tokenFor(customerTicketId), isNull);
  });

  test('a corrupted list is treated as empty', () async {
    await storage.writeString(StorageKeys.guestTickets, '{not json');
    expect(vault.list(), isEmpty);

    await storage.writeString(StorageKeys.guestTickets, '{"a": 1}');
    expect(vault.list(), isEmpty);
  });

  test('a join result never prints its token', () {
    final GuestJoinResult result = GuestJoinResult(
      ticket: ticket(),
      accessToken: guestAccessToken,
    );

    expect(result.toString(), isNot(contains(guestAccessToken)));
  });
}
