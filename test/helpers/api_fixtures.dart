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

const String branchId = '01jbranchxxxxxxxxxxxxxxxxx';
const String serviceId = '01jservicexxxxxxxxxxxxxxxx';

Map<String, Object?> categoryJson({
  String slug = 'healthcare-clinics',
  String en = 'Healthcare & Clinics',
  String ar = 'الرعاية الصحية والعيادات',
  String icon = 'healthcare',
}) => <String, Object?>{
  'slug': slug,
  'name': <String, Object?>{'en': en, 'ar': ar},
  'icon': icon,
  'is_active': true,
  'children': <Object?>[],
};

List<Map<String, Object?>> categoriesJson() => <Map<String, Object?>>[
  categoryJson(),
  categoryJson(
    slug: 'beauty-salons',
    en: 'Beauty & Salons',
    ar: 'التجميل والصالونات',
    icon: 'beauty',
  ),
];

const Map<String, Object?> ownerPermissions = <String, Object?>{
  'update': true,
  'change_status': true,
  'delete': true,
  'create_branches': true,
  'view_members': true,
  'manage_members': true,
};

const Map<String, Object?> employeePermissions = <String, Object?>{
  'update': false,
  'change_status': false,
  'delete': false,
  'create_branches': false,
  'view_members': false,
  'manage_members': false,
};

Map<String, Object?> businessJson({
  String id = businessId,
  String name = 'Shifa Clinic',
  String role = 'owner',
  String status = 'active',
  Map<String, Object?> permissions = ownerPermissions,
  int percentage = 60,
  List<String> missing = const <String>['service', 'contact'],
}) => <String, Object?>{
  'id': id,
  'name': name,
  'slug': 'shifa-clinic',
  'description': 'Family medicine',
  'category': categoryJson(),
  'email': null,
  'phone': null,
  'website': null,
  'timezone': 'Asia/Damascus',
  'status': status,
  'logo_url': null,
  'cover_url': null,
  'stats': <String, Object?>{'branches': 1, 'services': 0, 'members': 1},
  'profile': <String, Object?>{
    'percentage': percentage,
    'is_complete': missing.isEmpty,
    'missing': missing,
  },
  'membership': <String, Object?>{
    'id': '01jmembershipxxxxxxxxxxxxx',
    'role': role,
    'branch_access': 'all',
  },
  'permissions': permissions,
  'created_at': '2026-11-05T10:00:00+00:00',
  'updated_at': '2026-11-05T10:00:00+00:00',
};

Map<String, Object?> branchJson({
  String id = branchId,
  String name = 'Mazzeh',
  bool canManage = true,
  int services = 2,
  String status = 'active',
}) => <String, Object?>{
  'id': id,
  'business_id': businessId,
  'name': name,
  'address': 'Mazzeh Highway 12',
  'country': 'SY',
  'city': 'Damascus',
  'region': null,
  'latitude': 33.5138,
  'longitude': 36.2765,
  'phone': null,
  'email': null,
  'timezone': 'Asia/Damascus',
  'status': status,
  'services_count': services,
  'permissions': <String, Object?>{
    'update': canManage,
    'delete': canManage,
    'manage_services': canManage,
  },
};

Map<String, Object?> serviceJson({
  String id = serviceId,
  String name = 'General consultation',
  int minutes = 20,
  String status = 'active',
}) => <String, Object?>{
  'id': id,
  'branch_id': branchId,
  'name': name,
  'description': null,
  'estimated_duration_minutes': minutes,
  'display_order': 1,
  'status': status,
};
