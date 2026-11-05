import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter/foundation.dart';

/// The signed-in user, as returned by `GET /auth/me`.
@immutable
class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.locale,
    required this.timezone,
    this.phone,
    this.emailVerified = false,
    this.memberships = const <UserMembership>[],
  });

  /// Public id (ULID).
  final String id;
  final String name;
  final String email;
  final String? phone;
  final String locale;
  final String timezone;
  final bool emailVerified;
  final List<UserMembership> memberships;

  /// Whether the user works in at least one business.
  bool get hasActiveMemberships => memberships.any((m) => m.isActive);

  AuthUser copyWith({List<UserMembership>? memberships}) => AuthUser(
    id: id,
    name: name,
    email: email,
    phone: phone,
    locale: locale,
    timezone: timezone,
    emailVerified: emailVerified,
    memberships: memberships ?? this.memberships,
  );

  @override
  bool operator ==(Object other) =>
      other is AuthUser &&
      other.id == id &&
      other.name == name &&
      other.email == email &&
      other.phone == phone &&
      other.locale == locale &&
      other.timezone == timezone &&
      other.emailVerified == emailVerified &&
      listEquals(other.memberships, memberships);

  @override
  int get hashCode => Object.hash(
    id,
    name,
    email,
    phone,
    locale,
    timezone,
    emailVerified,
    Object.hashAll(memberships),
  );
}

/// One of the user's business memberships.
@immutable
class UserMembership {
  const UserMembership({
    required this.businessId,
    required this.businessName,
    required this.role,
    required this.status,
  });

  final String businessId;
  final String businessName;
  final BusinessRole role;
  final String status;

  bool get isActive => status == 'active';

  @override
  bool operator ==(Object other) =>
      other is UserMembership &&
      other.businessId == businessId &&
      other.businessName == businessName &&
      other.role == role &&
      other.status == status;

  @override
  int get hashCode => Object.hash(businessId, businessName, role, status);
}
