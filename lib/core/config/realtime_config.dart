import 'package:flutter/foundation.dart';

/// Where the Laravel Reverb WebSocket server is (docs/REVERB_SETUP.md).
///
/// - `--dart-define=DORY_REVERB_APP_KEY=...`: the Reverb application key.
///   It is public (every client needs it); the secret never leaves the
///   server. Without a key, real-time is off and screens poll as before.
/// - `DORY_REVERB_HOST`, `DORY_REVERB_PORT`, `DORY_REVERB_SCHEME`
///   (`http` or `https`). Debug and profile builds default to
///   `127.0.0.1:8080` over plain WebSocket, which reaches the development
///   machine from a USB phone after `adb reverse tcp:8080 tcp:8080`.
///   Release builds have no defaults and accept only `https` (`wss`).
abstract final class RealtimeConfig {
  static const String _key = String.fromEnvironment('DORY_REVERB_APP_KEY');
  static const String _host = String.fromEnvironment('DORY_REVERB_HOST');
  static const String _port = String.fromEnvironment('DORY_REVERB_PORT');
  static const String _scheme = String.fromEnvironment('DORY_REVERB_SCHEME');

  /// The WebSocket address, or null when real-time is not configured.
  static Uri? get socketUri => resolve(
    key: _key,
    host: _host,
    port: _port,
    scheme: _scheme,
    release: kReleaseMode,
  );

  @visibleForTesting
  static Uri? resolve({
    required String key,
    required String host,
    required String port,
    required String scheme,
    required bool release,
  }) {
    if (key.isEmpty) {
      return null;
    }
    final String resolvedHost = host.isNotEmpty
        ? host
        : (release ? '' : '127.0.0.1');
    final String resolvedScheme = scheme.isNotEmpty
        ? scheme.toLowerCase()
        : (release ? 'https' : 'http');
    if (resolvedHost.isEmpty || (release && resolvedScheme != 'https')) {
      return null;
    }
    final bool secure = resolvedScheme == 'https';
    return Uri(
      scheme: secure ? 'wss' : 'ws',
      host: resolvedHost,
      port: int.tryParse(port) ?? (secure ? 443 : 8080),
      path: '/app/$key',
      queryParameters: const <String, String>{
        'protocol': '7',
        'client': 'dory-flutter',
        'version': '1.0',
      },
    );
  }
}
