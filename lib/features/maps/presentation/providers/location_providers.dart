import 'dart:async';
import 'dart:convert';

import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Where the customer wants results for.
@immutable
class CustomerLocation {
  const CustomerLocation({
    this.point,
    this.city,
    this.country,
    this.isDevice = false,
    this.radiusKm = defaultRadiusKm,
    this.access,
    this.isLocating = false,
  });

  static const double defaultRadiusKm = 10;

  /// Radiuses a customer can choose; the server allows up to 100 km.
  static const List<double> radiusChoices = <double>[5, 10, 25, 50, 100];

  final GeoPoint? point;

  /// The city chosen by hand.
  final String? city;
  final String? country;

  /// [point] came from the device. It is kept in memory only.
  final bool isDevice;
  final double radiusKm;

  /// The answer to the last request for access, if any.
  final LocationAccess? access;
  final bool isLocating;

  bool get isKnown => point != null;

  /// The area to search, or null while no location is known.
  SearchArea? get area {
    final GeoPoint? known = point;
    return known == null ? null : SearchArea(point: known, radiusKm: radiusKm);
  }
}

/// The customer's location for this session.
///
/// Location is never required: without it everything still works, and a
/// city can be chosen by hand. Device access is requested only from
/// [useDevice], which runs when the customer asks for it, after the screen
/// explained why. A chosen city is remembered; a device position is not.
class CustomerLocationController extends Notifier<CustomerLocation> {
  KeyValueStorage get _storage => ref.read(keyValueStorageProvider);

  @override
  CustomerLocation build() {
    final KeyValueStorage storage = ref.watch(keyValueStorageProvider);
    final double radius =
        double.tryParse(storage.readString(StorageKeys.searchRadiusKm) ?? '') ??
        CustomerLocation.defaultRadiusKm;
    final String? stored = storage.readString(StorageKeys.chosenCity);
    if (stored == null) {
      return CustomerLocation(radiusKm: radius);
    }
    try {
      final JsonMap json = asJsonMap(jsonDecode(stored));
      return CustomerLocation(
        point: GeoPoint(
          json.doubleOrNull('latitude') ?? 0,
          json.doubleOrNull('longitude') ?? 0,
        ),
        city: json.string('city'),
        country: json.stringOrNull('country'),
        radiusKm: radius,
      );
    } on FormatException {
      return CustomerLocation(radiusKm: radius);
    }
  }

  /// Asks for access if needed and takes one reading. Returns the access, so
  /// the screen can explain a refusal and offer the manual choice instead.
  Future<LocationAccess> useDevice() async {
    final LocationService service = ref.read(locationServiceProvider);
    state = _copy(isLocating: true);

    LocationAccess access = await service.checkAccess();
    if (access == LocationAccess.denied) {
      access = await service.requestAccess();
    }
    if (access != LocationAccess.granted) {
      state = _copy(access: access);
      return access;
    }

    final GeoPoint? reading = await service.currentPosition();
    final GeoPoint? position = reading == null ? null : _rounded(reading);
    state = position == null
        ? _copy(access: access)
        : CustomerLocation(
            point: position,
            isDevice: true,
            radiusKm: state.radiusKm,
            access: access,
          );
    if (position != null) {
      await _storage.remove(StorageKeys.chosenCity);
    }
    return access;
  }

  /// About 100 metres: enough to sort by distance, and all the server gets.
  /// Coordinates travel in query strings, so they may reach access logs.
  static GeoPoint _rounded(GeoPoint point) => GeoPoint(
    double.parse(point.latitude.toStringAsFixed(3)),
    double.parse(point.longitude.toStringAsFixed(3)),
  );

  /// Uses a city chosen by hand. Needs no permission at all.
  Future<void> chooseCity(DiscoveryCity city) async {
    final GeoPoint? point = city.location;
    if (point == null) {
      return;
    }
    state = CustomerLocation(
      point: point,
      city: city.city,
      country: city.country,
      radiusKm: state.radiusKm,
      access: state.access,
    );
    await _storage.writeString(
      StorageKeys.chosenCity,
      jsonEncode(<String, Object?>{
        'city': city.city,
        'country': city.country,
        'latitude': point.latitude,
        'longitude': point.longitude,
      }),
    );
  }

  Future<void> setRadius(double radiusKm) async {
    state = CustomerLocation(
      point: state.point,
      city: state.city,
      country: state.country,
      isDevice: state.isDevice,
      radiusKm: radiusKm,
      access: state.access,
    );
    await _storage.writeString(StorageKeys.searchRadiusKm, '$radiusKm');
  }

  Future<void> clear() async {
    state = CustomerLocation(radiusKm: state.radiusKm);
    await _storage.remove(StorageKeys.chosenCity);
  }

  CustomerLocation _copy({LocationAccess? access, bool isLocating = false}) =>
      CustomerLocation(
        point: state.point,
        city: state.city,
        country: state.country,
        isDevice: state.isDevice,
        radiusKm: state.radiusKm,
        access: access ?? state.access,
        isLocating: isLocating,
      );
}

final NotifierProvider<CustomerLocationController, CustomerLocation>
customerLocationProvider =
    NotifierProvider<CustomerLocationController, CustomerLocation>(
      CustomerLocationController.new,
      name: 'customerLocationProvider',
    );
