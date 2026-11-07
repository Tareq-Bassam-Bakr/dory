import 'dart:convert';

import 'package:dory_app/core/location/location_service.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:material_ui/material_ui.dart';

/// [LocationService] for tests: no GPS hardware, and every call is recorded.
class FakeLocationService implements LocationService {
  FakeLocationService({
    this.access = LocationAccess.denied,
    this.afterRequest,
    this.position,
  });

  /// What [checkAccess] answers.
  LocationAccess access;

  /// What [requestAccess] answers; defaults to [access].
  LocationAccess? afterRequest;
  GeoPoint? position;

  int checks = 0;
  int requests = 0;
  int readings = 0;
  int appSettingsOpened = 0;
  int locationSettingsOpened = 0;

  @override
  Future<LocationAccess> checkAccess() async {
    checks++;
    return access;
  }

  @override
  Future<LocationAccess> requestAccess() async {
    requests++;
    return access = afterRequest ?? access;
  }

  @override
  Future<GeoPoint?> currentPosition() async {
    readings++;
    return position;
  }

  @override
  Future<void> openAppSettings() async => appSettingsOpened++;

  @override
  Future<void> openLocationSettings() async => locationSettingsOpened++;
}

/// Serves a transparent pixel for every tile, so map tests never touch the
/// network (and never touch a public tile server).
class OfflineTileProvider extends TileProvider {
  static final MemoryImage _pixel = MemoryImage(
    base64Decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    ),
  );

  @override
  ImageProvider getImage(TileCoordinates coordinates, TileLayer options) =>
      _pixel;
}
