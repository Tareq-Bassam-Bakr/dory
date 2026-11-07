import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Privacy guarantees that must not regress without someone noticing.
void main() {
  List<File> dartFiles() => Directory('lib')
      .listSync(recursive: true)
      .whereType<File>()
      .where((file) => file.path.endsWith('.dart'))
      .where((file) => !file.path.contains('generated'))
      .toList();

  test('Android declares no background location access', () {
    final String manifest = File(
      'android/app/src/main/AndroidManifest.xml',
    ).readAsStringSync();

    expect(manifest, isNot(contains('ACCESS_BACKGROUND_LOCATION')));
    expect(manifest, isNot(contains('FOREGROUND_SERVICE')));
    expect(manifest, contains('ACCESS_COARSE_LOCATION'));
  });

  test('iOS asks for while-in-use location only', () {
    final String plist = File('ios/Runner/Info.plist').readAsStringSync();

    expect(plist, contains('NSLocationWhenInUseUsageDescription'));
    expect(plist, isNot(contains('NSLocationAlways')));
    expect(plist, isNot(contains('UIBackgroundModes')));
  });

  test('the app never subscribes to continuous location updates', () {
    for (final File file in dartFiles()) {
      final String source = file.readAsStringSync();

      expect(
        source,
        isNot(contains('getPositionStream')),
        reason: '${file.path} tracks location continuously',
      );
      expect(
        source,
        isNot(contains('getServiceStatusStream')),
        reason: file.path,
      );
    }
  });

  test('only the location service touches the location plugin', () {
    for (final File file in dartFiles()) {
      if (file.path.endsWith('location_service.dart')) {
        continue;
      }
      expect(
        file.readAsStringSync(),
        isNot(contains('package:geolocator')),
        reason: file.path,
      );
    }
  });

  test('no paid map key or provider is written in the code', () {
    final String config = File(
      'lib/core/config/map_config.dart',
    ).readAsStringSync();

    expect(config, isNot(contains('api_key=')));
    expect(config, isNot(contains('access_token=')));
    expect(config, isNot(contains('maps.googleapis.com')));
  });
}
