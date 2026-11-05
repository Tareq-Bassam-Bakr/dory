import 'package:dory_app/app/app.dart';
import 'package:dory_app/core/constants/app_constants.dart';
import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/shared_preferences_key_value_storage.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Initializes platform services, then starts [DoryApp].
///
/// Everything that must exist before the first frame is created here and
/// injected through [ProviderScope] overrides.
Future<void> bootstrap() async {
  WidgetsFlutterBinding.ensureInitialized();
  _installErrorHandlers();
  _registerLicenses();

  final KeyValueStorage storage = await _openStorage();

  runApp(
    ProviderScope(
      overrides: [keyValueStorageProvider.overrideWithValue(storage)],
      child: const DoryApp(),
    ),
  );
}

void _installErrorHandlers() {
  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    ErrorReporter.report(
      details.exception,
      details.stack,
      context: 'Flutter framework error',
    );
  };
  PlatformDispatcher.instance.onError = (error, stackTrace) {
    ErrorReporter.report(
      error,
      stackTrace,
      context: 'Uncaught asynchronous error',
    );
    return true;
  };
}

/// The bundled font is licensed under the SIL OFL, which requires the
/// license to ship with it. It is shown on the platform license page.
void _registerLicenses() {
  LicenseRegistry.addLicense(() async* {
    final String license = await rootBundle.loadString(AppAssets.fontLicense);
    yield LicenseEntryWithLineBreaks(const <String>[
      'IBM Plex Sans Arabic',
    ], license);
  });
}

/// Opens persistent storage. If the platform store fails, the app still
/// starts with volatile storage and the failure is reported.
Future<KeyValueStorage> _openStorage() async {
  try {
    return await SharedPreferencesKeyValueStorage.create();
  } on Object catch (error, stackTrace) {
    ErrorReporter.report(
      error,
      stackTrace,
      context: 'Persistent storage unavailable; using in-memory storage',
    );
    return InMemoryKeyValueStorage();
  }
}
