import 'package:flutter/foundation.dart';

/// Status of a queue session (docs/QUEUE_LIFECYCLE.md).
enum QueueSessionStatus {
  open,
  paused,
  closed;

  static QueueSessionStatus fromApi(String? value) =>
      values.firstWhere((status) => status.name == value, orElse: () => closed);

  bool get isActive => this != closed;
}

/// Ticket states (docs/TICKET_STATE_MACHINE.md).
enum TicketStatus {
  waiting('waiting'),
  called('called'),
  serving('serving'),
  completed('completed'),
  cancelled('cancelled'),
  skipped('skipped'),
  noShow('no_show'),
  expired('expired');

  const TicketStatus(this.apiValue);

  final String apiValue;

  static TicketStatus fromApi(String? value) => values.firstWhere(
    (status) => status.apiValue == value,
    orElse: () => expired,
  );

  bool get isActive => this == waiting || this == called || this == serving;
}

/// A reference to a branch or service inside queue data.
@immutable
class QueueRef {
  const QueueRef({required this.id, required this.name});

  final String id;
  final String name;
}

/// One working period of a queue (opened → closed).
@immutable
class QueueSession {
  const QueueSession({
    required this.id,
    required this.businessDate,
    required this.sessionNumber,
    required this.status,
    required this.openedAt,
    this.pausedAt,
    this.closedAt,
    this.closeReason,
    this.ticketsCount,
  });

  final String id;

  /// The branch's business day, "YYYY-MM-DD".
  final String businessDate;
  final int sessionNumber;
  final QueueSessionStatus status;
  final DateTime openedAt;
  final DateTime? pausedAt;
  final DateTime? closedAt;
  final String? closeReason;
  final int? ticketsCount;
}

/// A queue configuration: one per service of a branch.
@immutable
class QueueConfig {
  const QueueConfig({
    required this.id,
    required this.name,
    required this.businessId,
    required this.branch,
    required this.ticketPrefix,
    required this.isActive,
    required this.allowGuestJoin,
    required this.requireGuestName,
    required this.requireGuestPhone,
    required this.effectiveServiceMinutes,
    required this.gracePeriodSeconds,
    this.service,
    this.capacity,
    this.estimatedServiceMinutes,
    this.currentSession,
    this.canManage = false,
    this.canOperate = false,
  });

  final String id;
  final String name;
  final String businessId;
  final QueueRef branch;
  final QueueRef? service;
  final String ticketPrefix;
  final bool isActive;
  final int? capacity;
  final bool allowGuestJoin;
  final bool requireGuestName;
  final bool requireGuestPhone;

  /// Minutes per customer set on the queue (null: the service's duration).
  final int? estimatedServiceMinutes;
  final int effectiveServiceMinutes;
  final int gracePeriodSeconds;
  final QueueSession? currentSession;

  /// Configure, delete, assign staff, manage the public link.
  final bool canManage;

  /// Open/pause/resume/close and serve customers.
  final bool canOperate;

  QueueDraft toDraft() => QueueDraft(
    name: name,
    ticketPrefix: ticketPrefix,
    isActive: isActive,
    capacity: capacity,
    allowGuestJoin: allowGuestJoin,
    requireGuestName: requireGuestName,
    requireGuestPhone: requireGuestPhone,
    estimatedServiceMinutes: estimatedServiceMinutes,
    gracePeriodSeconds: gracePeriodSeconds,
  );
}

/// Editable queue configuration.
@immutable
class QueueDraft {
  const QueueDraft({
    required this.name,
    required this.ticketPrefix,
    this.serviceId,
    this.isActive = true,
    this.capacity,
    this.allowGuestJoin = true,
    this.requireGuestName = true,
    this.requireGuestPhone = false,
    this.estimatedServiceMinutes,
    this.gracePeriodSeconds = 120,
  });

  /// Only when creating.
  final String? serviceId;
  final String name;
  final String ticketPrefix;
  final bool isActive;
  final int? capacity;
  final bool allowGuestJoin;
  final bool requireGuestName;
  final bool requireGuestPhone;
  final int? estimatedServiceMinutes;
  final int gracePeriodSeconds;
}

/// The queue's public join link; the QR code encodes [qrPayload].
@immutable
class QueuePublicLink {
  const QueuePublicLink({
    required this.url,
    required this.qrPayload,
    required this.allowGuestJoin,
    this.rotatedAt,
  });

  final String url;
  final String qrPayload;
  final bool allowGuestJoin;
  final DateTime? rotatedAt;
}

/// One entry of a ticket's history.
@immutable
class TicketEvent {
  const TicketEvent({
    required this.type,
    required this.at,
    this.from,
    this.to,
    this.actorName,
    this.reason,
  });

  final String type;
  final DateTime at;
  final TicketStatus? from;
  final TicketStatus? to;
  final String? actorName;
  final String? reason;
}

/// A ticket as the business team sees it.
@immutable
class StaffTicket {
  const StaffTicket({
    required this.id,
    required this.number,
    required this.status,
    required this.isGuest,
    required this.joinedAt,
    required this.waitedSeconds,
    this.customerName,
    this.customerPhone,
    this.position,
    this.servedByName,
    this.servedByMe = false,
    this.recallCount = 0,
    this.cancelReason,
    this.serviceSeconds,
    this.noShowAllowedAt,
    this.calledAt,
    this.startedAt,
    this.endedAt,
    this.events = const <TicketEvent>[],
  });

  final String id;
  final String number;
  final TicketStatus status;
  final bool isGuest;
  final String? customerName;

  /// Masked in lists; in full on the details screen for operators.
  final String? customerPhone;
  final int? position;
  final String? servedByName;
  final bool servedByMe;
  final int recallCount;
  final String? cancelReason;
  final int waitedSeconds;
  final int? serviceSeconds;
  final DateTime? noShowAllowedAt;
  final DateTime joinedAt;
  final DateTime? calledAt;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final List<TicketEvent> events;
}

/// The live view of a session (refreshed by polling; no push yet).
@immutable
class QueueDashboard {
  const QueueDashboard({
    required this.session,
    required this.queueId,
    required this.queueName,
    required this.isCurrentSession,
    required this.counts,
    required this.nextUp,
    required this.inService,
    required this.estimatedWaitMinutes,
    required this.estimateFromHistory,
    required this.servers,
    this.myTicket,
    this.canManage = false,
    this.canOperate = false,
  });

  final QueueSession session;
  final String queueId;
  final String queueName;
  final bool isCurrentSession;
  final Map<TicketStatus, int> counts;
  final List<StaffTicket> nextUp;
  final List<StaffTicket> inService;
  final StaffTicket? myTicket;

  /// For someone joining now.
  final int estimatedWaitMinutes;
  final bool estimateFromHistory;
  final int servers;
  final bool canManage;
  final bool canOperate;

  int count(TicketStatus status) => counts[status] ?? 0;
}

/// A team member assigned to operate a queue.
@immutable
class QueueStaffAssignment {
  const QueueStaffAssignment({
    required this.id,
    required this.memberId,
    required this.name,
    required this.email,
    required this.role,
    required this.assignedAt,
    this.assignedBy,
  });

  final String id;

  /// Membership public id.
  final String memberId;
  final String name;
  final String email;
  final String role;
  final DateTime assignedAt;
  final String? assignedBy;
}

/// Staff actions on one ticket (POST /tickets/{id}/{action}).
enum TicketAction {
  call('call'),
  start('start'),
  complete('complete'),
  skip('skip'),
  noShow('no-show'),
  recall('recall');

  const TicketAction(this.path);

  final String path;
}
