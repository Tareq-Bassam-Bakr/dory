import 'package:dory_app/app/app.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/realtime/realtime_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/fake_auth_repository.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fake_realtime.dart';
import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

/// Screens update through Reverb when it is live, and poll when it is not
/// (docs/REALTIME_ARCHITECTURE.md).
void main() {
  const String privateChannel = 'private-tickets.$customerTicketId';
  const String publicChannel = 'queue-updates.01jsessionxxxxxxxxxxxxxxxxx';

  Future<void> disposeRealtime(WidgetTester tester) async {
    final ProviderContainer container = ProviderScope.containerOf(
      tester.element(find.byType(DoryApp)),
    );
    await tester.pumpWidget(const SizedBox.shrink());
    container.read(realtimeClientProvider)?.dispose();
  }

  group('customer ticket', () {
    late FakeRealtimeServer server;
    late FakeHttpAdapter http;
    late Map<String, Object?> ticket;
    late int reads;

    setUp(() {
      server = FakeRealtimeServer();
      reads = 0;
      ticket = customerTicketJson()
        ..['realtime'] = <String, Object?>{
          'public_channel': publicChannel,
          'private_channel': privateChannel,
        };
      http = customerApi()
        ..reply(
          'POST',
          '/broadcasting/auth',
          const FakeResponse(200, <String, Object?>{'auth': 'key:sig'}),
        );
      http.on('GET', '/tickets/$customerTicketId/position', (_) {
        reads++;
        return FakeResponse.ok(ticket);
      });
    });

    Future<void> open(WidgetTester tester) => pumpAppAt(
      tester,
      AppRoutes.customerTicket(customerTicketId),
      authRepository: FakeAuthRepository.signedIn(),
      http: http,
      overrides: [
        realtimeUriProvider.overrideWithValue(
          Uri.parse('ws://127.0.0.1:8080/app/key'),
        ),
        realtimeSocketConnectorProvider.overrideWithValue(server.connect),
      ],
    );

    Future<void> goLive(WidgetTester tester) async {
      server.current.establish();
      await tester.pump();
      await tester.pump();
      server.current
        ..confirm(publicChannel)
        ..confirm(privateChannel);
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pump();
    }

    testWidgets('a call reaches the screen through Reverb, without polling', (
      tester,
    ) async {
      await open(tester);
      await goLive(tester);
      // The ticket's two channels (the user's own channel is also open).
      expect(
        server.current.subscribedChannels,
        containsAll(<String>[publicChannel, privateChannel]),
      );
      expect(
        find.text('Updates arrive as soon as the business acts.'),
        findsOneWidget,
      );
      final int afterLive = reads;

      await tester.pump(const Duration(minutes: 1));
      expect(reads, afterLive, reason: 'No 15-second polling while live.');

      ticket = customerTicketJson(status: 'called', position: null, ahead: null)
        ..['realtime'] = ticket['realtime'];
      server.current.push('ticket.updated', <String, Object?>{
        'ticket': customerTicketId,
        'status': 'called',
        'event': 'called',
      }, channel: privateChannel);
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pump();

      expect(reads, afterLive + 1);
      expect(find.textContaining('your turn'), findsWidgets);
      await disposeRealtime(tester);
    });

    testWidgets('a dropped connection falls back to polling', (tester) async {
      await open(tester);
      await goLive(tester);
      server.refuse = true;

      expect(
        find.text('Updates arrive as soon as the business acts.'),
        findsOneWidget,
      );
      server.current.drop();
      await tester.pump();
      await tester.pump();
      expect(
        find.textContaining('updates about every 15 seconds'),
        findsOneWidget,
      );
      final int dropped = reads;
      await tester.pump(const Duration(seconds: 16));
      expect(reads, greaterThan(dropped));
      await disposeRealtime(tester);
    });
  });

  group('staff screens', () {
    late FakeRealtimeServer server;

    setUp(() => server = FakeRealtimeServer());

    Future<List<int>> pumpRefresh(WidgetTester tester) async {
      final List<int> ticks = <int>[];
      await pumpLocalizedWidget(
        tester,
        AutoRefresh(
          onTick: () => ticks.add(ticks.length),
          channels: const <String>[publicChannel],
          child: const RefreshModeNote(),
        ),
        settle: false,
        overrides: [
          realtimeUriProvider.overrideWithValue(
            Uri.parse('ws://127.0.0.1:8080/app/key'),
          ),
          realtimeSocketConnectorProvider.overrideWithValue(server.connect),
        ],
      );
      return ticks;
    }

    Future<void> unmount(WidgetTester tester) async {
      final client = ProviderScope.containerOf(
        tester.element(find.byType(AutoRefresh)),
      ).read(realtimeClientProvider);
      await tester.pumpWidget(const SizedBox.shrink());
      client?.dispose();
    }

    testWidgets('live: a change refreshes once, and the timer stops', (
      tester,
    ) async {
      final List<int> ticks = await pumpRefresh(tester);
      await tester.pump();
      server.current.establish();
      await tester.pump();
      server.current.confirm(publicChannel);
      await tester.pump(const Duration(milliseconds: 300));
      expect(ticks, hasLength(1), reason: 'One read when going live.');

      await tester.pump(const Duration(minutes: 1));
      expect(ticks, hasLength(1), reason: 'No timer while live.');

      for (int i = 0; i < 3; i++) {
        server.current.push('queue.updated', <String, Object?>{
          'kind': 'ticket_joined',
        }, channel: publicChannel);
      }
      await tester.pump(const Duration(milliseconds: 300));
      expect(ticks, hasLength(2), reason: 'A burst is one refresh.');
      await unmount(tester);
    });

    testWidgets('the note tells staff whether the screen is live', (
      tester,
    ) async {
      await pumpRefresh(tester);
      await tester.pump();
      expect(
        find.text('Updates automatically every 15 seconds.'),
        findsOneWidget,
      );

      server.current.establish();
      await tester.pump();
      server.current.confirm(publicChannel);
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.text('Live: changes appear at once.'), findsOneWidget);

      server.refuse = true;
      server.current.drop();
      await tester.pump();
      await tester.pump();
      expect(
        find.text('Updates automatically every 15 seconds.'),
        findsOneWidget,
      );
      await unmount(tester);
    });

    testWidgets('not live: the timer refreshes every 15 seconds', (
      tester,
    ) async {
      server.refuse = true;
      final List<int> ticks = await pumpRefresh(tester);

      await tester.pump(const Duration(seconds: 46));
      expect(ticks, hasLength(3));
      await unmount(tester);
    });
  });
}
