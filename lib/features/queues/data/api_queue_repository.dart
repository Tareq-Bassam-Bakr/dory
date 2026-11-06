import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/domain/repositories/queue_repository.dart';

/// [QueueRepository] over the DORY API.
class ApiQueueRepository implements QueueRepository {
  const ApiQueueRepository(this._api);

  final ApiClient _api;

  static const Map<String, Object?> _all = <String, Object?>{'per_page': 100};

  // ----- JSON -----------------------------------------------------------

  static QueueRef _ref(JsonMap json) =>
      QueueRef(id: json.stringOrNull('id') ?? '', name: json.string('name'));

  static QueueSession sessionFromJson(JsonMap json) => QueueSession(
    id: json.string('id'),
    businessDate: json.string('business_date'),
    sessionNumber: json.integer('session_number'),
    status: QueueSessionStatus.fromApi(json.stringOrNull('status')),
    openedAt: json.dateTimeOrNull('opened_at') ?? DateTime.now(),
    pausedAt: json.dateTimeOrNull('paused_at'),
    closedAt: json.dateTimeOrNull('closed_at'),
    closeReason: json.stringOrNull('close_reason'),
    ticketsCount: json.integerOrNull('tickets_count'),
  );

  static QueueConfig queueFromJson(JsonMap json) {
    final JsonMap? service = json.objectOrNull('service');
    final JsonMap? session = json.objectOrNull('current_session');
    final JsonMap? permissions = json.objectOrNull('permissions');
    return QueueConfig(
      id: json.string('id'),
      name: json.string('name'),
      businessId: json.object('business').string('id'),
      branch: _ref(json.object('branch')),
      service: service == null ? null : _ref(service),
      ticketPrefix: json.string('ticket_prefix'),
      isActive: json.stringOrNull('status') == 'active',
      capacity: json.integerOrNull('capacity'),
      allowGuestJoin: json.boolean('allow_guest_join', fallback: true),
      requireGuestName: json.boolean('require_guest_name', fallback: true),
      requireGuestPhone: json.boolean('require_guest_phone'),
      estimatedServiceMinutes: json.integerOrNull('estimated_service_duration'),
      effectiveServiceMinutes:
          json.integerOrNull('effective_service_minutes') ?? 15,
      gracePeriodSeconds: json.integerOrNull('grace_period_seconds') ?? 120,
      currentSession: session == null ? null : sessionFromJson(session),
      canManage: permissions?.boolean('manage') ?? false,
      canOperate: permissions?.boolean('operate') ?? false,
    );
  }

  static StaffTicket ticketFromJson(JsonMap json) {
    final JsonMap customer = json.object('customer');
    final JsonMap? server = json.objectOrNull('served_by');
    return StaffTicket(
      id: json.string('id'),
      number: json.string('ticket_number'),
      status: TicketStatus.fromApi(json.stringOrNull('status')),
      isGuest: customer.stringOrNull('type') == 'guest',
      customerName: customer.stringOrNull('name'),
      customerPhone: customer.stringOrNull('phone'),
      position: json.integerOrNull('position'),
      servedByName: server?.stringOrNull('name'),
      servedByMe: server?.boolean('is_you') ?? false,
      recallCount: json.integerOrNull('recall_count') ?? 0,
      cancelReason: json.stringOrNull('cancel_reason'),
      waitedSeconds: json.integerOrNull('waited_seconds') ?? 0,
      serviceSeconds: json.integerOrNull('service_seconds'),
      noShowAllowedAt: json.dateTimeOrNull('no_show_allowed_at'),
      joinedAt: json.dateTimeOrNull('joined_at') ?? DateTime.now(),
      calledAt: json.dateTimeOrNull('called_at'),
      startedAt: json.dateTimeOrNull('started_at'),
      endedAt: json.dateTimeOrNull('ended_at'),
      events: <TicketEvent>[
        for (final JsonMap event in json.objectList('events'))
          TicketEvent(
            type: event.string('type'),
            at: event.dateTimeOrNull('at') ?? DateTime.now(),
            from: event.stringOrNull('from') == null
                ? null
                : TicketStatus.fromApi(event.stringOrNull('from')),
            to: event.stringOrNull('to') == null
                ? null
                : TicketStatus.fromApi(event.stringOrNull('to')),
            actorName: event.stringOrNull('actor'),
            reason: event.stringOrNull('reason'),
          ),
      ],
    );
  }

