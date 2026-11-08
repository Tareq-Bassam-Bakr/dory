import 'dart:async';
import 'dart:ui' show PlatformDispatcher;

import 'package:dio/dio.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/core/config/api_config.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/auth_token_interceptor.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// For providers that load from the API. Riverpod would otherwise retry
/// every failure in the background, including a 404 or a refused request.
/// Screens show the failure with a retry button instead.
Duration? noAutomaticRetry(int attempt, Object error) => null;

/// The API base URL. Overridden in tests.
final Provider<String> apiBaseUrlProvider = Provider<String>(
  (ref) => ApiConfig.baseUrl,
  name: 'apiBaseUrlProvider',
);

/// In-memory copy of the current session token, read by the HTTP layer on
/// every request. Persistence lives in the authentication feature.
class SessionTokenHolder {
  String? token;
}

final Provider<SessionTokenHolder> sessionTokenHolderProvider =
    Provider<SessionTokenHolder>(
      (ref) => SessionTokenHolder(),
      name: 'sessionTokenHolderProvider',
    );

/// Broadcasts "the server rejected our token" (HTTP 401 on an
/// authenticated request). The authentication controller listens and ends
/// the session; the HTTP layer never depends on the controller directly.
class SessionEvents {
  final StreamController<void> _expired = StreamController<void>.broadcast();

  Stream<void> get expired => _expired.stream;

  void notifyExpired() {
    if (!_expired.isClosed) {
      _expired.add(null);
    }
  }

  Future<void> dispose() => _expired.close();
}

final Provider<SessionEvents> sessionEventsProvider = Provider<SessionEvents>((
  ref,
) {
  final SessionEvents events = SessionEvents();
  ref.onDispose(events.dispose);
  return events;
}, name: 'sessionEventsProvider');

/// Replaces Dio's HTTP transport. Null (the platform transport) in the app;
/// tests inject a fake to exercise the real API stack without a server.
final Provider<HttpClientAdapter?> httpClientAdapterProvider =
    Provider<HttpClientAdapter?>(
      (ref) => null,
      name: 'httpClientAdapterProvider',
    );

/// The configured [Dio] instance. No logging interceptor is installed:
/// requests carry tokens and passwords.
final Provider<Dio> dioProvider = Provider<Dio>((ref) {
  final Dio dio = Dio(
    BaseOptions(
      baseUrl: ref.watch(apiBaseUrlProvider),
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.receiveTimeout,
      sendTimeout: ApiConfig.sendTimeout,
      contentType: Headers.jsonContentType,
      responseType: ResponseType.json,
      headers: <String, Object>{'Accept': 'application/json'},
    ),
  );

  final HttpClientAdapter? adapter = ref.watch(httpClientAdapterProvider);
  if (adapter != null) {
    dio.httpClientAdapter = adapter;
  }

  final SessionTokenHolder tokens = ref.watch(sessionTokenHolderProvider);
  final SessionEvents events = ref.watch(sessionEventsProvider);

  dio.interceptors.addAll(<Interceptor>[
    InterceptorsWrapper(
      onRequest: (options, handler) {
        // The chosen language, else the device's: the server renders
        // notification texts in it.
        final Locale locale =
            ref.read(languageControllerProvider).locale ??
            PlatformDispatcher.instance.locale;
        options.headers['Accept-Language'] = locale.languageCode;
        handler.next(options);
      },
    ),
    AuthTokenInterceptor(
      readToken: () => tokens.token,
      onUnauthorized: events.notifyExpired,
    ),
  ]);

  ref.onDispose(dio.close);
  return dio;
}, name: 'dioProvider');

final Provider<ApiClient> apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient(ref.watch(dioProvider)),
  name: 'apiClientProvider',
);
