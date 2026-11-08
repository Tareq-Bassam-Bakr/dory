import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

import 'package:dory_app/core/realtime/realtime_socket.dart';
import 'package:flutter/foundation.dart';

/// Where the connection to Reverb stands.
enum RealtimeStatus {
  /// Nothing to listen to: no connection is open.
  idle,
  connecting,
  connected,

  /// The connection dropped; another attempt is scheduled.
  reconnecting,

  /// The app is in the background: closed on purpose until it returns.
  suspended,

  /// The server refused the app (wrong key, for instance): no retries.
  failed,
}

enum RealtimeEventKind {
  /// The server confirmed the subscription. Listeners read the current
  /// state from the API: events sent while they were away are not replayed.
  subscribed,

  /// The subscription was refused (authorization) or could not be signed.
  failed,

  /// A server event on the channel.
  message,
}

@immutable
class RealtimeEvent {
  const RealtimeEvent._(this.kind, this.channel, this.name, this.data);

  factory RealtimeEvent.subscribed(String channel) =>
      RealtimeEvent._(RealtimeEventKind.subscribed, channel, null, const {});

  factory RealtimeEvent.failed(String channel) =>
      RealtimeEvent._(RealtimeEventKind.failed, channel, null, const {});

  factory RealtimeEvent.message(
    String channel,
    String name,
    Map<String, Object?> data,
  ) => RealtimeEvent._(RealtimeEventKind.message, channel, name, data);

  final RealtimeEventKind kind;
  final String channel;
  final String? name;
  final Map<String, Object?> data;
}

/// Signs a private channel subscription for this connection
/// (POST /broadcasting/auth): returns the `auth` value.
typedef ChannelAuthorizer =
    Future<String> Function(String socketId, String channel);

/// A minimal Pusher protocol 7 client for Laravel Reverb
/// (docs/REALTIME_ARCHITECTURE.md).
///
/// - Connects lazily, on the first subscription, and closes a while after
///   the last one ends.
/// - Subscriptions are counted: two screens listening to the same channel
///   share one server subscription, released when both are done.
/// - Private channels are signed by the API with the user's own token: the
///   server decides who may listen, never this client.
/// - Reconnects with growing delays; on reconnection every channel is
///   subscribed again and listeners get [RealtimeEventKind.subscribed], so
///   they refresh from the API.
/// - [suspend] closes the connection while the app is in the background;
///   [resume] reconnects.
/// - Events sent by other clients (`client-*`) are ignored: only the server
///   speaks on DORY channels.
class PusherClient {
  PusherClient({
    required this.uri,
    required RealtimeSocketConnector connect,
    required ChannelAuthorizer authorize,
    this.reconnectDelay = defaultReconnectDelay,
    this.idleTimeout = const Duration(seconds: 20),
    this.pongTimeout = const Duration(seconds: 30),
  }) : _connector = connect,
       _authorizeChannel = authorize;

  /// 1, 2, 4, 8, 16, then 30 seconds.
  static Duration defaultReconnectDelay(int attempt) =>
      Duration(seconds: math.min(30, 1 << math.min(attempt - 1, 5)));

  final Uri uri;
  final Duration Function(int attempt) reconnectDelay;
  final Duration idleTimeout;
  final Duration pongTimeout;
  final RealtimeSocketConnector _connector;
  final ChannelAuthorizer _authorizeChannel;

  final Map<String, _Channel> _channels = <String, _Channel>{};
  final StreamController<RealtimeStatus> _statusChanges =
      StreamController<RealtimeStatus>.broadcast(sync: true);

  RealtimeStatus _status = RealtimeStatus.idle;
  RealtimeSocket? _socket;
  StreamSubscription<String>? _messages;
  String? _socketId;
  int _attempts = 0;
  int _generation = 0;
  bool _suspended = false;
  bool _disposed = false;
  Duration _activityTimeout = const Duration(seconds: 30);
  Timer? _reconnectTimer;
  Timer? _activityTimer;
  Timer? _pongTimer;
  Timer? _idleTimer;

  RealtimeStatus get status => _status;

  Stream<RealtimeStatus> get statusChanges => _statusChanges.stream;

  bool get isConnected => _status == RealtimeStatus.connected;

  /// Channels with at least one listener, for diagnostics and tests.
  Set<String> get channels => _channels.keys.toSet();

