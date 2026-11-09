import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:flutter/foundation.dart';

/// A ticket as its holder sees it. It says nothing about other customers.
@immutable
class CustomerTicket {
  const CustomerTicket({
    required this.id,
    required this.number,
    required this.status,
    required this.businessDate,
    required this.queueId,
    required this.queueName,
    required this.sessionStatus,
    required this.businessName,
    required this.branchName,
    required this.joinedAt,
    this.businessSlug,
    this.businessLogoUrl,
    this.branchId,
    this.branchCity,
    this.branchAddress,
    this.serviceName,
    this.position,
    this.peopleAhead,
    this.estimatedMinutes,
    this.waitRangeLowMinutes,
    this.waitRangeHighMinutes,
    this.nowServing = const <String>[],
    this.arriveBy,
    this.canCancel = false,
    this.canReview = false,
    this.myRating,
    this.cancelReason,
    this.calledAt,
    this.startedAt,
    this.completedAt,
    this.endedAt,
    this.realtimeChannels = const <String>[],
  });

  final String id;
  final String number;
  final TicketStatus status;

  /// The branch's business day, "YYYY-MM-DD".
  final String businessDate;
  final String queueId;
  final String queueName;
  final QueueSessionStatus sessionStatus;
  final String businessName;
  final String? businessSlug;
  final String? businessLogoUrl;
  final String branchName;
  final String? branchId;
  final String? branchCity;
  final String? branchAddress;
  final String? serviceName;

  /// Null unless waiting.
  final int? position;
  final int? peopleAhead;
  final int? estimatedMinutes;

  /// Likely range of the wait, from recent service times (smart estimate).
  final int? waitRangeLowMinutes;
  final int? waitRangeHighMinutes;

  /// Ticket numbers being served: numbers only, never people.
  final List<String> nowServing;

  /// While called: when the grace period to reach the counter ends.
  final DateTime? arriveBy;
  final bool canCancel;
  final bool canReview;
  final int? myRating;
  final String? cancelReason;
  final DateTime joinedAt;
  final DateTime? calledAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime? endedAt;

  /// Reverb channels that announce changes to this ticket
  /// (docs/BROADCAST_CHANNELS.md): the session's public signal, plus the
  /// private ticket channel for signed-in customers. Announcements only:
  /// the ticket itself is always read from the API.
  final List<String> realtimeChannels;

  /// Nothing more will happen to this ticket.
  bool get isFinished => !status.isActive;
}

/// The result of joining as a guest. The token exists only here and in
/// secure storage: it is not part of [CustomerTicket], so it cannot reach a
/// list, a screen or a log by accident.
@immutable
class GuestJoinResult {
  const GuestJoinResult({required this.ticket, required this.accessToken});

  final CustomerTicket ticket;
  final String accessToken;

  @override
  String toString() => 'GuestJoinResult(${ticket.id})';
}

/// What a guest types to join, limited to what the queue requires.
@immutable
class GuestDetails {
  const GuestDetails({this.name, this.phone, this.consent = false});

  final String? name;
  final String? phone;

  /// How the phone number is used was accepted.
  final bool consent;
}

/// Which tickets to load, matching the API's filters.
enum TicketFilter {
  active('group', 'active'),
  waiting('status', 'waiting'),
  called('status', 'called'),
  serving('status', 'serving'),
  completed('status', 'completed'),
  cancelled('status', 'cancelled'),
  history('group', 'history');

  const TicketFilter(this.parameter, this.value);

  final String parameter;
  final String value;

  bool matches(TicketStatus status) => switch (this) {
    TicketFilter.active => status.isActive,
    TicketFilter.waiting => status == TicketStatus.waiting,
    TicketFilter.called => status == TicketStatus.called,
    TicketFilter.serving => status == TicketStatus.serving,
    TicketFilter.completed => status == TicketStatus.completed,
    TicketFilter.cancelled => status == TicketStatus.cancelled,
    TicketFilter.history => !status.isActive,
  };
}

/// Identifies a ticket to open: a guest ticket is read with the token stored
/// on this device, a customer's with the session.
@immutable
class TicketRef {
  const TicketRef(this.id, {this.isGuest = false});

  final String id;
  final bool isGuest;

  @override
  bool operator ==(Object other) =>
      other is TicketRef && other.id == id && other.isGuest == isGuest;

  @override
  int get hashCode => Object.hash(id, isGuest);
}
