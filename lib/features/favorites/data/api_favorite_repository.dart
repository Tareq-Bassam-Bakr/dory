import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/discovery/data/api_discovery_repository.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/favorites/domain/repositories/favorite_repository.dart';

/// [FavoriteRepository] over the DORY API.
class ApiFavoriteRepository implements FavoriteRepository {
  const ApiFavoriteRepository(this._api);

  final ApiClient _api;

  @override
  Future<PageResult<PublicBusiness>> favorites({int page = 1}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/me/favorites',
          query: <String, Object?>{'page': page, 'per_page': 30},
        );
        final List<PublicBusiness> items = envelope.dataList
            .map(ApiDiscoveryRepository.businessFromJson)
            .toList();
        return PageResult<PublicBusiness>(
          items: items,
          pagination:
              envelope.pagination ??
              Pagination(
                currentPage: 1,
                perPage: items.length,
                total: items.length,
                lastPage: 1,
              ),
        );
      });

  @override
  Future<PublicBusiness> add(String businessId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.post(
      '/me/favorites',
      body: <String, Object?>{'business': businessId},
    );
    return ApiDiscoveryRepository.businessFromJson(envelope.dataObject);
  });

  @override
  Future<void> remove(String businessId) => AppFailure.guard(() async {
    await _api.delete('/me/favorites/$businessId');
  });
}
