import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_location.dart';
import '../../helpers/test_app.dart';

void main() {
  const GeoPoint here = GeoPoint(33.5138, 36.2765);
  const DiscoveryCity homs = DiscoveryCity(
    city: 'Homs',
    country: 'SY',
    branchesCount: 2,
    location: GeoPoint(34.73, 36.72),
  );

  late InMemoryKeyValueStorage storage;

  setUp(() => storage = InMemoryKeyValueStorage());

  ProviderContainer container(FakeLocationService location) =>
      createTestContainer(storage: storage, location: location);

  CustomerLocationController controller(ProviderContainer container) =>
      container.read(customerLocationProvider.notifier);

  test('nothing is asked or read until the customer requests it', () {
    final FakeLocationService location = FakeLocationService();

    final CustomerLocation state = container(
      location,
    ).read(customerLocationProvider);

    expect(state.isKnown, isFalse);
    expect(location.checks, 0);
    expect(location.requests, 0);
    expect(location.readings, 0);
  });

  test('permission granted: one reading gives the area', () async {
    final FakeLocationService location = FakeLocationService(
      afterRequest: LocationAccess.granted,
      position: here,
    );
    final ProviderContainer subject = container(location);

    final LocationAccess access = await controller(subject).useDevice();

    final CustomerLocation state = subject.read(customerLocationProvider);
    expect(access, LocationAccess.granted);
    expect(location.requests, 1);
    expect(location.readings, 1);
    expect(state.isDevice, isTrue);
    expect(state.area?.point, const GeoPoint(33.514, 36.276));
    expect(state.area?.radiusKm, CustomerLocation.defaultRadiusKm);
  });

  test('a position is rounded to about 100 metres before it is used', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.granted,
      position: const GeoPoint(33.51384567, 36.27654321),
    );
    final ProviderContainer subject = container(location);

    await controller(subject).useDevice();

    expect(
      subject.read(customerLocationProvider).point,
      const GeoPoint(33.514, 36.277),
    );
  });

  test('access already granted does not prompt again', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.granted,
      position: here,
    );

    await controller(container(location)).useDevice();

    expect(location.requests, 0);
    expect(location.readings, 1);
  });

  test('permission denied leaves everything usable', () async {
    final FakeLocationService location = FakeLocationService();
    final ProviderContainer subject = container(location);

    final LocationAccess access = await controller(subject).useDevice();

    final CustomerLocation state = subject.read(customerLocationProvider);
    expect(access, LocationAccess.denied);
    expect(state.access, LocationAccess.denied);
    expect(state.isKnown, isFalse);
    expect(state.isLocating, isFalse);
    expect(location.readings, 0);
  });

  test('permanently denied is not asked again', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.permanentlyDenied,
    );

    final LocationAccess access = await controller(
      container(location),
    ).useDevice();

    expect(access, LocationAccess.permanentlyDenied);
    expect(location.requests, 0);
  });

  test('location switched off is reported', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.serviceDisabled,
    );

    expect(
      await controller(container(location)).useDevice(),
      LocationAccess.serviceDisabled,
    );
  });

  test('a reading that fails keeps the previous location', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.granted,
    );
    final ProviderContainer subject = container(location);
    await controller(subject).chooseCity(homs);

    await controller(subject).useDevice();

    expect(subject.read(customerLocationProvider).city, 'Homs');
  });

  test('a chosen city needs no permission and survives a restart', () async {
    final FakeLocationService location = FakeLocationService();

    await controller(container(location)).chooseCity(homs);

    expect(location.checks, 0);
    final CustomerLocation restored = container(
      FakeLocationService(),
    ).read(customerLocationProvider);
    expect(restored.city, 'Homs');
    expect(restored.point, homs.location);
    expect(restored.isDevice, isFalse);
  });

  test('a device position is never persisted', () async {
    final FakeLocationService location = FakeLocationService(
      access: LocationAccess.granted,
      position: here,
    );
    final ProviderContainer subject = container(location);
    await controller(subject).chooseCity(homs);

    await controller(subject).useDevice();

    expect(storage.readString(StorageKeys.chosenCity), isNull);
    expect(
      container(FakeLocationService()).read(customerLocationProvider).isKnown,
      isFalse,
    );
  });

  test('the radius is configurable and remembered', () async {
    await controller(container(FakeLocationService())).setRadius(25);

    expect(
      container(FakeLocationService()).read(customerLocationProvider).radiusKm,
      25,
    );
  });

  test('clearing forgets the chosen city', () async {
    final ProviderContainer subject = container(FakeLocationService());
    await controller(subject).chooseCity(homs);

    await controller(subject).clear();

    expect(subject.read(customerLocationProvider).isKnown, isFalse);
    expect(storage.readString(StorageKeys.chosenCity), isNull);
  });
}
