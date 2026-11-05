import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:flutter/foundation.dart';

/// A branch of a business. Coordinates are WGS 84 decimal degrees
/// (OpenStreetMap compatible).
@immutable
class Branch {
  const Branch({
    required this.id,
    required this.name,
    required this.timezone,
    required this.isActive,
    this.businessId,
    this.address,
    this.city,
    this.region,
    this.country,
    this.latitude,
    this.longitude,
    this.phone,
    this.email,
    this.servicesCount,
    this.canUpdate = false,
    this.canDelete = false,
    this.canManageServices = false,
  });

  final String id;
  final String? businessId;
  final String name;
  final String? address;
  final String? city;
  final String? region;
  final String? country;
  final double? latitude;
  final double? longitude;
  final String? phone;
  final String? email;
  final String timezone;
  final bool isActive;
  final int? servicesCount;

  // Permission hints from the server.
  final bool canUpdate;
  final bool canDelete;
  final bool canManageServices;

  bool get hasLocation => latitude != null && longitude != null;

  /// The editable fields, as a form draft.
  BranchDraft toDraft() => BranchDraft(
    name: name,
    address: address ?? '',
    city: city ?? '',
    region: region ?? '',
    country: country ?? '',
    latitude: latitude?.toString() ?? '',
    longitude: longitude?.toString() ?? '',
    phone: phone ?? '',
    email: email ?? '',
    timezone: timezone,
    isActive: isActive,
  );
}
