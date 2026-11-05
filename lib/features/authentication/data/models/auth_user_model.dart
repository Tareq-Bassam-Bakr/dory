import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/authentication/domain/entities/auth_user.dart';
import 'package:dory_app/shared/enums/business_role.dart';

/// Maps the API's user resource to [AuthUser] and back.
///
/// [toJson] writes the same shape the API sends, so the cached copy on the
/// device is read with the same [fromJson].
abstract final class AuthUserModel {
  static AuthUser fromJson(JsonMap json) {
    return AuthUser(
      id: json.string('id'),
      name: json.string('name'),
      email: json.string('email'),
      phone: json.stringOrNull('phone'),
      locale: json.stringOrNull('locale') ?? 'en',
      timezone: json.stringOrNull('timezone') ?? 'UTC',
      emailVerified: json.stringOrNull('email_verified_at') != null,
      memberships: <UserMembership>[
        for (final JsonMap membership in json.objectList('memberships'))
          if (membership.objectOrNull('business') != null)
            _membershipFromJson(membership),
      ],
    );
  }

  static JsonMap toJson(AuthUser user) => <String, Object?>{
    'id': user.id,
    'name': user.name,
    'email': user.email,
    'phone': user.phone,
    'locale': user.locale,
    'timezone': user.timezone,
    // Only presence matters to fromJson.
    'email_verified_at': user.emailVerified ? 'verified' : null,
    'memberships': <JsonMap>[
      for (final UserMembership membership in user.memberships)
        <String, Object?>{
          'business': <String, Object?>{
            'id': membership.businessId,
            'name': membership.businessName,
          },
          'role': membership.role.apiValue,
          'status': membership.status,
        },
    ],
  };

  static UserMembership _membershipFromJson(JsonMap json) {
    final JsonMap business = json.object('business');
    return UserMembership(
      businessId: business.string('id'),
      businessName: business.string('name'),
      role: BusinessRole.fromApi(json.stringOrNull('role')),
      status: json.stringOrNull('status') ?? 'active',
    );
  }
}
