import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dory_app/core/realtime/realtime_socket.dart';

/// A stand-in for Reverb: records every connection, lets a test play the
/// server's side of the Pusher protocol, and can refuse connections.
class FakeRealtimeServer {
  final List<FakeRealtimeSocket> sockets = <FakeRealtimeSocket>[];
  final List<Uri> attempts = <Uri>[];
  bool refuse = false;

  Future<RealtimeSocket> connect(Uri uri) async {
    attempts.add(uri);
    if (refuse) {
      throw const SocketException('Connection refused');
    }
    final FakeRealtimeSocket socket = FakeRealtimeSocket(sockets.length + 1);
    sockets.add(socket);
    return socket;
  }

  FakeRealtimeSocket get current => sockets.last;
}

class FakeRealtimeSocket implements RealtimeSocket {
  FakeRealtimeSocket(this.number);

  final int number;
  final StreamController<String> _incoming = StreamController<String>();
  final List<Map<String, Object?>> sent = <Map<String, Object?>>[];
  bool closed = false;

  /// Reverb answers pings; a test can make the server silent.
  bool answersPings = true;

  String get socketId => '$number.$number';

  @override
  Stream<String> get messages => _incoming.stream;

  @override
  void send(String message) {
    final Map<String, Object?> decoded =
        jsonDecode(message) as Map<String, Object?>;
    sent.add(decoded);
    if (answersPings && decoded['event'] == 'pusher:ping' && !closed) {
      scheduleMicrotask(() => push('pusher:pong', const <String, Object?>{}));
    }
  }

  @override
  Future<void> close() async {
    closed = true;
  }

  // ----- The server's side ------------------------------------------------

  void establish({int activityTimeout = 30}) =>
      push('pusher:connection_established', <String, Object?>{
        'socket_id': socketId,
        'activity_timeout': activityTimeout,
      });

  void push(String event, Map<String, Object?> data, {String? channel}) {
    _incoming.add(
      jsonEncode(<String, Object?>{
        'event': event,
        'channel': ?channel,
        'data': jsonEncode(data),
      }),
    );
  }

  void confirm(String channel) => push(
    'pusher_internal:subscription_succeeded',
    const {},
    channel: channel,
  );

  void drop() => unawaited(_incoming.close());

  /// Messages the client sent with [event], in order.
  List<Map<String, Object?>> sentEvents(String event) =>
      sent.where((message) => message['event'] == event).toList();

  /// Channels the client asked to subscribe to on this connection.
  List<String> get subscribedChannels => <String>[
    for (final Map<String, Object?> message in sentEvents('pusher:subscribe'))
      (message['data']! as Map<String, Object?>)['channel']! as String,
  ];
}
