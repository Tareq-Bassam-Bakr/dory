import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

/// The polling rules, on a fake clock.
void main() {
  const Duration interval = Duration(seconds: 15);
  const Duration maximum = Duration(minutes: 2);

  TicketPoller poller(
    List<PollOutcome> outcomes, {
    required void Function() onRequest,
  }) {
    int index = 0;
    return TicketPoller(
      interval: interval,
      maxInterval: maximum,
      refresh: () async {
        onRequest();
        final PollOutcome outcome =
            outcomes[index < outcomes.length ? index : outcomes.length - 1];
        index++;
        return outcome;
      },
    );
  }

  test('refreshes on the interval while running', () {
    fakeAsync((clock) {
      int requests = 0;
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.active,
      ], onRequest: () => requests++);

      subject.resume();
      clock.flushMicrotasks();
      expect(requests, 1);

      clock.elapse(interval);
      expect(requests, 2);
      clock.elapse(interval * 3);
      expect(requests, 5);

      subject.dispose();
    });
  });

  test('the first request can wait for one interval', () {
    fakeAsync((clock) {
      int requests = 0;
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.active,
      ], onRequest: () => requests++);

      subject.resume(refreshFirst: false);
      clock.flushMicrotasks();
      expect(requests, 0);

      clock.elapse(interval);
      expect(requests, 1);

      subject.dispose();
    });
  });

  test('pausing stops every request', () {
    fakeAsync((clock) {
      int requests = 0;
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.active,
      ], onRequest: () => requests++);

      subject.resume();
      clock.flushMicrotasks();
      subject.pause();

      clock.elapse(const Duration(hours: 1));
      expect(requests, 1);
      expect(clock.pendingTimers, isEmpty);
    });
  });

  test('resuming refreshes at once', () {
    fakeAsync((clock) {
      int requests = 0;
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.active,
      ], onRequest: () => requests++);

      subject.resume();
      clock.flushMicrotasks();
      subject.pause();
      clock.elapse(const Duration(minutes: 10));

      subject.resume();
      clock.flushMicrotasks();
      expect(requests, 2);

      subject.dispose();
    });
  });

  test('failures back off exponentially up to the maximum', () {
    fakeAsync((clock) {
      final List<Duration> gaps = <Duration>[];
      Duration last = Duration.zero;
      final TicketPoller subject = poller(
        <PollOutcome>[PollOutcome.failed],
        onRequest: () {
          gaps.add(clock.elapsed - last);
          last = clock.elapsed;
        },
      );

      subject.resume();
      clock.elapse(const Duration(minutes: 10));

      expect(gaps.skip(1).take(5), <Duration>[
        const Duration(seconds: 30),
        const Duration(seconds: 60),
        const Duration(seconds: 120),
        maximum,
        maximum,
      ]);

      subject.dispose();
    });
  });

  test('a success returns to the normal interval', () {
    fakeAsync((clock) {
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.failed,
        PollOutcome.failed,
        PollOutcome.active,
      ], onRequest: () {});

      subject.resume();
      clock.flushMicrotasks();
      expect(subject.nextDelay, interval * 2);

      clock.elapse(interval * 2);
      expect(subject.nextDelay, interval * 4);

      clock.elapse(interval * 4);
      expect(subject.nextDelay, interval);

      subject.dispose();
    });
  });

  test('a finished ticket stops polling for good', () {
    fakeAsync((clock) {
      int requests = 0;
      final TicketPoller subject = poller(<PollOutcome>[
        PollOutcome.active,
        PollOutcome.finished,
      ], onRequest: () => requests++);

      subject.resume();
      clock.elapse(interval);
      expect(requests, 2);
      expect(subject.isActive, isFalse);

      clock.elapse(const Duration(hours: 1));
      subject.resume();
      subject.refreshNow();
      clock.elapse(const Duration(hours: 1));

      expect(requests, 2);
      expect(clock.pendingTimers, isEmpty);
    });
  });

  test('a slow request never overlaps the next one', () {
    fakeAsync((clock) {
      int started = 0;
      int running = 0;
      int peak = 0;
      final TicketPoller subject = TicketPoller(
        interval: interval,
        maxInterval: maximum,
        refresh: () async {
          started++;
          running++;
          peak = running > peak ? running : peak;
          await Future<void>.delayed(interval * 4);
          running--;
          return PollOutcome.active;
        },
      );

      subject.resume();
      subject.refreshNow();
      clock.elapse(interval * 3);

      expect(started, 1);
      expect(peak, 1);

      clock.elapse(const Duration(minutes: 5));
      expect(peak, 1);

      subject.dispose();
      clock.elapse(interval * 5);
    });
  });
}
