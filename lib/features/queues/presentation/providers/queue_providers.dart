import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/queues/data/api_queue_repository.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/domain/repositories/queue_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<QueueRepository> queueRepositoryProvider =
    Provider<QueueRepository>(
      (ref) => ApiQueueRepository(ref.watch(apiClientProvider)),
      name: 'queueRepositoryProvider',
    );

/// Every queue of a business the user may reach.
final businessQueuesProvider = FutureProvider.autoDispose
    .family<List<QueueConfig>, String>((ref, businessId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).businessQueues(businessId);
    }, name: 'businessQueuesProvider');

final queueProvider = FutureProvider.autoDispose.family<QueueConfig, String>((
  ref,
  queueId,
) {
  ref.watch(currentUserIdProvider);
  return ref.watch(queueRepositoryProvider).queue(queueId);
}, name: 'queueProvider');

final queueSessionsProvider = FutureProvider.autoDispose
    .family<List<QueueSession>, String>((ref, queueId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).sessions(queueId);
    }, name: 'queueSessionsProvider');

final queuePublicLinkProvider = FutureProvider.autoDispose
    .family<QueuePublicLink, String>((ref, queueId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).publicLink(queueId);
    }, name: 'queuePublicLinkProvider');

final queueStaffProvider = FutureProvider.autoDispose
    .family<List<QueueStaffAssignment>, String>((ref, queueId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).staff(queueId);
    }, name: 'queueStaffProvider');

final queueDashboardProvider = FutureProvider.autoDispose
    .family<QueueDashboard, String>((ref, sessionId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).dashboard(sessionId);
    }, name: 'queueDashboardProvider');

/// Tickets of a session in the given states (see [sessionTicketsKey]).
final sessionTicketsProvider = FutureProvider.autoDispose
    .family<List<StaffTicket>, ({String sessionId, String statuses})>((
      ref,
      query,
    ) {
      ref.watch(currentUserIdProvider);
      final List<TicketStatus> statuses = query.statuses.isEmpty
          ? const <TicketStatus>[]
          : query.statuses.split(',').map(TicketStatus.fromApi).toList();
      return ref
          .watch(queueRepositoryProvider)
          .sessionTickets(query.sessionId, statuses);
    }, name: 'sessionTicketsProvider');

/// The family key for [sessionTicketsProvider] (records with lists do not
/// compare by value, so statuses are joined into a string).
({String sessionId, String statuses}) sessionTicketsKey(
  String sessionId,
  List<TicketStatus> statuses,
) => (
  sessionId: sessionId,
  statuses: statuses.map((status) => status.apiValue).join(','),
);

final staffTicketProvider = FutureProvider.autoDispose
    .family<StaffTicket, String>((ref, ticketId) {
      ref.watch(currentUserIdProvider);
      return ref.watch(queueRepositoryProvider).ticket(ticketId);
    }, name: 'staffTicketProvider');

/// Queue mutations. Each refreshes what it changes; the live screens also
/// refresh on a timer, since there is no real-time push yet.
class QueueActions {
  const QueueActions(this._ref);

  final Ref _ref;

  QueueRepository get _repository => _ref.read(queueRepositoryProvider);

  Future<QueueConfig> create(String branchId, QueueDraft draft) async {
    final QueueConfig queue = await _repository.create(branchId, draft);
    _ref.invalidate(businessQueuesProvider);
    return queue;
  }

  Future<QueueConfig> update(String queueId, QueueDraft draft) async {
    final QueueConfig queue = await _repository.update(queueId, draft);
    _refreshQueue(queueId);
    return queue;
  }

  Future<void> delete(String queueId) async {
    await _repository.delete(queueId);
    _ref.invalidate(businessQueuesProvider);
  }

  Future<QueueSession> open(String queueId) =>
      _session(queueId, () => _repository.open(queueId));

  Future<QueueSession> pause(String queueId) =>
      _session(queueId, () => _repository.pause(queueId));

  Future<QueueSession> resume(String queueId) =>
      _session(queueId, () => _repository.resume(queueId));

  Future<QueueSession> close(String queueId) =>
      _session(queueId, () => _repository.close(queueId));

  Future<QueuePublicLink> rotatePublicLink(String queueId) async {
    final QueuePublicLink link = await _repository.rotatePublicLink(queueId);
    _ref.invalidate(queuePublicLinkProvider(queueId));
    return link;
  }

  Future<void> assign(String queueId, String memberId) async {
    await _repository.assign(queueId, memberId);
    _refreshStaff(queueId);
  }

  Future<void> unassign(String queueId, String assignmentId) async {
    await _repository.unassign(queueId, assignmentId);
    _refreshStaff(queueId);
  }

  Future<StaffTicket> callNext(String sessionId) async {
    final StaffTicket ticket = await _repository.callNext(sessionId);
    _refreshSession(sessionId, ticket.id);
    return ticket;
  }

  Future<StaffTicket> act(
    String sessionId,
    String ticketId,
    TicketAction action,
  ) async {
    final StaffTicket ticket = await _repository.act(ticketId, action);
    _refreshSession(sessionId, ticketId);
    return ticket;
  }

  Future<QueueSession> _session(
    String queueId,
    Future<QueueSession> Function() action,
  ) async {
    final QueueSession session = await action();
    _refreshQueue(queueId);
    _ref
      ..invalidate(queueSessionsProvider(queueId))
      ..invalidate(queueDashboardProvider(session.id))
      ..invalidate(sessionTicketsProvider);
    return session;
  }

  void _refreshQueue(String queueId) {
    _ref
      ..invalidate(queueProvider(queueId))
      ..invalidate(businessQueuesProvider);
  }

  void _refreshStaff(String queueId) {
    _ref
      ..invalidate(queueStaffProvider(queueId))
      ..invalidate(queueProvider(queueId))
      ..invalidate(queueDashboardProvider);
  }

  void _refreshSession(String sessionId, String ticketId) {
    _ref
      ..invalidate(queueDashboardProvider(sessionId))
      ..invalidate(sessionTicketsProvider)
      ..invalidate(staffTicketProvider(ticketId));
  }
}

final Provider<QueueActions> queueActionsProvider = Provider<QueueActions>(
  QueueActions.new,
  name: 'queueActionsProvider',
);