  static QueueDashboard dashboardFromJson(JsonMap json) {
    final JsonMap queue = json.object('queue');
    final JsonMap counts = json.object('counts');
    final JsonMap estimate = json.object('estimated_wait');
    final JsonMap? mine = json.objectOrNull('my_ticket');
    final JsonMap? permissions = json.objectOrNull('permissions');
    return QueueDashboard(
      session: sessionFromJson(json.object('session')),
      queueId: queue.string('id'),
      queueName: queue.string('name'),
      isCurrentSession: json.boolean('is_current_session'),
      counts: <TicketStatus, int>{
        for (final TicketStatus status in TicketStatus.values)
          status: counts.integerOrNull(status.apiValue) ?? 0,
      },
      nextUp: json.objectList('next_up').map(ticketFromJson).toList(),
      inService: json.objectList('in_service').map(ticketFromJson).toList(),
      myTicket: mine == null ? null : ticketFromJson(mine),
      estimatedWaitMinutes: estimate.integerOrNull('minutes') ?? 0,
      estimateFromHistory: estimate.stringOrNull('basis') == 'history',
      servers: estimate.integerOrNull('servers') ?? 1,
      canManage: permissions?.boolean('manage') ?? false,
      canOperate: permissions?.boolean('operate') ?? false,
    );
  }

  static QueuePublicLink linkFromJson(JsonMap json) => QueuePublicLink(
    url: json.string('url'),
    qrPayload: json.string('qr_payload'),
    allowGuestJoin: json.boolean('allow_guest_join', fallback: true),
    rotatedAt: json.dateTimeOrNull('rotated_at'),
  );

  static QueueStaffAssignment assignmentFromJson(JsonMap json) {
    final JsonMap member = json.object('member');
    return QueueStaffAssignment(
      id: json.string('id'),
      memberId: member.string('id'),
      name: member.string('name'),
      email: member.stringOrNull('email') ?? '',
      role: member.stringOrNull('role') ?? 'employee',
      assignedAt: json.dateTimeOrNull('assigned_at') ?? DateTime.now(),
      assignedBy: json.stringOrNull('assigned_by'),
    );
  }

  /// The configuration fields of [draft], in the API's names.
  static Map<String, Object?> draftBody(QueueDraft draft) => <String, Object?>{
    'name': draft.name.trim(),
    'ticket_prefix': draft.ticketPrefix.trim().toUpperCase(),
    'status': draft.isActive ? 'active' : 'inactive',
    'capacity': draft.capacity,
    'allow_guest_join': draft.allowGuestJoin,
    'require_guest_name': draft.requireGuestName,
    'require_guest_phone': draft.requireGuestPhone,
    'estimated_service_duration': draft.estimatedServiceMinutes,
    'grace_period_seconds': draft.gracePeriodSeconds,
  };

  // ----- Queues ---------------------------------------------------------

