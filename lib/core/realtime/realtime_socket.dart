import 'dart:async';

import 'package:web_socket_channel/web_socket_channel.dart';

/// One WebSocket connection carrying text frames. The Pusher protocol only
/// uses text, so binary frames are ignored.
abstract interface class RealtimeSocket {
  Stream<String> get messages;

  void send(String message);

  Future<void> close();
}

/// Opens a [RealtimeSocket]; tests replace it with a fake.
typedef RealtimeSocketConnector = Future<RealtimeSocket> Function(Uri uri);

/// The real connector: a `web_socket_channel` WebSocket, ready or failed
/// within [timeout].
Future<RealtimeSocket> connectWebSocket(
  Uri uri, {
  Duration timeout = const Duration(seconds: 10),
}) async {
  final WebSocketChannel channel = WebSocketChannel.connect(uri);
  try {
    await channel.ready.timeout(timeout);
  } on Object {
    unawaited(channel.sink.close());
    rethrow;
  }
  return _ChannelSocket(channel);
}

class _ChannelSocket implements RealtimeSocket {
  _ChannelSocket(this._channel);

  final WebSocketChannel _channel;

  @override
  Stream<String> get messages =>
      _channel.stream.where((message) => message is String).cast<String>();

  @override
  void send(String message) => _channel.sink.add(message);

  @override
  Future<void> close() => _channel.sink.close();
}
