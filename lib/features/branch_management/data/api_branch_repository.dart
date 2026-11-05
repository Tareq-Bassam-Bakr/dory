import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/domain/repositories/branch_repository.dart';
import 'package:dory_app/features/businesses/data/models/business_models.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';

/// [BranchRepository] over the DORY API.
class ApiBranchRepository implements BranchRepository {
  const ApiBranchRepository(this._api);

  final ApiClient _api;

  static Branch fromJson(JsonMap json) {
    final JsonMap permissions =
        json.objectOrNull('permissions') ?? const <String, Object?>{};
    return Branch(
      id: json.string('id'),
      businessId: json.stringOrNull('business_id'),
      name: json.string('name'),
      address: json.stringOrNull('address'),
      city: json.stringOrNull('city'),
      region: json.stringOrNull('region'),
      country: json.stringOrNull('country'),
      latitude: json.doubleOrNull('latitude'),
      longitude: json.doubleOrNull('longitude'),
      phone: json.stringOrNull('phone'),
      email: json.stringOrNull('email'),
      timezone: json.stringOrNull('timezone') ?? 'UTC',
      isActive: json.stringOrNull('status') == 'active',
      servicesCount: json.integerOrNull('services_count'),
      canUpdate: permissions.boolean('update'),
      canDelete: permissions.boolean('delete'),
      canManageServices: permissions.boolean('manage_services'),
    );
  }

  @override
  Future<List<Branch>> branches(String businessId) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/businesses/$businessId/branches',
          query: <String, Object?>{'per_page': 100},
        );
        return envelope.dataList.map(fromJson).toList();
      });

  @override
  Future<Branch> branch(String branchId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/branches/$branchId');
    return fromJson(envelope.dataObject);
  });

  @override
  Future<Branch> create(String businessId, BranchDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/businesses/$businessId/branches',
          body: BusinessModels.branchBody(draft),
        );
        return fromJson(envelope.dataObject);
      });

  @override
  Future<Branch> update(String branchId, BranchDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.put(
          '/branches/$branchId',
          body: BusinessModels.branchUpdateBody(draft),
        );
        return fromJson(envelope.dataObject);
      });

  @override
  Future<void> delete(String branchId) => AppFailure.guard(() async {
    await _api.delete('/branches/$branchId');
  });
}
