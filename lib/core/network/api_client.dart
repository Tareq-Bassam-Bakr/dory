import 'package:dio/dio.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/api_exception.dart';
import 'package:dory_app/core/network/json.dart';

/// Thin wrapper around [Dio] for the DORY API.
///
/// Every call returns the parsed success envelope or throws an
/// [ApiException]; callers never see Dio types. Data sources use this
/// class; widgets never do.
class ApiClient {
  const ApiClient(this._dio);

  final Dio _dio;

  Future<ApiEnvelope> get(String path, {Map<String, Object?>? query}) {
    return _send(() => _dio.get<Object?>(path, queryParameters: query));
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

  Future<ApiEnvelope> delete(String path) {
    return _send(() => _dio.delete<Object?>(path));
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
