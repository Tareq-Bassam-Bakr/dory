import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

/// WGS 84 coordinates.
@immutable
class GeoPoint {
  const GeoPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;

  @override
  bool operator ==(Object other) =>
      other is GeoPoint &&
      other.latitude == latitude &&
      other.longitude == longitude;

  @override
  int get hashCode => Object.hash(latitude, longitude);
}

/// Whether the app may read the device's position.
enum LocationAccess {
  granted,
  denied,

  /// Denied with "don't ask again": only system settings can change it.
  permanentlyDenied,

  /// Location is switched off on the device.
  serviceDisabled,
}

/// The device's location, behind an interface so tests never need GPS.
///
/// Only while-in-use access exists here, a single reading is taken when the
/// customer asks for it, and nothing keeps listening afterwards. The app has
/// no background location permission (docs/MAPS_AND_LOCATION.md).
abstract interface class LocationService {
  /// The current access, without asking the customer.
  Future<LocationAccess> checkAccess();

  /// Shows the system prompt if it may still be shown.
  Future<LocationAccess> requestAccess();

  /// One approximate reading, or null when none could be obtained.
  Future<GeoPoint?> currentPosition();

  Future<void> openAppSettings();

  Future<void> openLocationSettings();
}

/// [LocationService] backed by geolocator.
class GeolocatorLocationService implements LocationService {
  const GeolocatorLocationService();

  static const Duration _readingTimeout = Duration(seconds: 12);

  @override
  Future<LocationAccess> checkAccess() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return LocationAccess.serviceDisabled;
    }
    return _access(await Geolocator.checkPermission());
  }

  @override
  Future<LocationAccess> requestAccess() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return LocationAccess.serviceDisabled;
    }
    return _access(await Geolocator.requestPermission());
  }

  @override
  Future<GeoPoint?> currentPosition() async {
    try {
      // Low accuracy is enough to sort by distance, and also works when the
      // customer granted only approximate location.
      final Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.low,
          timeLimit: _readingTimeout,
        ),
      );
      return GeoPoint(position.latitude, position.longitude);
    } on Exception {
      final Position? last = await Geolocator.getLastKnownPosition();
      return last == null ? null : GeoPoint(last.latitude, last.longitude);
    }
  }

  @override
  Future<void> openAppSettings() => Geolocator.openAppSettings();

  @override
  Future<void> openLocationSettings() => Geolocator.openLocationSettings();

  LocationAccess _access(LocationPermission permission) => switch (permission) {
    LocationPermission.always ||
    LocationPermission.whileInUse => LocationAccess.granted,
    LocationPermission.deniedForever => LocationAccess.permanentlyDenied,
    LocationPermission.denied ||
    LocationPermission.unableToDetermine => LocationAccess.denied,
  };
}

final Provider<LocationService> locationServiceProvider =
    Provider<LocationService>(
      (ref) => const GeolocatorLocationService(),
      name: 'locationServiceProvider',
    );
