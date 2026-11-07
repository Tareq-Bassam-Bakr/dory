import 'dart:convert';

import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:flutter/foundation.dart';

/// What the device remembers about a guest ticket to list it offline. It
/// holds no token, name or phone number.
@immutable
class StoredGuestTicket {
  const StoredGuestTicket({
    required this.id,
    required this.number,
    required this.status,
    required this.businessName,
    required this.branchName,
    required this.joinedAt,
    this.serviceName,
  });

  factory StoredGuestTicket.fromTicket(CustomerTicket ticket) =>
      StoredGuestTicket(
        id: ticket.id,
        number: ticket.number,
        status: ticket.status,
        businessName: ticket.businessName,
        branchName: ticket.branchName,
        serviceName: ticket.serviceName,
        joinedAt: ticket.joinedAt,
      );

  factory StoredGuestTicket.fromJson(JsonMap json) => StoredGuestTicket(
    id: json.string('id'),
    number: json.string('number'),
    status: TicketStatus.fromApi(json.stringOrNull('status')),
    businessName: json.string('business'),
    branchName: json.string('branch'),
    serviceName: json.stringOrNull('service'),
    joinedAt: json.dateTimeOrNull('joined_at') ?? DateTime.now(),
  );

  final String id;
  final String number;

  /// As of the last time the ticket was read from the server.
  final TicketStatus status;
  final String businessName;
  final String branchName;
  final String? serviceName;
  final DateTime joinedAt;

  JsonMap toJson() => <String, Object?>{
    'id': id,
    'number': number,
    'status': status.apiValue,
    'business': businessName,
    'branch': branchName,
    'service': serviceName,
    'joined_at': joinedAt.toUtc().toIso8601String(),
  };
}

/// Guest tickets kept on this device (docs/GUEST_TICKET_PERSISTENCE.md).
///
/// Two stores on purpose. The list lives in [KeyValueStorage], so the app
/// can still show that a ticket exists when its token cannot be read. Tokens
/// live only in [SecureStore], one entry per ticket.
class GuestTicketVault {
  const GuestTicketVault(this._storage, this._secure);

  final KeyValueStorage _storage;
  final SecureStore _secure;

  static const String _tokenPrefix = 'dory.guest_ticket_token.';

  /// Newest first. An unreadable list is treated as empty, never as a crash.
  List<StoredGuestTicket> list() {
    final String? raw = _storage.readString(StorageKeys.guestTickets);
    if (raw == null) {
      return const <StoredGuestTicket>[];
    }
    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is! List) {
        return const <StoredGuestTicket>[];
      }
      return <StoredGuestTicket>[
        for (final Object? item in decoded)
          StoredGuestTicket.fromJson(asJsonMap(item)),
      ]..sort((a, b) => b.joinedAt.compareTo(a.joinedAt));
    } on FormatException catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Guest ticket list unreadable',
      );
      return const <StoredGuestTicket>[];
    }
  }

  /// Remembers a ticket and its token. A newer token for the same ticket
  /// replaces the old one (a retried join rotates it on the server).
  Future<void> save(CustomerTicket ticket, String accessToken) async {
    await _secure.write(_tokenKey(ticket.id), accessToken);
    await update(ticket);
  }

  /// Refreshes the stored details after reading the ticket from the server.
  Future<void> update(CustomerTicket ticket) => _write(<StoredGuestTicket>[
    StoredGuestTicket.fromTicket(ticket),
    for (final StoredGuestTicket stored in list())
      if (stored.id != ticket.id) stored,
  ]);

  /// The ticket's token, or null when it is gone or cannot be read (secure
  /// storage can be reset by the system, for example after a restore).
  Future<String?> tokenFor(String ticketId) async {
    try {
      return await _secure.read(_tokenKey(ticketId));
    } on Exception catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Guest ticket credentials unreadable',
      );
      return null;
    }
  }

  Future<void> remove(String ticketId) async {
    await _secure.delete(_tokenKey(ticketId));
    await _write(<StoredGuestTicket>[
      for (final StoredGuestTicket stored in list())
        if (stored.id != ticketId) stored,
    ]);
  }

  Future<void> _write(List<StoredGuestTicket> tickets) => _storage.writeString(
    StorageKeys.guestTickets,
    jsonEncode(<JsonMap>[
      for (final StoredGuestTicket ticket in tickets) ticket.toJson(),
    ]),
  );

  static String _tokenKey(String ticketId) => '$_tokenPrefix$ticketId';
}
