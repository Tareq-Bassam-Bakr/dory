import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/notifications/notification_target.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const String ticket = '01jcustomerticketxxxxxxxxx';
  const String queue = '01jqueuexxxxxxxxxxxxxxxxxx';

  test('a customer notification opens the ticket', () {
    final NotificationTarget target = NotificationTarget.fromData(
      <String, Object?>{
        'kind': 'ticket_called',
        'ticket': ticket,
        'guest': '0',
      },
    );

    expect(target.location, AppRoutes.customerTicket(ticket));
    expect(target.guest, isFalse);
  });

  test('a guest notification opens the guest ticket, without any token', () {
    final NotificationTarget target = NotificationTarget.fromData(
      <String, Object?>{
        'kind': 'ticket_called',
        'ticket': ticket,
        'guest': '1',
      },
    );

    expect(target.location, AppRoutes.customerTicket(ticket, guest: true));
    expect(target.toPayload().keys, isNot(contains('token')));
    expect(target.toPayload().values.join(), isNot(contains('access')));
  });

  test('a staff notification opens the queue', () {
    final NotificationTarget target = NotificationTarget.fromData(
      <String, Object?>{
        'kind': 'staff_ticket_joined',
        'ticket': ticket,
        'queue': queue,
      },
    );

    expect(target.forStaff, isTrue);
    expect(target.location, AppRoutes.queue(queue));
  });

  test('malformed ids lead to the notification center', () {
    for (final String bad in <String>[
      '../../admin',
      'short',
      'https://evil.example/x',
      '01jcustomerticketxxxxxxxxx?x=1',
    ]) {
      final NotificationTarget target = NotificationTarget.fromData(
        <String, Object?>{'kind': 'ticket_called', 'ticket': bad},
      );
      expect(target.location, AppRoutes.customerNotifications, reason: bad);
    }
  });

  test('the same alert from Reverb and FCM has one key', () {
    const String id = '9d4f6a1e-1c2b-4c3d-8e9f-0a1b2c3d4e5f';
    final NotificationTarget fromReverb = NotificationTarget.fromData(
      <String, Object?>{'kind': 'ticket_called', 'ticket': ticket, 'id': id},
    );
    final NotificationTarget fromPush = NotificationTarget.fromData(
      <String, Object?>{
        'kind': 'ticket_called',
        'ticket': ticket,
        'notification': id,
      },
    );

    expect(fromReverb.dedupeKey, fromPush.dedupeKey);
  });

  test('local notification payloads survive the round trip', () {
    final NotificationTarget target = NotificationTarget.fromData(
      <String, Object?>{
        'kind': 'ticket_called',
        'ticket': ticket,
        'guest': '1',
      },
    );

    final Map<String, String>? decoded = FlutterLocalNotifier.decodePayload(
      '{"kind":"ticket_called","ticket":"$ticket","guest":"1","bad":3}',
    );

    expect(decoded, target.toPayload());
    expect(FlutterLocalNotifier.decodePayload('not json'), isNull);
    expect(FlutterLocalNotifier.decodePayload(null), isNull);
  });
}
