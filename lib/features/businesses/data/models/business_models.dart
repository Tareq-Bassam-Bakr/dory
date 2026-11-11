import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/shared/enums/business_role.dart';

/// JSON mapping for business resources (docs/API_ENDPOINTS_PHASE_03.md).
abstract final class BusinessModels {
  static BusinessCategory category(JsonMap json) {
    final JsonMap names = json.object('name');
    return BusinessCategory(
      slug: json.string('slug'),
      names: <String, String>{
        for (final MapEntry<String, Object?> entry in names.entries)
          if (entry.value is String) entry.key: entry.value! as String,
      },
      icon: json.stringOrNull('icon') ?? 'other',
      children: <BusinessCategory>[
        for (final JsonMap child in json.objectList('children'))
          category(child),
      ],
    );
  }

  static Business business(JsonMap json) {
    final JsonMap? category = json.objectOrNull('category');
    final JsonMap? membership = json.objectOrNull('membership');
    final JsonMap? stats = json.objectOrNull('stats');
    final JsonMap? profile = json.objectOrNull('profile');
    final JsonMap permissions =
        json.objectOrNull('permissions') ?? const <String, Object?>{};

    return Business(
      id: json.string('id'),
      name: json.string('name'),
      slug: json.stringOrNull('slug') ?? '',
      description: json.stringOrNull('description'),
      category: category == null ? null : BusinessModels.category(category),
      email: json.stringOrNull('email'),
      phone: json.stringOrNull('phone'),
      website: json.stringOrNull('website'),
      timezone: json.stringOrNull('timezone') ?? 'UTC',
      status: BusinessStatus.fromApi(json.stringOrNull('status')),
      role: membership == null
          ? null
          : BusinessRole.fromApi(membership.stringOrNull('role')),
      stats: stats == null
          ? null
          : BusinessStats(
              branches: stats.integerOrNull('branches') ?? 0,
              services: stats.integerOrNull('services') ?? 0,
              members: stats.integerOrNull('members') ?? 0,
            ),
      profile: profile == null
          ? null
          : ProfileCompletion(
              percentage: profile.integerOrNull('percentage') ?? 0,
              isComplete: profile.boolean('is_complete'),
              missing: <String>[
                if (profile['missing'] is List)
                  for (final Object? item in profile['missing']! as List)
                    if (item is String) item,
              ],
            ),
      permissions: BusinessPermissions(
        update: permissions.boolean('update'),
        changeStatus: permissions.boolean('change_status'),
        delete: permissions.boolean('delete'),
        createBranches: permissions.boolean('create_branches'),
        viewMembers: permissions.boolean('view_members'),
        manageMembers: permissions.boolean('manage_members'),
        viewInsights: permissions.boolean('view_insights'),
        viewSubscription: permissions.boolean('view_subscription'),
        manageBilling: permissions.boolean('manage_billing'),
      ),
    );
  }

  /// Body of POST /businesses: profile, plus the first branch and its
  /// services. Empty optional fields are left out.
  static JsonMap createBody(BusinessDraft draft) => <String, Object?>{
    'name': draft.name.trim(),
    'category': draft.categorySlug,
    ..._optional(<String, String>{
      'description': draft.description,
      'email': draft.email,
      'phone': draft.phone,
      'website': draft.website,
    }),
    if (draft.branch.name.trim().isNotEmpty)
      'branch': <String, Object?>{
        ...branchBody(draft.branch),
        if (draft.services.isNotEmpty)
          'services': <JsonMap>[
            for (final ServiceDraft service in draft.services)
              serviceBody(service),
          ],
      },
  };

  static JsonMap updateBody(BusinessProfileChanges changes) =>
      <String, Object?>{
        if (changes.name != null) 'name': changes.name!.trim(),
        if (changes.categorySlug != null) 'category': changes.categorySlug,
        if (changes.description != null)
          'description': _nullIfBlank(changes.description!),
        if (changes.email != null) 'email': _nullIfBlank(changes.email!),
        if (changes.phone != null) 'phone': _nullIfBlank(changes.phone!),
        if (changes.website != null) 'website': _nullIfBlank(changes.website!),
        if (changes.timezone != null) 'timezone': changes.timezone!.trim(),
        if (changes.isActive != null)
          'status': changes.isActive! ? 'active' : 'inactive',
      };

  /// Branch fields for create; blank optional fields are omitted.
  static JsonMap branchBody(BranchDraft branch) => <String, Object?>{
    'name': branch.name.trim(),
    ..._optional(<String, String>{
      'address': branch.address,
      'city': branch.city,
      'region': branch.region,
      'country': branch.country.toUpperCase(),
      'phone': branch.phone,
      'email': branch.email,
      'timezone': branch.timezone,
    }),
    if (branch.latitude.trim().isNotEmpty &&
        branch.longitude.trim().isNotEmpty) ...<String, Object?>{
      'latitude': double.parse(branch.latitude.trim()),
      'longitude': double.parse(branch.longitude.trim()),
    },
    'status': branch.isActive ? 'active' : 'inactive',
  };

  /// Branch fields for update: blank optional fields are sent as null so
  /// they are cleared.
  static JsonMap branchUpdateBody(BranchDraft branch) {
    final bool hasLocation =
        branch.latitude.trim().isNotEmpty && branch.longitude.trim().isNotEmpty;
    return <String, Object?>{
      'name': branch.name.trim(),
      'address': _nullIfBlank(branch.address),
      'city': _nullIfBlank(branch.city),
      'region': _nullIfBlank(branch.region),
      'country': _nullIfBlank(branch.country.toUpperCase()),
      'phone': _nullIfBlank(branch.phone),
      'email': _nullIfBlank(branch.email),
      if (branch.timezone.trim().isNotEmpty) 'timezone': branch.timezone.trim(),
      'latitude': hasLocation ? double.parse(branch.latitude.trim()) : null,
      'longitude': hasLocation ? double.parse(branch.longitude.trim()) : null,
      'status': branch.isActive ? 'active' : 'inactive',
    };
  }

  static JsonMap serviceBody(ServiceDraft service) => <String, Object?>{
    'name': service.name.trim(),
    if (service.description.trim().isNotEmpty)
      'description': service.description.trim(),
    'estimated_duration_minutes': service.durationMinutes,
    'status': service.isActive ? 'active' : 'inactive',
  };

  static Map<String, String> _optional(Map<String, String> fields) =>
      <String, String>{
        for (final MapEntry<String, String> entry in fields.entries)
          if (entry.value.trim().isNotEmpty) entry.key: entry.value.trim(),
      };

  static String? _nullIfBlank(String value) =>
      value.trim().isEmpty ? null : value.trim();
}