  /// Starts listening to [channel]. Cancel the returned subscription when
  /// the screen goes away.
  RealtimeSubscription subscribe(String channel) {
    if (_disposed) {
      throw StateError('PusherClient was disposed.');
    }
    _idleTimer?.cancel();
    final _Channel entry = _channels.putIfAbsent(
      channel,
      () => _Channel(channel),
    );
    entry.references++;
    if (entry.references == 1) {
      if (isConnected) {
        unawaited(_sendSubscribe(entry));
      } else if (_status == RealtimeStatus.idle) {
        unawaited(_connect());
      }
    }
    return RealtimeSubscription._(this, entry);
  }

  /// The app went to the background: close the connection and keep the
  /// subscriptions for [resume].
  void suspend() {
    if (_disposed || _suspended) {
      return;
    }
    _suspended = true;
    _reconnectTimer?.cancel();
    _closeSocket(RealtimeStatus.suspended);
  }

  /// The app is in front again: reconnect if anything listens.
  void resume() {
    if (_disposed || !_suspended) {
      return;
    }
    _suspended = false;
    _attempts = 0;
    if (_channels.isEmpty) {
      _setStatus(RealtimeStatus.idle);
    } else {
      unawaited(_connect());
    }
  }

  void dispose() {
    if (_disposed) {
      return;
    }
    _disposed = true;
    _reconnectTimer?.cancel();
    _idleTimer?.cancel();
    _closeSocket(RealtimeStatus.idle);
    for (final _Channel channel in _channels.values) {
      unawaited(channel.events.close());
    }
    _channels.clear();
    unawaited(_statusChanges.close());
  }

  // ----- Connection -------------------------------------------------------

  Future<void> _connect() async {
    if (_disposed ||
        _suspended ||
        _status == RealtimeStatus.connecting ||
        _status == RealtimeStatus.connected ||
        _status == RealtimeStatus.failed) {
      return;
    }
    final int generation = ++_generation;
    _setStatus(RealtimeStatus.connecting);
    try {
      final RealtimeSocket socket = await _connector(uri);
      if (generation != _generation || _disposed || _suspended) {
        await socket.close();
        return;
      }
      _socket = socket;
      _messages = socket.messages.listen(
        _onMessage,
        onDone: () => _onClosed(generation),
        onError: (Object _) => _onClosed(generation),
        cancelOnError: true,
      );
    } on Object {
      if (generation == _generation) {
        _scheduleReconnect();
      }
    }
  }

  /// The server or the network closed the connection.
  void _onClosed(int generation) {
    if (generation != _generation) {
      return;
    }
    _scheduleReconnect();
  }

  void _scheduleReconnect() {
    if (_disposed || _suspended) {
      return;
    }
    if (_channels.isEmpty) {
      _closeSocket(RealtimeStatus.idle);
      return;
    }
    _closeSocket(RealtimeStatus.reconnecting);
    _attempts++;
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(reconnectDelay(_attempts), () {
      unawaited(_connect());
    });
  }

  void _closeSocket(RealtimeStatus next) {
    _generation++;
    _activityTimer?.cancel();
    _pongTimer?.cancel();
    unawaited(_messages?.cancel());
    _messages = null;
    final RealtimeSocket? socket = _socket;
    _socket = null;
    _socketId = null;
    if (socket != null) {
      unawaited(socket.close().catchError((Object _) {}));
    }
    for (final _Channel channel in _channels.values) {
      channel.requested = false;
      channel.subscribed = false;
    }
    _setStatus(next);
  }

  void _setStatus(RealtimeStatus status) {
    if (_status == status || _disposed && status != RealtimeStatus.idle) {
      return;
    }
    _status = status;
    if (!_statusChanges.isClosed) {
      _statusChanges.add(status);
    }
  }

  // ----- Protocol ---------------------------------------------------------

