import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/businesses/data/models/business_models.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/domain/repositories/business_repository.dart';

/// [BusinessRepository] over the DORY API.
class ApiBusinessRepository implements BusinessRepository {
  const ApiBusinessRepository(this._api);

  final ApiClient _api;

  /// A user rarely belongs to more than a handful of businesses; one page
  /// at the API maximum covers them.
  static const int _pageSize = 100;

  @override
  Future<List<BusinessCategory>> categories() => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/business-categories');
    return envelope.dataList.map(BusinessModels.category).toList();
  });

  @override
  Future<List<Business>> myBusinesses() => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/businesses/manage',
      query: <String, Object?>{'per_page': _pageSize},
    );
    return envelope.dataList.map(BusinessModels.business).toList();
  });

  @override
  Future<Business> business(String businessId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/businesses/$businessId');
    return BusinessModels.business(envelope.dataObject);
  });

  @override
  Future<Business> create(
    BusinessDraft draft, {
    required String idempotencyKey,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/businesses',
      body: BusinessModels.createBody(draft),
      headers: <String, String>{'Idempotency-Key': idempotencyKey},
    );
    return BusinessModels.business(envelope.dataObject);
  });

  @override
  Future<Business> update(String businessId, BusinessProfileChanges changes) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.put(
          '/businesses/$businessId',
          body: BusinessModels.updateBody(changes),
        );
        return BusinessModels.business(envelope.dataObject);
      });

  @override
  Future<void> delete(String businessId) => AppFailure.guard(() async {
    await _api.delete('/businesses/$businessId');
  });
}
