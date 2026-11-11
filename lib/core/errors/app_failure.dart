import 'package:dory_app/core/network/api_exception.dart';
import 'package:flutter/foundation.dart';

/// Why a business-management action failed, independent of HTTP.
enum FailureKind {
  network,
  unauthorized,
  forbidden,
  notFound,
  conflict,
  gone,
  validation,
  rateLimited,
  server,
  unknown,
}

/// A failed repository call. Repositories throw this, never transport
/// exceptions, so the domain and presentation layers stay HTTP-agnostic.
@immutable
class AppFailure implements Exception {
  const AppFailure(
    this.kind, {
    this.code,
    this.fieldErrors = const <String, String>{},
    this.details = const <String, Object?>{},
  });

  factory AppFailure.fromApi(ApiException error) {
    final FailureKind kind = switch (error.kind) {
      ApiErrorKind.network || ApiErrorKind.timeout => FailureKind.network,
      ApiErrorKind.unauthorized => FailureKind.unauthorized,
      ApiErrorKind.forbidden => FailureKind.forbidden,
      ApiErrorKind.notFound => FailureKind.notFound,
      ApiErrorKind.conflict => FailureKind.conflict,
      ApiErrorKind.gone => FailureKind.gone,
      ApiErrorKind.validation => FailureKind.validation,
      ApiErrorKind.rateLimited => FailureKind.rateLimited,
      ApiErrorKind.server => FailureKind.server,
      ApiErrorKind.badResponse || ApiErrorKind.cancelled => FailureKind.unknown,
    };
    return AppFailure(
      kind,
      code: error.code,
      fieldErrors: <String, String>{
        for (final MapEntry<String, List<String>> entry
            in error.fieldErrors.entries)
          if (entry.value.isNotEmpty) entry.key: entry.value.first,
      },
      details: error.details,
    );
  }

  /// Runs [action], converting API and parsing errors into [AppFailure].
  static Future<T> guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on ApiException catch (error) {
      throw AppFailure.fromApi(error);
    } on FormatException {
      throw const AppFailure(FailureKind.unknown);
    }
  }

  final FailureKind kind;

  /// The API's error code (e.g. `business_suspended`, `invitation_pending`).
  final String? code;

  /// First server message per field, for validation failures.
  final Map<String, String> fieldErrors;

  /// The API's machine-readable context, e.g. for `plan_limit_reached`:
  /// `{limit: branches, max: 1, used: 1, plan: free}`.
  final Map<String, Object?> details;

  /// The business's plan does not allow adding one more (docs/SUBSCRIPTIONS.md).
  bool get isPlanLimit => code == 'plan_limit_reached';

  /// The business's plan does not include the feature.
  bool get isFeatureNotInPlan => code == 'feature_not_in_plan';

  @override
  String toString() => 'AppFailure(${kind.name}, $code)';
}
