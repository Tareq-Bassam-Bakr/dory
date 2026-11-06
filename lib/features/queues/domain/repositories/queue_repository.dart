import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';

/// Queues, sessions, staff and tickets of the business side
/// (docs/QUEUE_API_REFERENCE.md). Every method throws [AppFailure].
abstract interface class QueueRepository {
  /// Every queue of the business the user may reach.
  Future<List<QueueConfig>> businessQueues(String businessId);

  Future<QueueConfig> queue(String queueId);

  Future<QueueConfig> create(String branchId, QueueDraft draft);

  Future<QueueConfig> update(String queueId, QueueDraft draft);

  /// Soft delete; refused while a session is open or paused.
  Future<void> delete(String queueId);

  Future<QueueSession> open(String queueId);

  Future<QueueSession> pause(String queueId);

  Future<QueueSession> resume(String queueId);

  Future<QueueSession> close(String queueId);

  /// Newest first.
  Future<List<QueueSession>> sessions(String queueId);

  Future<QueuePublicLink> publicLink(String queueId);

  /// The previous link and QR code stop working.
  Future<QueuePublicLink> rotatePublicLink(String queueId);

  Future<List<QueueStaffAssignment>> staff(String queueId);

  Future<QueueStaffAssignment> assign(String queueId, String memberId);

  Future<void> unassign(String queueId, String assignmentId);

  Future<QueueDashboard> dashboard(String sessionId);

  /// Tickets of a session; [statuses] empty for all.
  Future<List<StaffTicket>> sessionTickets(
    String sessionId,
    List<TicketStatus> statuses,
  );

  Future<StaffTicket> ticket(String ticketId);

  Future<StaffTicket> callNext(String sessionId);

  Future<StaffTicket> act(String ticketId, TicketAction action);
}
