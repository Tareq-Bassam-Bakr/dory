import 'dart:developer' as developer;

/// Single entry point for reporting unexpected errors.
///
/// Phase 01 writes to the developer log. A crash-reporting backend can be
/// added here later without touching call sites.
abstract final class ErrorReporter {
  static void report(Object error, StackTrace? stackTrace, {String? context}) {
    developer.log(
      context ?? 'Unhandled error',
      name: 'dory.error',
      error: error,
      stackTrace: stackTrace,
      level: 1000, // SEVERE
    );
  }
}
