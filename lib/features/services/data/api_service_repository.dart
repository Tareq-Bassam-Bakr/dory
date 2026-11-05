import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/businesses/data/models/business_models.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';
import 'package:dory_app/features/services/domain/repositories/service_repository.dart';

/// [ServiceRepository] over the DORY API.
class ApiServiceRepository implements ServiceRepository {
  const ApiServiceRepository(this._api);

  final ApiClient _api;

  static Service fromJson(JsonMap json) => Service(
    id: json.string('id'),
    branchId: json.stringOrNull('branch_id'),
    name: json.string('name'),
    description: json.stringOrNull('description'),
    durationMinutes: json.integerOrNull('estimated_duration_minutes') ?? 15,
    displayOrder: json.integerOrNull('display_order') ?? 0,
    isActive: json.stringOrNull('status') == 'active',
  );

  @override
  Future<List<Service>> services(String branchId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/branches/$branchId/services',
      query: <String, Object?>{'per_page': 100},
    );
    return envelope.dataList.map(fromJson).toList();
  });

  @override
  Future<Service> service(String serviceId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/services/$serviceId');
    return fromJson(envelope.dataObject);
  });

  @override
  Future<Service> create(String branchId, ServiceDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.post(
          '/branches/$branchId/services',
          body: BusinessModels.serviceBody(draft),
        );
        return fromJson(envelope.dataObject);
      });

  @override
  Future<Service> update(String serviceId, ServiceDraft draft) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.put(
          '/services/$serviceId',
          body: <String, Object?>{
            ...BusinessModels.serviceBody(draft),
            // Cleared descriptions must be sent as null.
            'description': draft.description.trim().isEmpty
                ? null
                : draft.description.trim(),
          },
        );
        return fromJson(envelope.dataObject);
      });

  @override
  Future<void> archive(String serviceId) => AppFailure.guard(() async {
    await _api.delete('/services/$serviceId');
  });
}
