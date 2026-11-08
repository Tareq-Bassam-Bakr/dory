import 'dart:async';

import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:flutter/foundation.dart';

/// Keeps an open ticket current: Reverb first, polling as the fallback
/// (docs/REALTIME_ARCHITECTURE.md).
///
/// - Live (connected, every channel confirmed): no timer. Each event makes
///   one API read, the source of truth; bursts of events collapse into one.
/// - Not live (no real-time, connecting, dropped, refused): the poller runs
///   as in Phase 05.
/// - Becoming live again reads the ticket once: events sent while away are
///   not replayed.
/// - Hidden screens neither poll nor read; they read once when shown again.
class LiveTicketSync {
  LiveTicketSync({
    required this.poller,
    this.debounce = const Duration(milliseconds: 300),
  });

  final TicketPoller poller;
  final Duration debounce;

  /// True while updates arrive through Reverb.
  final ValueNotifier<bool> live = ValueNotifier<bool>(false);

  PusherClient? _client;
  Set<String> _channels = const <String>{};
  final List<RealtimeSubscription> _subscriptions = <RealtimeSubscription>[];
  final List<StreamSubscription<Object?>> _listeners =
      <StreamSubscription<Object?>>[];
  Timer? _debounceTimer;
  bool _visible = false;
  bool _started = false;
  bool _pendingRefresh = false;
  bool _finished = false;

  bool get isLive => live.value;

  /// Listens on [channels] through [client]; null means real-time is off.
  /// Calling again with the same values does nothing.
  void attach(PusherClient? client, Iterable<String> channels) {
    if (_finished) {
      return;
    }
    final Set<String> wanted = channels.toSet();
    if (identical(client, _client) && setEquals(wanted, _channels)) {
      return;
    }
    _detach();
    _client = client;
    _channels = wanted;
    if (client != null && wanted.isNotEmpty) {
      for (final String channel in wanted) {
        final RealtimeSubscription subscription = client.subscribe(channel);
        _subscriptions.add(subscription);
        _listeners.add(subscription.events.listen(_onEvent));
      }
      _listeners.add(client.statusChanges.listen((_) => _evaluate()));
    }
    _evaluate();
  }

  /// The ticket screen is (or is no longer) what the customer looks at.
  void setVisible(bool visible) {
    if (_finished || visible == _visible) {
      return;
    }
    _visible = visible;
    if (!visible) {
      poller.pause();
      return;
    }
    if (isLive) {
      if (_pendingRefresh || _started) {
        _pendingRefresh = false;
        poller.refreshNow();
      }
    } else {
      poller.resume(refreshFirst: _started);
    }
    _started = true;
  }

  /// Nothing more can change: stop listening and polling.
  void finish() {
    if (_finished) {
      return;
    }
    _finished = true;
    _detach();
    live.value = false;
    poller.finish();
  }

  void dispose() {
    _detach();
    poller.dispose();
    live.dispose();
  }

  void _onEvent(RealtimeEvent event) {
    switch (event.kind) {
      case RealtimeEventKind.subscribed:
        // Becoming live reads the ticket; a channel confirmed again while
        // live (after a reconnection) also needs one read.
        if (!_evaluate() && isLive) {
          _refreshSoon();
        }
      case RealtimeEventKind.failed:
        _evaluate();
      case RealtimeEventKind.message:
        _refreshSoon();
    }
  }

  /// Returns true when the live state changed.
  bool _evaluate() {
    final PusherClient? client = _client;
    final bool nowLive =
        !_finished &&
        client != null &&
        client.isConnected &&
        _subscriptions.isNotEmpty &&
        _subscriptions.every((subscription) => subscription.isSubscribed);
    if (nowLive == live.value) {
      return false;
    }
    live.value = nowLive;
    if (nowLive) {
      poller.pause();
      // Whatever happened while away: read the ticket once.
      _refreshSoon();
    } else if (_visible) {
      poller.resume(refreshFirst: false);
    }
    return true;
  }

  void _refreshSoon() {
    if (_finished) {
      return;
    }
    _debounceTimer?.cancel();
    _debounceTimer = Timer(debounce, () {
      if (_visible) {
        poller.refreshNow();
      } else {
        _pendingRefresh = true;
      }
    });
  }

  void _detach() {
    _debounceTimer?.cancel();
    for (final StreamSubscription<Object?> listener in _listeners) {
      unawaited(listener.cancel());
    }
    _listeners.clear();
    for (final RealtimeSubscription subscription in _subscriptions) {
      subscription.cancel();
    }
    _subscriptions.clear();
    _client = null;
    _channels = const <String>{};
  }
}
