import 'package:dio/dio.dart';

/// Adds the session's bearer token to API requests and reports when the
/// server rejects it.
///
/// [readToken] is read on every request, so login and logout take effect
/// immediately. [onUnauthorized] runs when a request that carried a token
/// receives 401: the token expired or was revoked on the server.
class AuthTokenInterceptor extends Interceptor {
  AuthTokenInterceptor({
    required this._readToken,
    required this._onUnauthorized,
  });

  final String? Function() _readToken;
  final void Function() _onUnauthorized;

  static const String _authorization = 'Authorization';

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final String? token = _readToken();
    if (token != null && token.isNotEmpty) {
      options.headers[_authorization] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final bool hadToken = err.requestOptions.headers.containsKey(
      _authorization,
    );
    if (err.response?.statusCode == 401 && hadToken) {
      _onUnauthorized();
    }
    handler.next(err);
  }
}