  void _onMessage(String raw) {
    _touchActivity();
    final Map<String, Object?> message;
    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is! Map<String, Object?>) {
        return;
      }
      message = decoded;
    } on FormatException {
      return;
    }
    final String event = message['event'] as String? ?? '';
    final String? channel = message['channel'] as String?;
    final Map<String, Object?> data = _decodeData(message['data']);

    switch (event) {
      case 'pusher:connection_established':
        _socketId = data['socket_id'] as String?;
        final Object? timeout = data['activity_timeout'];
        if (timeout is int && timeout > 0) {
          _activityTimeout = Duration(seconds: timeout);
        }
        _attempts = 0;
        _setStatus(RealtimeStatus.connected);
        for (final _Channel entry in _channels.values.toList()) {
          unawaited(_sendSubscribe(entry));
        }
        _touchActivity();
      case 'pusher:ping':
        _send('pusher:pong', const <String, Object?>{});
      case 'pusher:pong':
        _pongTimer?.cancel();
      case 'pusher:error':
        _onServerError(data['code']);
      case 'pusher_internal:subscription_succeeded':
        final _Channel? entry = _channels[channel];
        if (entry != null) {
          entry.subscribed = true;
          entry.emit(RealtimeEvent.subscribed(entry.name));
        }
      case 'pusher:subscription_error':
        _channels[channel]?.fail();
      default:
        final _Channel? entry = _channels[channel];
        if (entry != null &&
            !event.startsWith('pusher') &&
            !event.startsWith('client-')) {
          entry.emit(RealtimeEvent.message(entry.name, event, data));
        }
    }
  }

  /// Pusher error codes: 4000-4099 do not retry, 4100-4199 retry later,
  /// 4200-4299 retry now.
  void _onServerError(Object? code) {
    if (code is! int) {
      return;
    }
    if (code >= 4000 && code < 4100) {
      _closeSocket(RealtimeStatus.failed);
    } else if (code >= 4200 && code < 4300) {
      _attempts = 0;
      _scheduleReconnect();
    } else if (code >= 4100 && code < 4200) {
      _scheduleReconnect();
    }
  }

  Future<void> _sendSubscribe(_Channel entry) async {
    final String? socketId = _socketId;
    if (socketId == null || entry.requested) {
      return;
    }
    entry.requested = true;
    final Map<String, Object?> data = <String, Object?>{'channel': entry.name};
    if (entry.name.startsWith('private-') ||
        entry.name.startsWith('presence-')) {
      try {
        data['auth'] = await _authorizeChannel(socketId, entry.name);
      } on Object {
        if (socketId == _socketId) {
          entry.fail();
        }
        return;
      }
      // The connection changed while signing: the new one resubscribes.
      if (socketId != _socketId || !_channels.containsKey(entry.name)) {
        return;
      }
    }
    _send('pusher:subscribe', data);
  }

  void _release(_Channel entry) {
    entry.references--;
    if (entry.references > 0) {
      return;
    }
    _channels.remove(entry.name);
    if (isConnected && entry.requested) {
      _send('pusher:unsubscribe', <String, Object?>{'channel': entry.name});
    }
    unawaited(entry.events.close());
    if (_channels.isEmpty) {
      _idleTimer?.cancel();
      _idleTimer = Timer(idleTimeout, () {
        if (_channels.isEmpty && !_disposed && !_suspended) {
          _reconnectTimer?.cancel();
          _closeSocket(RealtimeStatus.idle);
        }
      });
    }
  }

  void _send(String event, Map<String, Object?> data) {
    _socket?.send(jsonEncode(<String, Object?>{'event': event, 'data': data}));
  }

  /// No message for the server's activity timeout: ping, and reconnect if
  /// no pong comes back.
  void _touchActivity() {
    _activityTimer?.cancel();
    if (_socket == null) {
      return;
    }
    _activityTimer = Timer(_activityTimeout, () {
      _send('pusher:ping', const <String, Object?>{});
      _pongTimer?.cancel();
      _pongTimer = Timer(pongTimeout, _scheduleReconnect);
    });
  }

  static Map<String, Object?> _decodeData(Object? data) {
    if (data is Map<String, Object?>) {
      return data;
    }
    if (data is String && data.isNotEmpty) {
      try {
        final Object? decoded = jsonDecode(data);
        if (decoded is Map<String, Object?>) {
          return decoded;
        }
      } on FormatException {
        return const <String, Object?>{};
      }
    }
    return const <String, Object?>{};
  }
}

/// One listener's hold on a channel.
class RealtimeSubscription {
  RealtimeSubscription._(this._client, this._entry);

  final PusherClient _client;
  final _Channel _entry;
  bool _cancelled = false;

  String get channel => _entry.name;

  Stream<RealtimeEvent> get events => _entry.events.stream;

  /// The server confirmed this channel on the current connection.
  bool get isSubscribed =>
      !_cancelled && _entry.subscribed && _client.isConnected;

  bool get hasFailed => _entry.failed;

  void cancel() {
    if (_cancelled) {
      return;
    }
    _cancelled = true;
    _client._release(_entry);
  }
}

class _Channel {
  _Channel(this.name);

  final String name;
  final StreamController<RealtimeEvent> events =
      StreamController<RealtimeEvent>.broadcast(sync: true);
  int references = 0;
  bool requested = false;
  bool subscribed = false;
  bool failed = false;

  void emit(RealtimeEvent event) {
    if (event.kind == RealtimeEventKind.subscribed) {
      failed = false;
    }
    if (!events.isClosed) {
      events.add(event);
    }
  }

  void fail() {
    failed = true;
    subscribed = false;
    emit(RealtimeEvent.failed(name));
  }
}
