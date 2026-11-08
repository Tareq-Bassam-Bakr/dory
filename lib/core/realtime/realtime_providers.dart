import 'dart:async';

import 'package:dory_app/core/config/realtime_config.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:dory_app/core/realtime/realtime_socket.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// The Reverb address; null when real-time is not configured. Tests
/// override it.
final Provider<Uri?> realtimeUriProvider = Provider<Uri?>(
  (ref) => RealtimeConfig.socketUri,
  name: 'realtimeUriProvider',
);

/// Opens WebSockets; tests replace it with a fake server.
final Provider<RealtimeSocketConnector> realtimeSocketConnectorProvider =
    Provider<RealtimeSocketConnector>(
      (ref) => connectWebSocket,
      name: 'realtimeSocketConnectorProvider',
    );

/// The app's single Reverb connection, or null when real-time is off (the
/// screens then poll, as before).
///
/// One client per signed-in account: private channels are signed with the
/// account's token, so signing in or out builds a new client and every
/// screen subscribes again. The connection closes while the app is in the
/// background and reopens when it returns.
final Provider<PusherClient?> realtimeClientProvider = Provider<PusherClient?>((
  ref,
) {
  final Uri? uri = ref.watch(realtimeUriProvider);
  if (uri == null) {
    return null;
  }
  ref.watch(currentUserIdProvider);
  final api = ref.watch(apiClientProvider);

  final PusherClient client = PusherClient(
    uri: uri,
    connect: ref.watch(realtimeSocketConnectorProvider),
    authorize: (socketId, channel) async {
      final JsonMap answer = await api.postRaw(
        '/broadcasting/auth',
        body: <String, Object?>{'socket_id': socketId, 'channel_name': channel},
      );
      return answer.string('auth');
    },
  );

  final AppLifecycleListener lifecycle = AppLifecycleListener(
    onHide: client.suspend,
    onShow: client.resume,
  );
  ref.onDispose(() {
    lifecycle.dispose();
    client.dispose();
  });
  return client;
}, name: 'realtimeClientProvider');

/// The connection status, for screens that tell the user whether updates
/// arrive live.
final StreamProvider<RealtimeStatus> realtimeStatusProvider =
    StreamProvider<RealtimeStatus>((ref) {
      final PusherClient? client = ref.watch(realtimeClientProvider);
      if (client == null) {
        return Stream<RealtimeStatus>.value(RealtimeStatus.idle);
      }
      final StreamController<RealtimeStatus> controller =
          StreamController<RealtimeStatus>();
      controller.add(client.status);
      final StreamSubscription<RealtimeStatus> subscription = client
          .statusChanges
          .listen(controller.add);
      ref.onDispose(() {
        unawaited(subscription.cancel());
        unawaited(controller.close());
      });
      return controller.stream;
    }, name: 'realtimeStatusProvider');
