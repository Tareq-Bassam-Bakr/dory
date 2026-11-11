import 'package:dio/dio.dart';
import 'package:dory_app/core/network/json.dart';

/// What went wrong with an API call, independent of HTTP details.
enum ApiErrorKind {
  /// No connection to the server (offline, DNS, refused).
  network,

  /// The server did not answer in time.
  timeout,

  /// 401: missing, expired or revoked token, or wrong credentials.
  unauthorized,

  /// 403: signed in but not allowed (or the account is inactive).
  forbidden,

  /// 404: the resource does not exist or is not visible to the user.
  notFound,

  /// 409: conflicts such as duplicates or already-used invitations.
  conflict,

  /// 410: expired, e.g. an invitation.
  gone,

  /// 422: input rejected; see [ApiException.fieldErrors].
  validation,

  /// 429: rate limited.
  rateLimited,

  /// 5xx.
  server,

  /// The response was not the documented JSON envelope.
  badResponse,

  /// The request was cancelled by the app.
  cancelled,
}

/// A failed API call, carrying the server's machine-readable `code` and,
/// for validation errors, the field messages.
///
/// [toString] never includes request or response bodies, so tokens and
/// personal data cannot leak through error reports.
class ApiException implements Exception {
  const ApiException(
    this.kind, {
    this.statusCode,
    this.code,
    this.message,
    this.fieldErrors = const <String, List<String>>{},
    this.details = const <String, Object?>{},
  });

  /// Maps a Dio failure, reading the error envelope when there is one:
  /// `{ success: false, message, code, errors, details? }`.
  factory ApiException.fromDio(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.transformTimeout:
        return const ApiException(ApiErrorKind.timeout);
      case DioExceptionType.cancel:
        return const ApiException(ApiErrorKind.cancelled);
      case DioExceptionType.connectionError:
      case DioExceptionType.badCertificate:
        return const ApiException(ApiErrorKind.network);
      case DioExceptionType.unknown:
        return error.response == null
            ? const ApiException(ApiErrorKind.network)
            : ApiException._fromResponse(error.response!);
      case DioExceptionType.badResponse:
        return ApiException._fromResponse(error.response!);
    }
  }

  factory ApiException._fromResponse(Response<Object?> response) {
    final int status = response.statusCode ?? 0;
    String? code;
    String? message;
    Map<String, List<String>> fieldErrors = const <String, List<String>>{};
    JsonMap details = const <String, Object?>{};

    final Object? body = response.data;
    if (body is Map) {
      final JsonMap json = asJsonMap(body);
      code = json.stringOrNull('code');
      message = json.stringOrNull('message');
      details = json.objectOrNull('details') ?? const <String, Object?>{};
      final Object? errors = json['errors'];
      if (errors is Map) {
        fieldErrors = <String, List<String>>{
          for (final MapEntry<Object?, Object?> entry in errors.entries)
            entry.key.toString(): <String>[
              if (entry.value is List)
                for (final Object? item in entry.value! as List)
                  if (item is String) item,
            ],
        };
      }
    }

    return ApiException(
      _kindFor(status),
      statusCode: status,
      code: code,
      message: message,
      fieldErrors: fieldErrors,
      details: details,
    );
  }

  static ApiErrorKind _kindFor(int status) => switch (status) {
    401 => ApiErrorKind.unauthorized,
    403 => ApiErrorKind.forbidden,
    404 => ApiErrorKind.notFound,
    409 => ApiErrorKind.conflict,
    410 => ApiErrorKind.gone,
    422 => ApiErrorKind.validation,
    429 => ApiErrorKind.rateLimited,
    >= 500 => ApiErrorKind.server,
    _ => ApiErrorKind.badResponse,
  };

  final ApiErrorKind kind;
  final int? statusCode;

  /// The API's error code, e.g. `invalid_credentials`, `business_not_found`.
  final String? code;

  /// The API's English message. Show localized text based on [code]
  /// instead; this is for diagnostics.
  final String? message;

  /// Validation messages per field (422 only).
  final Map<String, List<String>> fieldErrors;

  /// Machine-readable context of some errors, e.g. a plan limit:
  /// `{limit: branches, max: 1, used: 1, plan: free}`.
  final JsonMap details;

  /// The first validation message for [field], if any.
  String? firstErrorFor(String field) {
    final List<String>? messages = fieldErrors[field];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  bool get isConnectivityProblem =>
      kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  @override
  String toString() => 'ApiException(${kind.name}, $statusCode, $code)';
}
