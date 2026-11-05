import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// A canned HTTP response for [FakeHttpAdapter].
class FakeResponse {
  const FakeResponse(this.status, this.body);

  /// The API success envelope around [data].
  factory FakeResponse.ok(
    Object? data, {
    int status = 200,
    Map<String, Object?>? meta,
  }) => FakeResponse(status, <String, Object?>{
    'success': true,
    'message': 'OK',
    'data': data,
    'meta': meta,
  });

  /// The API error envelope.
  factory FakeResponse.error(
    int status,
    String code, {
    Map<String, List<String>>? errors,
  }) => FakeResponse(status, <String, Object?>{
    'success': false,
    'message': code,
    'code': code,
    'errors': errors,
  });

  /// A request that never reaches a server (offline).
  static const FakeResponse offline = FakeResponse(-1, null);

  final int status;
  final Object? body;
}

typedef FakeHandler = FakeResponse Function(RequestOptions request);

/// In-memory HTTP transport for Dio: routes "METHOD /path" (the path after
/// /api/v1) to handlers and records every request, so tests can drive the
/// real API client, interceptors and repositories without a server.
class FakeHttpAdapter implements HttpClientAdapter {
  final Map<String, FakeHandler> _routes = <String, FakeHandler>{};

  /// Every request sent, in order.
  final List<RequestOptions> requests = <RequestOptions>[];

  void on(String method, String path, FakeHandler handler) {
    _routes['${method.toUpperCase()} $path'] = handler;
  }

  void reply(String method, String path, FakeResponse response) {
    on(method, path, (_) => response);
  }

  /// Requests to [method] [path].
  List<RequestOptions> sent(String method, String path) => <RequestOptions>[
    for (final RequestOptions request in requests)
      if (request.method == method.toUpperCase() && _pathOf(request) == path)
        request,
  ];

  static String _pathOf(RequestOptions request) {
    final String path = request.uri.path;
    const String prefix = '/api/v1';
    return path.startsWith(prefix) ? path.substring(prefix.length) : path;
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final FakeHandler? handler =
        _routes['${options.method} ${_pathOf(options)}'];
    final FakeResponse response =
        handler?.call(options) ?? FakeResponse.error(404, 'not_found');

    if (response.status < 0) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'Simulated offline device',
      );
    }

    return ResponseBody.fromString(
      jsonEncode(response.body),
      response.status,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
