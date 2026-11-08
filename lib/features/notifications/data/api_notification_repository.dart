import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/notifications/domain/notification_entities.dart';
import 'package:dory_app/features/tickets/data/api_ticket_repository.dart';

/// [NotificationRepository] over the DORY API.
class ApiNotificationRepository implements NotificationRepository {
  const ApiNotificationRepository(this._api);

  final ApiClient _api;

  static AppNotification fromJson(JsonMap json) {
    final JsonMap? ticket = json.objectOrNull('ticket');
    return AppNotification(
      id: json.string('id'),
      kind: json.string('kind'),
      title: json.stringOrNull('title') ?? '',
      body: json.stringOrNull('body') ?? '',
      read: json.boolean('read'),
      createdAt: json.dateTimeOrNull('created_at') ?? DateTime.now(),
      ticketId: ticket?.stringOrNull('id'),
      ticketNumber: ticket?.stringOrNull('number'),
      queueId: json.objectOrNull('queue')?.stringOrNull('id'),
      businessName: json.objectOrNull('business')?.stringOrNull('name'),
      forStaff: json.boolean('for_staff'),
    );
  }

  static NotificationSettings settingsFromJson(JsonMap json) =>
      NotificationSettings(
        ticketUpdates: json.boolean('ticket_updates'),
        almostTurn: json.boolean('almost_turn'),
        queueUpdates: json.boolean('queue_updates'),
        staffNewTickets: json.boolean('staff_new_tickets'),
        staffCancellations: json.boolean('staff_cancellations'),
        pushEnabled: json.boolean('push_enabled'),
      );

  static int _unread(ApiEnvelope envelope) =>
      envelope.meta?.integerOrNull('unread_count') ?? 0;

  @override
  Future<NotificationPage> list(NotificationFilter filter, {int page = 1}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/me/notifications',
          query: <String, Object?>{'filter': filter.apiValue, 'page': page},
        );
        final List<AppNotification> items = envelope.dataList
            .map(fromJson)
            .toList();
        return NotificationPage(
          result: PageResult<AppNotification>(
            items: items,
            pagination:
                envelope.pagination ??
                Pagination(
                  currentPage: 1,
                  perPage: items.length,
                  total: items.length,
                  lastPage: 1,
                ),
          ),
          unreadCount: _unread(envelope),
        );
      });

  @override
  Future<int> markRead(String notificationId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/me/notifications/$notificationId/read',
    );
    return _unread(envelope);
  });

  @override
  Future<void> markAllRead() => AppFailure.guard(() async {
    await _api.post('/me/notifications/read-all');
  });

  @override
  Future<NotificationSettings> settings() => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/me/notification-preferences');
    return settingsFromJson(envelope.dataObject);
  });

  @override
  Future<NotificationSettings> updateSettings(Map<String, bool> changes) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.put(
          '/me/notification-preferences',
          body: changes,
        );
        return settingsFromJson(envelope.dataObject);
      });
}

/// [DeviceRepository] over the DORY API.
class ApiDeviceRepository implements DeviceRepository {
  const ApiDeviceRepository(this._api);

  final ApiClient _api;

  @override
  Future<String> registerForUser(PushDeviceInfo device, {String? replaces}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/devices/register',
          body: device.toJson(replaces: replaces),
        );
        return envelope.dataObject.string('id');
      });

  @override
  Future<void> unregisterForUser(String deviceId) => AppFailure.guard(() async {
    await _api.delete('/devices/$deviceId/unregister');
  });

  @override
  Future<String> registerForGuest(
    String ticketId,
    String accessToken,
    PushDeviceInfo device, {
    String? replaces,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/public/tickets/$ticketId/devices/register',
      body: device.toJson(replaces: replaces),
      headers: <String, String>{
        ApiTicketRepository.ticketTokenHeader: accessToken,
      },
    );
    return envelope.dataObject.string('id');
  });

  @override
  Future<void> unregisterForGuest(
    String ticketId,
    String accessToken,
    String deviceId,
  ) => AppFailure.guard(() async {
    await _api.delete(
      '/public/tickets/$ticketId/devices/unregister',
      body: <String, Object?>{'device': deviceId},
      headers: <String, String>{
        ApiTicketRepository.ticketTokenHeader: accessToken,
      },
    );
  });
}
