import 'package:dio/dio.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/api_exception.dart';
import 'package:dory_app/core/network/json.dart';

/// Lets a caller abandon a request that is no longer needed, such as a
/// search the customer has already replaced with a newer one. Cancelled
/// requests fail with [ApiErrorKind.cancelled].
class ApiCancellation {
  final CancelToken _token = CancelToken();

  void cancel() {
    if (!_token.isCancelled) {
      _token.cancel();
    }
  }
}

/// Thin wrapper around [Dio] for the DORY API.
///
/// Every call returns the parsed success envelope or throws an
/// [ApiException]; callers never see Dio types. Data sources use this
/// class; widgets never do.
class ApiClient {
  const ApiClient(this._dio);

  final Dio _dio;

  Future<ApiEnvelope> get(
    String path, {
    Map<String, Object?>? query,
    Map<String, String>? headers,
    ApiCancellation? cancellation,
  }) {
    return _send(
      () => _dio.get<Object?>(
        path,
        queryParameters: query,
        options: headers == null ? null : Options(headers: headers),
        cancelToken: cancellation?._token,
      ),
    );
  }

  Future<ApiEnvelope> post(
    String path, {
    Object? body,
    Map<String, String>? headers,
  }) {
    return _send(
      () => _dio.post<Object?>(
        path,
        data: body,
        options: headers == null ? null : Options(headers: headers),
      ),
    );
  }

  Future<ApiEnvelope> put(String path, {Object? body}) {
    return _send(() => _dio.put<Object?>(path, data: body));
  }

  Future<ApiEnvelope> delete(
    String path, {
    Object? body,
    Map<String, String>? headers,
  }) {
    return _send(
      () => _dio.delete<Object?>(
        path,
        data: body,
        options: headers == null ? null : Options(headers: headers),
      ),
    );
  }

  /// A POST whose answer is not the API envelope: Laravel's broadcasting
  /// endpoint answers `{"auth": "..."}` (docs/BROADCAST_CHANNELS.md).
  Future<JsonMap> postRaw(String path, {Object? body}) async {
    final Response<Object?> response;
    try {
      response = await _dio.post<Object?>(path, data: body);
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }
    try {
      return asJsonMap(response.data, context: 'response');
    } on FormatException {
      throw ApiException(
        ApiErrorKind.badResponse,
        statusCode: response.statusCode,
      );
    }
  }

  Future<ApiEnvelope> _send(
    Future<Response<Object?>> Function() request,
  ) async {
    final Response<Object?> response;
    try {
      response = await request();
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }

    final Object? body = response.data;
    try {
      return ApiEnvelope.fromJson(asJsonMap(body, context: 'response'));
    } on FormatException {
      throw ApiException(
        ApiErrorKind.badResponse,
        statusCode: response.statusCode,
      );
    }
  }
}
