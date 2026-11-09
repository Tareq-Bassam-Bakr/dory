import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter/foundation.dart';

/// Lifecycle state of a business.
enum BusinessStatus {
  active,
  inactive,
  suspended,
  archived;

  static BusinessStatus fromApi(String? value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => suspended,
  );
}

/// A business the signed-in user belongs to, as seen by them.
@immutable
class Business {
  const Business({
    required this.id,
    required this.name,
    required this.slug,
    required this.status,
    required this.timezone,
    required this.permissions,
    this.description,
    this.category,
    this.email,
    this.phone,
    this.website,
    this.role,
    this.stats,
    this.profile,
  });

  /// Public id (ULID).
  final String id;
  final String name;
  final String slug;
  final String? description;
  final BusinessCategory? category;
  final String? email;
  final String? phone;
  final String? website;
  final String timezone;
  final BusinessStatus status;

  /// The viewer's role here.
  final BusinessRole? role;
  final BusinessStats? stats;
  final ProfileCompletion? profile;

  /// What the server allows the viewer to do. UI hints only: the API
  /// checks every action again.
  final BusinessPermissions permissions;

  bool get isSuspended => status == BusinessStatus.suspended;
}

@immutable
class BusinessStats {
  const BusinessStats({
    required this.branches,
    required this.services,
    required this.members,
  });

  final int branches;
  final int services;
  final int members;
}

/// Profile steps the server reports as missing:
/// basics, description, contact, branch, service.
@immutable
class ProfileCompletion {
  const ProfileCompletion({
    required this.percentage,
    required this.isComplete,
    required this.missing,
  });

  final int percentage;
  final bool isComplete;
  final List<String> missing;
}

@immutable
class BusinessPermissions {
  const BusinessPermissions({
    this.update = false,
    this.changeStatus = false,
    this.delete = false,
    this.createBranches = false,
    this.viewMembers = false,
    this.manageMembers = false,
    this.viewInsights = false,
  });

  final bool update;
  final bool changeStatus;
  final bool delete;
  final bool createBranches;
  final bool viewMembers;
  final bool manageMembers;

  /// Insights and peak times (owners and managers).
  final bool viewInsights;
}