  @override
  Future<List<QueueConfig>> businessQueues(String businessId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/businesses/$businessId/queues',
          query: _all,
        );
        return envelope.dataList.map(queueFromJson).toList();
      });

  @override
  Future<QueueConfig> queue(String queueId) => AppFailure.guard(() async {
    return queueFromJson((await _api.get('/queues/$queueId')).dataObject);
  });

  @override
  Future<QueueConfig> create(String branchId, QueueDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/branches/$branchId/queues',
          body: <String, Object?>{
            'service': draft.serviceId,
            ...draftBody(draft),
          },
        );
        return queueFromJson(envelope.dataObject);
      });

  @override
  Future<QueueConfig> update(String queueId, QueueDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.put(
          '/queues/$queueId',
          body: draftBody(draft),
        );
        return queueFromJson(envelope.dataObject);
      });

  @override
  Future<void> delete(String queueId) => AppFailure.guard(() async {
    await _api.delete('/queues/$queueId');
  });

  // ----- Sessions -------------------------------------------------------

  Future<QueueSession> _sessionAction(String queueId, String action) =>
      AppFailure.guard(() async {
        return sessionFromJson(
          (await _api.post('/queues/$queueId/$action')).dataObject,
        );
      });

  @override
  Future<QueueSession> open(String queueId) => _sessionAction(queueId, 'open');

  @override
  Future<QueueSession> pause(String queueId) =>
      _sessionAction(queueId, 'pause');

  @override
  Future<QueueSession> resume(String queueId) =>
      _sessionAction(queueId, 'resume');

  @override
  Future<QueueSession> close(String queueId) =>
      _sessionAction(queueId, 'close');

  @override
  Future<List<QueueSession>> sessions(String queueId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/queues/$queueId/sessions',
          query: _all,
        );
        return envelope.dataList.map(sessionFromJson).toList();
      });

  // ----- Public link ----------------------------------------------------

  @override
  Future<QueuePublicLink> publicLink(String queueId) =>
      AppFailure.guard(() async {
        return linkFromJson(
          (await _api.get('/queues/$queueId/public-link')).dataObject,
        );
      });

  @override
  Future<QueuePublicLink> rotatePublicLink(String queueId) =>
      AppFailure.guard(() async {
        return linkFromJson(
          (await _api.post('/queues/$queueId/rotate-public-token')).dataObject,
        );
      });

  // ----- Staff ----------------------------------------------------------

  @override
  Future<List<QueueStaffAssignment>> staff(String queueId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get('/queues/$queueId/staff');
        return envelope.dataList.map(assignmentFromJson).toList();
      });

  @override
  Future<QueueStaffAssignment> assign(String queueId, String memberId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/queues/$queueId/staff',
          body: <String, Object?>{'member': memberId},
        );
        return assignmentFromJson(envelope.dataObject);
      });

  @override
  Future<void> unassign(String queueId, String assignmentId) =>
      AppFailure.guard(() async {
        await _api.delete('/queues/$queueId/staff/$assignmentId');
      });

  // ----- Serving --------------------------------------------------------

  @override
  Future<QueueDashboard> dashboard(String sessionId) =>
      AppFailure.guard(() async {
        return dashboardFromJson(
          (await _api.get('/queue-sessions/$sessionId/dashboard')).dataObject,
        );
      });

  @override
  Future<List<StaffTicket>> sessionTickets(
    String sessionId,
    List<TicketStatus> statuses,
  ) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/queue-sessions/$sessionId/tickets',
      query: <String, Object?>{
        ..._all,
        if (statuses.isNotEmpty)
          'status': statuses.map((status) => status.apiValue).join(','),
      },
    );
    return envelope.dataList.map(ticketFromJson).toList();
  });

  @override
  Future<StaffTicket> ticket(String ticketId) => AppFailure.guard(() async {
    return ticketFromJson((await _api.get('/tickets/$ticketId')).dataObject);
  });

  @override
  Future<StaffTicket> callNext(String sessionId) => AppFailure.guard(() async {
    return ticketFromJson(
      (await _api.post('/queue-sessions/$sessionId/call-next')).dataObject,
    );
  });

  @override
  Future<StaffTicket> act(String ticketId, TicketAction action) =>
      AppFailure.guard(() async {
        return ticketFromJson(
          (await _api.post('/tickets/$ticketId/${action.path}')).dataObject,
        );
      });
}
