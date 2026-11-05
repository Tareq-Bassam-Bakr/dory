import 'package:flutter/foundation.dart';

/// Branch fields entered in forms (onboarding step 3, create/edit branch).
/// Empty strings mean "not provided".
@immutable
class BranchDraft {
  const BranchDraft({
    this.name = '',
    this.address = '',
    this.city = '',
    this.region = '',
    this.country = '',
    this.latitude = '',
    this.longitude = '',
    this.phone = '',
    this.email = '',
    this.timezone = '',
    this.isActive = true,
  });

  final String name;
  final String address;
  final String city;
  final String region;

  /// ISO 3166-1 alpha-2, e.g. SY.
  final String country;

  /// Decimal degrees as typed (WGS 84, OpenStreetMap compatible).
  final String latitude;
  final String longitude;
  final String phone;
  final String email;
  final String timezone;
  final bool isActive;

  BranchDraft copyWith({
    String? name,
    String? address,
    String? city,
    String? region,
    String? country,
    String? latitude,
    String? longitude,
    String? phone,
    String? email,
    String? timezone,
    bool? isActive,
  }) => BranchDraft(
    name: name ?? this.name,
    address: address ?? this.address,
    city: city ?? this.city,
    region: region ?? this.region,
    country: country ?? this.country,
    latitude: latitude ?? this.latitude,
    longitude: longitude ?? this.longitude,
    phone: phone ?? this.phone,
    email: email ?? this.email,
    timezone: timezone ?? this.timezone,
    isActive: isActive ?? this.isActive,
  );
}

/// A service entered in forms.
@immutable
class ServiceDraft {
  const ServiceDraft({
    required this.name,
    this.description = '',
    this.durationMinutes = 15,
    this.isActive = true,
  });

  final String name;
  final String description;
  final int durationMinutes;
  final bool isActive;
}

/// Everything the onboarding flow collects before creating the business.
@immutable
class BusinessDraft {
  const BusinessDraft({
    this.name = '',
    this.categorySlug,
    this.description = '',
    this.email = '',
    this.phone = '',
    this.website = '',
    this.branch = const BranchDraft(),
    this.services = const <ServiceDraft>[],
  });

  final String name;
  final String? categorySlug;
  final String description;
  final String email;
  final String phone;
  final String website;
  final BranchDraft branch;
  final List<ServiceDraft> services;

  BusinessDraft copyWith({
    String? name,
    String? categorySlug,
    String? description,
    String? email,
    String? phone,
    String? website,
    BranchDraft? branch,
    List<ServiceDraft>? services,
  }) => BusinessDraft(
    name: name ?? this.name,
    categorySlug: categorySlug ?? this.categorySlug,
    description: description ?? this.description,
    email: email ?? this.email,
    phone: phone ?? this.phone,
    website: website ?? this.website,
    branch: branch ?? this.branch,
    services: services ?? this.services,
  );
}

/// Profile fields changed on the edit screen; null means unchanged.
@immutable
class BusinessProfileChanges {
  const BusinessProfileChanges({
    this.name,
    this.categorySlug,
    this.description,
    this.email,
    this.phone,
    this.website,
    this.timezone,
    this.isActive,
  });

  final String? name;
  final String? categorySlug;

  /// Empty string clears the field.
  final String? description;
  final String? email;
  final String? phone;
  final String? website;
  final String? timezone;

  /// Owner only: active or inactive.
  final bool? isActive;
}
