import 'package:flutter/foundation.dart';

/// Where the DORY API lives and how long requests may take.
///
/// The base URL comes from `--dart-define=DORY_API_BASE_URL=...`. Debug and
/// profile builds fall back to `http://127.0.0.1:8000/api/v1`, which reaches
/// the development machine from a USB-connected phone after
/// `adb reverse tcp:8000 tcp:8000` (see docs/FLUTTER_API_INTEGRATION.md).
/// Release builds have no fallback: they must be given an HTTPS URL.
abstract final class ApiConfig {
  static const String _definedBaseUrl = String.fromEnvironment(
    'DORY_API_BASE_URL',
  );

  static const String developmentBaseUrl = 'http://127.0.0.1:8000/api/v1';

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
  static const Duration sendTimeout = Duration(seconds: 20);

  /// The configured base URL, without a trailing slash.
  ///
  /// Throws a [StateError] in release builds when no URL was configured,
  /// rather than silently talking to a development address.
  static String get baseUrl {
    final String url = _definedBaseUrl.isNotEmpty
        ? _definedBaseUrl
        : (kReleaseMode ? '' : developmentBaseUrl);
    if (url.isEmpty) {
      throw StateError(
        'DORY_API_BASE_URL is not set. Build release apps with '
        '--dart-define=DORY_API_BASE_URL=https://<api-host>/api/v1',
      );
    }
    return url.endsWith('/') ? url.substring(0, url.length - 1) : url;
  }
}
