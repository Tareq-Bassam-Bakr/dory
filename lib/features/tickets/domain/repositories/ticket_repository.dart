import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';

/// A customer's tickets, through the Phase 04 queue engine
/// (docs/QUEUE_API_REFERENCE.md). Every method throws [AppFailure].
abstract interface class TicketRepository {
  /// Joins as the signed-in customer. The same [idempotencyKey] returns the
  /// same ticket, so a retry can never create a second one.
  Future<CustomerTicket> join(String queueId, {required String idempotencyKey});

  /// Joins as a guest, by the queue's public id.
  Future<GuestJoinResult> joinAsGuest(
    String queueId,
    GuestDetails details, {
    required String idempotencyKey,
  });

  Future<PageResult<CustomerTicket>> myTickets(
    TicketFilter filter, {
    int page = 1,
  });

  Future<CustomerTicket> ticket(String ticketId);

  Future<CustomerTicket> cancel(String ticketId);

  /// Rates a completed ticket from 1 to 5, once.
  Future<CustomerTicket> review(String ticketId, int rating);

  /// Reads a guest ticket. [accessToken] travels only in the X-Ticket-Token
  /// header, as the API requires.
  Future<CustomerTicket> guestTicket(String ticketId, String accessToken);

  Future<CustomerTicket> cancelGuestTicket(String ticketId, String accessToken);
}
