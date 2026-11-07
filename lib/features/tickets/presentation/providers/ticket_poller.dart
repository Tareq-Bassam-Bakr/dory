import 'dart:async';

import 'package:dory_app/core/config/ticket_config.dart';

/// What one refresh found.
enum PollOutcome {
  /// The ticket was read and is still active.
  active,

  /// The ticket is finished: nothing more can change, so polling stops.
  finished,

  /// The request failed: try again later, waiting longer each time.
  failed,
}

/// Refreshes one ticket on a timer while it is on screen.
///
/// - Only one request runs at a time, and the next is scheduled only after
///   the previous one finished, so slow networks never pile up requests.
/// - Failures back off exponentially up to [maxInterval].
/// - [pause] stops everything; [resume] refreshes at once, so a customer
///   returning to the app never looks at stale numbers.
///
/// This is polling, not real-time: see docs/CUSTOMER_TICKET_EXPERIENCE.md.
class TicketPoller {
  TicketPoller({
    required this.refresh,
    this.interval = TicketConfig.pollInterval,
    this.maxInterval = TicketConfig.maxPollInterval,
  });

  final Future<PollOutcome> Function() refresh;
  final Duration interval;
  final Duration maxInterval;

  Timer? _timer;
  int _failures = 0;
  bool _active = false;
  bool _running = false;
  bool _finished = false;

  bool get isActive => _active;

  /// Starts polling, or does nothing if it already runs. With
  /// [refreshFirst] false the first request waits one interval, for when the
  /// ticket was just loaded.
  void resume({bool refreshFirst = true}) {
    if (_active || _finished) {
      return;
    }
    _active = true;
    _failures = 0;
    if (refreshFirst) {
      unawaited(_tick());
    } else {
      _timer = Timer(interval, () => unawaited(_tick()));
    }
  }

  /// Nothing more can change: stop for good.
  void finish() {
    _finished = true;
    pause();
  }

  void pause() {
    _active = false;
    _timer?.cancel();
    _timer = null;
  }

  /// The customer acted: refresh now and keep the normal rhythm.
  void refreshNow() {
    if (_finished) {
      return;
    }
    _timer?.cancel();
    _failures = 0;
    unawaited(_tick());
  }

  void dispose() => pause();

  /// The wait before the next request.
  Duration get nextDelay {
    if (_failures == 0) {
      return interval;
    }
    final int factor = 1 << _failures.clamp(0, 10);
    final Duration delay = interval * factor;
    return delay > maxInterval ? maxInterval : delay;
  }

  Future<void> _tick() async {
    if (_running) {
      return;
    }
    _running = true;
    final PollOutcome outcome = await refresh();
    _running = false;

    switch (outcome) {
      case PollOutcome.finished:
        finish();
        return;
      case PollOutcome.failed:
        _failures++;
      case PollOutcome.active:
        _failures = 0;
    }
    if (_active) {
      _timer?.cancel();
      _timer = Timer(nextDelay, () => unawaited(_tick()));
    }
  }
}
