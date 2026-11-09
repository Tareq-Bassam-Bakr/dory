import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/domain/repositories/ticket_repository.dart';

/// [TicketRepository] over the DORY API.
class ApiTicketRepository implements TicketRepository {
  const ApiTicketRepository(this._api);

  final ApiClient _api;

  /// The header Phase 04's EnsureTicketAccessToken reads.
  static const String ticketTokenHeader = 'X-Ticket-Token';

  static const String _idempotencyHeader = 'Idempotency-Key';

  static CustomerTicket ticketFromJson(JsonMap json) {
    final JsonMap queue = json.object('queue');
    final JsonMap business = json.object('business');
    final JsonMap branch = json.object('branch');
    final JsonMap? estimate = json.objectOrNull('estimated_wait');
    final Object? serving = json['now_serving'];
    return CustomerTicket(
      id: json.string('id'),
      number: json.string('ticket_number'),
      status: TicketStatus.fromApi(json.stringOrNull('status')),
      businessDate: json.stringOrNull('business_date') ?? '',
      queueId: queue.string('id'),
      queueName: queue.string('name'),
      sessionStatus: QueueSessionStatus.fromApi(
        queue.stringOrNull('session_status'),
      ),
      businessName: business.string('name'),
      businessSlug: business.stringOrNull('slug'),
      businessLogoUrl: business.stringOrNull('logo_url'),
      branchName: branch.string('name'),
      branchId: branch.stringOrNull('id'),
      branchCity: branch.stringOrNull('city'),
      branchAddress: branch.stringOrNull('address'),
      serviceName: json.objectOrNull('service')?.stringOrNull('name'),
      position: json.integerOrNull('position'),
      peopleAhead: json.integerOrNull('people_ahead'),
      estimatedMinutes: estimate?.integerOrNull('minutes'),
      waitRangeLowMinutes: estimate
          ?.objectOrNull('range')
          ?.integerOrNull('low_minutes'),
      waitRangeHighMinutes: estimate
          ?.objectOrNull('range')
          ?.integerOrNull('high_minutes'),
      nowServing: serving is List
          ? serving.whereType<String>().toList()
          : const <String>[],
      arriveBy: json.dateTimeOrNull('arrive_by'),
      canCancel: json.boolean('can_cancel'),
      canReview: json.boolean('can_review'),
      myRating: json.integerOrNull('my_rating'),
      cancelReason: json.stringOrNull('cancel_reason'),
      joinedAt: json.dateTimeOrNull('joined_at') ?? DateTime.now(),
      calledAt: json.dateTimeOrNull('called_at'),
      startedAt: json.dateTimeOrNull('started_at'),
      completedAt: json.dateTimeOrNull('completed_at'),
      endedAt: json.dateTimeOrNull('ended_at'),
      realtimeChannels: _channels(json.objectOrNull('realtime')),
    );
  }

  /// Only the two channel kinds a ticket can have; anything else is ignored.
  static List<String> _channels(JsonMap? realtime) => <String>[
    for (final String? channel in <String?>[
      realtime?.stringOrNull('public_channel'),
      realtime?.stringOrNull('private_channel'),
    ])
      if (channel != null &&
          (channel.startsWith('queue-updates.') ||
              channel.startsWith('private-tickets.')))
        channel,
  ];

  static Map<String, String> _guestHeaders(String accessToken) =>
      <String, String>{ticketTokenHeader: accessToken};

  @override
  Future<CustomerTicket> join(
    String queueId, {
    required String idempotencyKey,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/queues/$queueId/join',
      headers: <String, String>{_idempotencyHeader: idempotencyKey},
    );
    return ticketFromJson(envelope.dataObject);
  });

  @override
  Future<GuestJoinResult> joinAsGuest(
    String queueId,
    GuestDetails details, {
    required String idempotencyKey,
  }) => AppFailure.guard(() async {
    final String? phone = details.phone?.trim();
    final bool hasPhone = phone != null && phone.isNotEmpty;
    final ApiEnvelope envelope = await _api.post(
      '/public/queues/$queueId/join',
      headers: <String, String>{_idempotencyHeader: idempotencyKey},
      body: <String, Object?>{
        if (details.name != null && details.name!.trim().isNotEmpty)
          'name': details.name!.trim(),
        if (hasPhone) ...<String, Object?>{
          'phone': phone,
          'consent': details.consent,
        },
      },
    );
    final JsonMap data = envelope.dataObject;
    return GuestJoinResult(
      ticket: ticketFromJson(data),
      accessToken: data.string('access_token'),
    );
  });

  @override
  Future<PageResult<CustomerTicket>> myTickets(
    TicketFilter filter, {
    int page = 1,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/me/tickets',
      query: <String, Object?>{
        filter.parameter: filter.value,
        'page': page,
        'per_page': 20,
      },
    );
    final List<CustomerTicket> items = envelope.dataList
        .map(ticketFromJson)
        .toList();
    return PageResult<CustomerTicket>(
      items: items,
      pagination:
          envelope.pagination ??
          Pagination(
            currentPage: 1,
            perPage: items.length,
            total: items.length,
            lastPage: 1,
          ),
    );
  });

  @override
  Future<CustomerTicket> ticket(String ticketId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/tickets/$ticketId/position');
    return ticketFromJson(envelope.dataObject);
  });

  @override
  Future<CustomerTicket> cancel(String ticketId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post('/tickets/$ticketId/cancel');
    return ticketFromJson(envelope.dataObject);
  });

  @override
  Future<CustomerTicket> review(String ticketId, int rating) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/tickets/$ticketId/review',
          body: <String, Object?>{'rating': rating},
        );
        return ticketFromJson(envelope.dataObject);
      });

  @override
  Future<CustomerTicket> guestTicket(String ticketId, String accessToken) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/public/tickets/$ticketId',
          headers: _guestHeaders(accessToken),
        );
        return ticketFromJson(envelope.dataObject);
      });

  @override
  Future<CustomerTicket> cancelGuestTicket(
    String ticketId,
    String accessToken,
  ) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/public/tickets/$ticketId/cancel',
      headers: _guestHeaders(accessToken),
    );
    return ticketFromJson(envelope.dataObject);
  });
}
