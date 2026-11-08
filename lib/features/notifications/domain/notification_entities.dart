import 'package:dory_app/core/network/api_envelope.dart';
import 'package:flutter/foundation.dart';

/// Which notifications the center lists.
enum NotificationFilter {
  all,
  unread,
  read;

  String get apiValue => name;
}

/// One entry of the notification center (docs/NOTIFICATION_CENTER.md). The
/// title and text come from the server, in the app's language.
@immutable
class AppNotification {
  const AppNotification({
    required this.id,
    required this.kind,
    required this.title,
    required this.body,
    required this.read,
    required this.createdAt,
    this.ticketId,
    this.ticketNumber,
    this.queueId,
    this.businessName,
    this.forStaff = false,
  });

  final String id;
  final String kind;
  final String title;
  final String body;
  final bool read;
  final DateTime createdAt;
  final String? ticketId;
  final String? ticketNumber;
  final String? queueId;
  final String? businessName;

  /// About a queue the user runs, not about their own ticket.
  final bool forStaff;

  AppNotification markedRead() => AppNotification(
    id: id,
    kind: kind,
    title: title,
    body: body,
    read: true,
    createdAt: createdAt,
    ticketId: ticketId,
    ticketNumber: ticketNumber,
    queueId: queueId,
    businessName: businessName,
    forStaff: forStaff,
  );
}

/// A page of notifications and the user's unread count at that moment.
@immutable
class NotificationPage {
  const NotificationPage({required this.result, required this.unreadCount});

  final PageResult<AppNotification> result;
  final int unreadCount;
}

/// What the user wants to be told. Mirrors the API flags.
@immutable
class NotificationSettings {
  const NotificationSettings({
    this.ticketUpdates = true,
    this.almostTurn = true,
    this.queueUpdates = true,
    this.staffNewTickets = true,
    this.staffCancellations = true,
    this.pushEnabled = true,
  });

  final bool ticketUpdates;
  final bool almostTurn;
  final bool queueUpdates;
  final bool staffNewTickets;
  final bool staffCancellations;
  final bool pushEnabled;

  static const List<String> flags = <String>[
    'ticket_updates',
    'almost_turn',
    'queue_updates',
    'staff_new_tickets',
    'staff_cancellations',
    'push_enabled',
  ];

  bool flag(String name) => switch (name) {
    'ticket_updates' => ticketUpdates,
    'almost_turn' => almostTurn,
    'queue_updates' => queueUpdates,
    'staff_new_tickets' => staffNewTickets,
    'staff_cancellations' => staffCancellations,
    'push_enabled' => pushEnabled,
    _ => throw ArgumentError.value(name, 'name'),
  };

  NotificationSettings withFlag(String name, bool value) =>
      NotificationSettings(
        ticketUpdates: name == 'ticket_updates' ? value : ticketUpdates,
        almostTurn: name == 'almost_turn' ? value : almostTurn,
        queueUpdates: name == 'queue_updates' ? value : queueUpdates,
        staffNewTickets: name == 'staff_new_tickets' ? value : staffNewTickets,
        staffCancellations: name == 'staff_cancellations'
            ? value
            : staffCancellations,
        pushEnabled: name == 'push_enabled' ? value : pushEnabled,
      );

  @override
  bool operator ==(Object other) =>
      other is NotificationSettings &&
      flags.every((name) => flag(name) == other.flag(name));

  @override
  int get hashCode => Object.hashAll(flags.map(flag));
}

/// The notification center and preferences, through the API. Every method
/// throws AppFailure.
abstract interface class NotificationRepository {
  Future<NotificationPage> list(NotificationFilter filter, {int page = 1});

  /// Returns the unread count after marking.
  Future<int> markRead(String notificationId);

  Future<void> markAllRead();

  Future<NotificationSettings> settings();

  Future<NotificationSettings> updateSettings(Map<String, bool> changes);
}

/// Push device registration (docs/DEVICE_TOKEN_SECURITY.md). Returns device
/// ids, never tokens. Every method throws AppFailure.
abstract interface class DeviceRepository {
  Future<String> registerForUser(PushDeviceInfo device, {String? replaces});

  Future<void> unregisterForUser(String deviceId);

  /// [accessToken] is the guest ticket token: it travels in the
  /// X-Ticket-Token header only.
  Future<String> registerForGuest(
    String ticketId,
    String accessToken,
    PushDeviceInfo device, {
    String? replaces,
  });

  Future<void> unregisterForGuest(
    String ticketId,
    String accessToken,
    String deviceId,
  );
}

@immutable
class PushDeviceInfo {
  const PushDeviceInfo({
    required this.token,
    required this.platform,
    required this.locale,
    this.appVersion,
  });

  final String token;
  final String platform;
  final String locale;
  final String? appVersion;

  Map<String, Object?> toJson({String? replaces}) => <String, Object?>{
    'token': token,
    'platform': platform,
    'locale': locale,
    'app_version': ?appVersion,
    'replaces': ?replaces,
  };
}
