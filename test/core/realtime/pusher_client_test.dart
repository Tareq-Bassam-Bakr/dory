import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_realtime.dart';

void main() {
  late FakeRealtimeServer server;
  late List<(String, String)> authorized;
  late bool refuseAuthorization;

  PusherClient client() => PusherClient(
    uri: Uri.parse('ws://127.0.0.1:8080/app/key?protocol=7'),
    connect: server.connect,
    authorize: (socketId, channel) async {
      authorized.add((socketId, channel));
      if (refuseAuthorization) {
        throw StateError('403');
      }
      return 'key:signature-for-$socketId-$channel';
    },
  );

  setUp(() {
    server = FakeRealtimeServer();
    authorized = <(String, String)>[];
    refuseAuthorization = false;
  });

  test('connects only when something subscribes', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      async.flushMicrotasks();
      expect(server.attempts, isEmpty);
      expect(realtime.status, RealtimeStatus.idle);

      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      expect(server.attempts, hasLength(1));
      expect(realtime.status, RealtimeStatus.connecting);

      server.current.establish();
      async.flushMicrotasks();
      expect(realtime.status, RealtimeStatus.connected);
      realtime.dispose();
    });
  });

  test('public channels subscribe without a signature', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      final RealtimeSubscription subscription = realtime.subscribe(
        'queue-updates.S1',
      );
      final List<RealtimeEvent> events = <RealtimeEvent>[];
      subscription.events.listen(events.add);
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      final Map<String, Object?> data =
          server.current.sentEvents('pusher:subscribe').single['data']!
              as Map<String, Object?>;
      expect(data, <String, Object?>{'channel': 'queue-updates.S1'});
      expect(authorized, isEmpty);
      expect(subscription.isSubscribed, isFalse);

      server.current.confirm('queue-updates.S1');
      async.flushMicrotasks();
      expect(subscription.isSubscribed, isTrue);
      expect(events.single.kind, RealtimeEventKind.subscribed);
      realtime.dispose();
    });
  });

  test('private channels are signed for this connection', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('private-tickets.T1');
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      expect(authorized.single, ('1.1', 'private-tickets.T1'));
      final Map<String, Object?> data =
          server.current.sentEvents('pusher:subscribe').single['data']!
              as Map<String, Object?>;
      expect(data['auth'], 'key:signature-for-1.1-private-tickets.T1');
      realtime.dispose();
    });
  });

  test('a refused signature fails the subscription without retrying it', () {
    fakeAsync((async) {
      refuseAuthorization = true;
      final PusherClient realtime = client();
      final RealtimeSubscription subscription = realtime.subscribe(
        'private-tickets.T1',
      );
      final List<RealtimeEvent> events = <RealtimeEvent>[];
      subscription.events.listen(events.add);
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      expect(events.single.kind, RealtimeEventKind.failed);
      expect(subscription.hasFailed, isTrue);
      expect(subscription.isSubscribed, isFalse);
      expect(server.current.sentEvents('pusher:subscribe'), isEmpty);
      realtime.dispose();
    });
  });

  test('two listeners of one channel share one server subscription', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      final RealtimeSubscription first = realtime.subscribe('queue-updates.S1');
      final RealtimeSubscription second = realtime.subscribe(
        'queue-updates.S1',
      );
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();
      expect(server.current.subscribedChannels, <String>['queue-updates.S1']);

      first.cancel();
      first.cancel();
      expect(server.current.sentEvents('pusher:unsubscribe'), isEmpty);
      second.cancel();
      expect(server.current.sentEvents('pusher:unsubscribe'), hasLength(1));
      expect(realtime.channels, isEmpty);
      realtime.dispose();
    });
  });

  test('server events reach their channel; client events are ignored', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      final RealtimeSubscription subscription = realtime.subscribe(
        'queue-updates.S1',
      );
      final List<RealtimeEvent> events = <RealtimeEvent>[];
      subscription.events.listen(events.add);
      async.flushMicrotasks();
      server.current
        ..establish()
        ..confirm('queue-updates.S1')
        ..push('queue.changed', <String, Object?>{
          'session': 'S1',
          'session_status': 'open',
        }, channel: 'queue-updates.S1')
        ..push('client-fake', <String, Object?>{
          'spoof': true,
        }, channel: 'queue-updates.S1')
        ..push('queue.changed', <String, Object?>{}, channel: 'other.channel');
      async.flushMicrotasks();

      final List<RealtimeEvent> messages = events
          .where((event) => event.kind == RealtimeEventKind.message)
          .toList();
      expect(messages, hasLength(1));
      expect(messages.single.name, 'queue.changed');
      expect(messages.single.data['session_status'], 'open');
      realtime.dispose();
    });
  });

  test('answers pings, and pings a silent server', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      server.current.answersPings = false;
      server.current.establish(activityTimeout: 30);
      server.current.push('pusher:ping', const <String, Object?>{});
      async.flushMicrotasks();
      expect(server.current.sentEvents('pusher:pong'), hasLength(1));

      async.elapse(const Duration(seconds: 31));
      expect(server.current.sentEvents('pusher:ping'), hasLength(1));
      server.current.push('pusher:pong', const <String, Object?>{});
      async.elapse(const Duration(seconds: 20));
      expect(realtime.status, RealtimeStatus.connected);
      realtime.dispose();
    });
  });

  test('a server that stops answering pings is reconnected', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      server.current
        ..answersPings = false
        ..establish(activityTimeout: 30);
      async.flushMicrotasks();

      // Ping after 30 s of silence, no pong within 30 s more.
      async.elapse(const Duration(seconds: 60, milliseconds: 500));
      expect(server.sockets.first.closed, isTrue);
      expect(realtime.status, RealtimeStatus.reconnecting);
      async.elapse(const Duration(seconds: 1));
      expect(server.sockets, hasLength(2));
      realtime.dispose();
    });
  });

  test('a dropped connection comes back and every channel resubscribes', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      final RealtimeSubscription ticket = realtime.subscribe(
        'private-tickets.T1',
      );
      realtime.subscribe('queue-updates.S1');
      final List<RealtimeEvent> events = <RealtimeEvent>[];
      ticket.events.listen(events.add);
      async.flushMicrotasks();
      server.current
        ..establish()
        ..confirm('private-tickets.T1');
      async.flushMicrotasks();

      server.current.drop();
      async.flushMicrotasks();
      expect(realtime.status, RealtimeStatus.reconnecting);
      expect(ticket.isSubscribed, isFalse);

      async.elapse(const Duration(seconds: 1));
      server.current.establish();
      async.flushMicrotasks();
      expect(server.current.subscribedChannels.toSet(), <String>{
        'private-tickets.T1',
        'queue-updates.S1',
      });
      // Signed again, for the new connection.
      expect(authorized.last, ('2.2', 'private-tickets.T1'));

      server.current.confirm('private-tickets.T1');
      async.flushMicrotasks();
      expect(
        events.where((event) => event.kind == RealtimeEventKind.subscribed),
        hasLength(2),
      );
      realtime.dispose();
    });
  });

  test('waits longer after each failed attempt, up to 30 seconds', () {
    expect(PusherClient.defaultReconnectDelay(1), const Duration(seconds: 1));
    expect(PusherClient.defaultReconnectDelay(2), const Duration(seconds: 2));
    expect(PusherClient.defaultReconnectDelay(5), const Duration(seconds: 16));
    expect(PusherClient.defaultReconnectDelay(6), const Duration(seconds: 30));
    expect(PusherClient.defaultReconnectDelay(20), const Duration(seconds: 30));

    fakeAsync((async) {
      server.refuse = true;
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      expect(server.attempts, hasLength(1));
      async.elapse(const Duration(seconds: 1));
      expect(server.attempts, hasLength(2));
      async.elapse(const Duration(seconds: 1));
      expect(server.attempts, hasLength(2));
      async.elapse(const Duration(seconds: 1));
      expect(server.attempts, hasLength(3));
      realtime.dispose();
    });
  });

  test('a refused application stops; a busy server is retried', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      server.current
        ..establish()
        ..push('pusher:error', <String, Object?>{'code': 4001});
      async.flushMicrotasks();
      expect(realtime.status, RealtimeStatus.failed);
      async.elapse(const Duration(minutes: 5));
      expect(server.attempts, hasLength(1));
      realtime.dispose();
    });

    fakeAsync((async) {
      final FakeRealtimeServer busy = FakeRealtimeServer();
      final PusherClient realtime = PusherClient(
        uri: Uri.parse('ws://127.0.0.1:8080/app/key'),
        connect: busy.connect,
        authorize: (_, _) async => 'x',
      );
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      busy.current
        ..establish()
        ..push('pusher:error', <String, Object?>{'code': 4200});
      async.flushMicrotasks();
      expect(realtime.status, RealtimeStatus.reconnecting);
      async.elapse(const Duration(seconds: 1));
      expect(busy.attempts, hasLength(2));
      realtime.dispose();
    });
  });

  test('suspends in the background and resumes in front', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1');
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      realtime.suspend();
      expect(server.sockets.first.closed, isTrue);
      expect(realtime.status, RealtimeStatus.suspended);
      async.elapse(const Duration(minutes: 5));
      expect(server.attempts, hasLength(1));

      realtime.resume();
      async.flushMicrotasks();
      expect(server.attempts, hasLength(2));
      server.current.establish();
      async.flushMicrotasks();
      expect(server.current.subscribedChannels, <String>['queue-updates.S1']);
      realtime.dispose();
    });
  });

  test('closes the connection a while after the last listener leaves', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      final RealtimeSubscription subscription = realtime.subscribe(
        'queue-updates.S1',
      );
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      subscription.cancel();
      async.elapse(const Duration(seconds: 10));
      expect(server.current.closed, isFalse);
      async.elapse(const Duration(seconds: 15));
      expect(server.current.closed, isTrue);
      expect(realtime.status, RealtimeStatus.idle);
      realtime.dispose();
    });
  });

  test('a new listener within the idle time keeps the connection', () {
    fakeAsync((async) {
      final PusherClient realtime = client();
      realtime.subscribe('queue-updates.S1').cancel();
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      async.elapse(const Duration(seconds: 5));
      realtime.subscribe('queue-updates.S2');
      async.elapse(const Duration(seconds: 30));
      expect(server.sockets, hasLength(1));
      expect(server.current.closed, isFalse);
      realtime.dispose();
    });
  });
}
