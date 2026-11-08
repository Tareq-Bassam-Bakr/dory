import 'package:dory_app/app/router/app_routes.dart';
import 'package:flutter/foundation.dart';

/// Where a notification leads (docs/PUSH_NOTIFICATIONS.md).
///
/// Built from a push or local notification payload: the kind and public ids
/// only. A guest ticket's access token is never in a payload: opening a
/// guest ticket reads it from this device's encrypted storage, and the
/// ticket screen explains when it is missing. Ids that do not look like
/// DORY public ids are ignored, so a malformed payload opens the
/// notification center instead of a guessed screen.
@immutable
class NotificationTarget {
  const NotificationTarget({
    required this.kind,
    this.ticketId,
    this.queueId,
    this.guest = false,
    this.notificationId,
  });

  factory NotificationTarget.fromData(Map<String, Object?> data) {
    String? text(String key) {
      final Object? value = data[key];
      return value is String && value.isNotEmpty ? value : null;
    }

    String? publicId(String key) {
      final String? value = text(key);
      return value != null && _publicId.hasMatch(value) ? value : null;
    }

    final String? notification = text('notification') ?? text('id');
    return NotificationTarget(
      kind: text('kind') ?? '',
      ticketId: publicId('ticket'),
      queueId: publicId('queue'),
      guest: text('guest') == '1',
      notificationId: notification != null && _uuid.hasMatch(notification)
          ? notification
          : null,
    );
  }

  static final RegExp _publicId = RegExp(r'^[0-9A-Za-z]{26}$');
  static final RegExp _uuid = RegExp(r'^[0-9a-fA-F-]{36}$');

  final String kind;
  final String? ticketId;
  final String? queueId;
  final bool guest;
  final String? notificationId;

  bool get forStaff => kind.startsWith('staff_');

  /// Staff open the queue; customers and guests their ticket.
  String get location {
    if (forStaff) {
      final String? queue = queueId;
      return queue != null
          ? AppRoutes.queue(queue)
          : AppRoutes.customerNotifications;
    }
    final String? ticket = ticketId;
    return ticket != null
        ? AppRoutes.customerTicket(ticket, guest: guest)
        : AppRoutes.customerNotifications;
  }

  /// For local notifications: strings only, no secrets.
  Map<String, String> toPayload() => <String, String>{
    'kind': kind,
    'ticket': ?ticketId,
    'queue': ?queueId,
    'guest': guest ? '1' : '0',
    'notification': ?notificationId,
  };

  /// A stable key: the same alert from Reverb and from FCM is shown once.
  String get dedupeKey =>
      notificationId ?? '$kind:${ticketId ?? queueId ?? ''}';
}
