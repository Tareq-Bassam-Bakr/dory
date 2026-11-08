import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:dory_app/features/tickets/presentation/providers/live_ticket_sync.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_realtime.dart';

/// Reverb first, polling as the fallback (docs/REALTIME_ARCHITECTURE.md).
void main() {
  const String publicChannel = 'queue-updates.S1';
  const String privateChannel = 'private-tickets.T1';

  late FakeRealtimeServer server;
  late int reads;
  late PollOutcome nextOutcome;

  setUp(() {
    server = FakeRealtimeServer();
    reads = 0;
    nextOutcome = PollOutcome.active;
  });

  LiveTicketSync sync() => LiveTicketSync(
    poller: TicketPoller(
      refresh: () async {
        reads++;
        return nextOutcome;
      },
      interval: const Duration(seconds: 15),
    ),
  );

  PusherClient realtime() => PusherClient(
    uri: Uri.parse('ws://127.0.0.1:8080/app/key'),
    connect: server.connect,
    authorize: (socketId, channel) async => 'key:sig',
  );

  /// Connects and confirms both channels.
  void goLive(FakeAsync async) {
    async.flushMicrotasks();
    server.current.establish();
    async.flushMicrotasks();
    server.current
      ..confirm(publicChannel)
      ..confirm(privateChannel);
    async.flushMicrotasks();
  }

  test('without real-time the ticket polls every 15 seconds', () {
    fakeAsync((async) {
      final LiveTicketSync live = sync()..attach(null, const <String>[]);
      live.setVisible(true);
      async.elapse(const Duration(seconds: 46));

      expect(live.isLive, isFalse);
      expect(reads, 3);
      live.dispose();
    });
  });

  test('while live, polling stops and nothing is read without an event', () {
    fakeAsync((async) {
      final PusherClient client = realtime();
      final LiveTicketSync live = sync()
        ..setVisible(true)
        ..attach(client, const <String>[publicChannel, privateChannel]);
      goLive(async);
      async.elapse(const Duration(seconds: 1));
      final int afterLive = reads;

      async.elapse(const Duration(minutes: 2));
      expect(live.isLive, isTrue);
      expect(reads, afterLive, reason: 'No polling while live.');
      live.dispose();
      client.dispose();
    });
  });

  test('an event reads the ticket once, even in a burst', () {
    fakeAsync((async) {
      final PusherClient client = realtime();
      final LiveTicketSync live = sync()
        ..setVisible(true)
        ..attach(client, const <String>[publicChannel, privateChannel]);
      goLive(async);
      async.elapse(const Duration(seconds: 1));
      final int before = reads;

      for (int i = 0; i < 5; i++) {
        server.current.push('queue.changed', <String, Object?>{
          'session': 'S1',
        }, channel: publicChannel);
      }
      server.current.push('ticket.updated', <String, Object?>{
        'status': 'called',
      }, channel: privateChannel);
      async.elapse(const Duration(seconds: 1));

      expect(reads, before + 1);
      live.dispose();
      client.dispose();
    });
  });

  test(
    'a dropped connection falls back to polling, then reads once when back',
    () {
      fakeAsync((async) {
        final PusherClient client = realtime();
        final LiveTicketSync live = sync()
          ..setVisible(true)
          ..attach(client, const <String>[publicChannel, privateChannel]);
        goLive(async);
        async.elapse(const Duration(seconds: 1));

        server.current.drop();
        async.flushMicrotasks();
        expect(live.isLive, isFalse);
        // Reconnection keeps failing: polling carries on meanwhile.
        server.refuse = true;
        final int beforePolling = reads;
        async.elapse(const Duration(seconds: 31));
        expect(reads, beforePolling + 2);

        server.refuse = false;
        async.elapse(const Duration(seconds: 30));
        goLive(async);
        final int beforeLive = reads;
        async.elapse(const Duration(seconds: 1));
        expect(live.isLive, isTrue);
        expect(reads, beforeLive + 1, reason: 'One read after reconnecting.');
        async.elapse(const Duration(minutes: 1));
        expect(reads, beforeLive + 1);
        live.dispose();
        client.dispose();
      });
    },
  );

  test(
    'a hidden ticket neither polls nor reads, and reads once when shown',
    () {
      fakeAsync((async) {
        final PusherClient client = realtime();
        final LiveTicketSync live = sync()
          ..setVisible(true)
          ..attach(client, const <String>[publicChannel, privateChannel]);
        goLive(async);
        async.elapse(const Duration(seconds: 1));
        live.setVisible(false);
        final int hidden = reads;

        server.current.push('ticket.updated', <String, Object?>{
          'status': 'called',
        }, channel: privateChannel);
        async.elapse(const Duration(minutes: 1));
        expect(reads, hidden);

        live.setVisible(true);
        async.flushMicrotasks();
        expect(reads, hidden + 1);
        live.dispose();
        client.dispose();
      });
    },
  );

  test('a finished ticket stops listening and polling', () {
    fakeAsync((async) {
      final PusherClient client = realtime();
      final LiveTicketSync live = sync()
        ..setVisible(true)
        ..attach(client, const <String>[publicChannel, privateChannel]);
      goLive(async);

      live.finish();
      expect(client.channels, isEmpty);
      expect(server.current.sentEvents('pusher:unsubscribe'), hasLength(2));
      final int finished = reads;
      async.elapse(const Duration(minutes: 2));
      expect(reads, finished);
      live.dispose();
      client.dispose();
    });
  });

  test('a refused private channel keeps the ticket on polling', () {
    fakeAsync((async) {
      final PusherClient client = PusherClient(
        uri: Uri.parse('ws://127.0.0.1:8080/app/key'),
        connect: server.connect,
        authorize: (socketId, channel) async => throw StateError('403'),
      );
      final LiveTicketSync live = sync()
        ..setVisible(true)
        ..attach(client, const <String>[publicChannel, privateChannel]);
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();
      server.current.confirm(publicChannel);
      async.flushMicrotasks();

      final int before = reads;
      async.elapse(const Duration(seconds: 31));
      expect(live.isLive, isFalse);
      expect(reads, before + 2);
      live.dispose();
      client.dispose();
    });
  });

  test('attaching the same channels again changes nothing', () {
    fakeAsync((async) {
      final PusherClient client = realtime();
      final LiveTicketSync live = sync()
        ..setVisible(true)
        ..attach(client, const <String>[publicChannel])
        ..attach(client, const <String>[publicChannel]);
      async.flushMicrotasks();
      server.current.establish();
      async.flushMicrotasks();

      expect(server.current.subscribedChannels, <String>[publicChannel]);
      live.dispose();
      client.dispose();
    });
  });
}
