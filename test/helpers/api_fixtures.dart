/// JSON shaped exactly like the Laravel API resources (see
/// docs/API_ENDPOINTS_PHASE_03.md), for fake HTTP responses.
library;

const String ownerId = '01jbusinessownerxxxxxxxxxx';
const String businessId = '01jbusinessxxxxxxxxxxxxxxx';

Map<String, Object?> userJson({
  String id = ownerId,
  String name = 'Lina Haddad',
  String email = 'lina@dory.test',
  String? phone,
  List<Map<String, Object?>> memberships = const <Map<String, Object?>>[],
}) => <String, Object?>{
  'id': id,
  'name': name,
  'email': email,
  'phone': phone,
  'locale': 'en',
  'timezone': 'UTC',
  'status': 'active',
  'is_platform_admin': false,
  'email_verified_at': null,
  'last_login_at': null,
  'created_at': '2026-11-05T10:00:00+00:00',
  'memberships': memberships,
};

Map<String, Object?> membershipJson({
  String business = businessId,
  String businessName = 'Shifa Clinic',
  String role = 'owner',
}) => <String, Object?>{
  'business': <String, Object?>{
    'id': business,
    'name': businessName,
    'slug': 'shifa-clinic',
    'status': 'active',
  },
  'role': role,
  'status': 'active',
  'joined_at': '2026-11-05T10:00:00+00:00',
};

Map<String, Object?> authPayloadJson({
  Map<String, Object?>? user,
  String token = '1|dory_testtoken',
  String? expiresAt = '2099-01-01T00:00:00+00:00',
}) => <String, Object?>{
  'user': user ?? userJson(),
  'token': <String, Object?>{
    'access_token': token,
    'token_type': 'Bearer',
    'expires_at': expiresAt,
  },
};
