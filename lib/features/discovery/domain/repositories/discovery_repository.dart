import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';

/// The public catalogue (docs/CUSTOMER_API_REFERENCE.md). Every list is
/// paged and filtered by the server. Every method throws [AppFailure].
abstract interface class DiscoveryRepository {
  Future<PageResult<PublicBusiness>> businesses(
    DiscoveryQuery query, {
    ApiCancellation? cancellation,
  });

  Future<PublicBusiness> business(String slug, {GeoPoint? from});

  Future<PageResult<PublicBranch>> businessBranches(
    String slug, {
    GeoPoint? from,
    int page = 1,
  });

  /// Branches around [query]'s area, nearest first.
  Future<PageResult<PublicBranch>> nearby(DiscoveryQuery query);

  /// Queues accepting tickets right now.
  Future<PageResult<AvailableQueue>> availableQueues(DiscoveryQuery query);

  Future<PageResult<DiscoveryCity>> cities({String? search, int page = 1});

  Future<PublicBranch> branch(String branchId, {GeoPoint? from});

  Future<PageResult<PublicService>> branchServices(
    String branchId, {
    int page = 1,
  });

  /// The current state of a queue, by public id.
  Future<JoinableQueue> queue(String queueId);
}
