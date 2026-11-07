import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/features/businesses/data/models/business_models.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/domain/repositories/discovery_repository.dart';

/// [DiscoveryRepository] over the DORY API.
class ApiDiscoveryRepository implements DiscoveryRepository {
  const ApiDiscoveryRepository(this._api);

  final ApiClient _api;

  // ----- JSON -----------------------------------------------------------

  static BusinessCategory? _category(JsonMap json) {
    final JsonMap? category = json.objectOrNull('category');
    return category == null ? null : BusinessModels.category(category);
  }

  static GeoPoint? _point(JsonMap json) {
    final double? latitude = json.doubleOrNull('latitude');
    final double? longitude = json.doubleOrNull('longitude');
    return latitude == null || longitude == null
        ? null
        : GeoPoint(latitude, longitude);
  }

  static PublicBusiness businessFromJson(JsonMap json) {
    final JsonMap? rating = json.objectOrNull('rating');
    return PublicBusiness(
      id: json.string('id'),
      slug: json.string('slug'),
      name: json.string('name'),
      category: _category(json),
      logoUrl: json.stringOrNull('logo_url'),
      coverUrl: json.stringOrNull('cover_url'),
      branchesCount: json.integerOrNull('branches_count') ?? 0,
      city: json.stringOrNull('city'),
      country: json.stringOrNull('country'),
      distanceKm: json.doubleOrNull('distance_km'),
      openQueues: json.integerOrNull('open_queues_count') ?? 0,
      acceptingQueues: json.integerOrNull('accepting_queues_count') ?? 0,
      lowestWaitMinutes: json.integerOrNull('lowest_wait_minutes'),
      rating: rating == null
          ? null
          : RatingSummary(
              average: rating.doubleOrNull('average') ?? 0,
              count: rating.integerOrNull('count') ?? 0,
            ),
      isFavorite: json.boolean('is_favorite'),
      description: json.stringOrNull('description'),
      email: json.stringOrNull('email'),
      phone: json.stringOrNull('phone'),
      website: json.stringOrNull('website'),
    );
  }

  static BusinessSummary _businessSummary(JsonMap json) => BusinessSummary(
    id: json.string('id'),
    slug: json.string('slug'),
    name: json.string('name'),
    logoUrl: json.stringOrNull('logo_url'),
    category: _category(json),
  );

  static PublicBranch branchFromJson(JsonMap json) => PublicBranch(
    id: json.string('id'),
    name: json.string('name'),
    business: _businessSummary(json.object('business')),
    address: json.stringOrNull('address'),
    city: json.stringOrNull('city'),
    region: json.stringOrNull('region'),
    country: json.stringOrNull('country'),
    location: _point(json),
    phone: json.stringOrNull('phone'),
    distanceKm: json.doubleOrNull('distance_km'),
    servicesCount: json.integerOrNull('services_count') ?? 0,
    openQueues: json.integerOrNull('open_queues_count') ?? 0,
    acceptingQueues: json.integerOrNull('accepting_queues_count') ?? 0,
    lowestWaitMinutes: json.integerOrNull('lowest_wait_minutes'),
  );

  static QueueAvailability queueFromJson(JsonMap json) {
    final JsonMap? estimate = json.objectOrNull('estimated_wait');
    return QueueAvailability(
      id: json.string('id'),
      name: json.string('name'),
      state: QueueState.fromApi(json.stringOrNull('status')),
      unavailableReason: json.stringOrNull('unavailable_reason'),
      acceptingJoins: json.boolean('accepting_joins'),
      isFull: json.boolean('is_full'),
      waitingCount: json.integerOrNull('waiting_count') ?? 0,
      capacity: json.integerOrNull('capacity'),
      capacityRemaining: json.integerOrNull('capacity_remaining'),
      estimatedMinutes: estimate?.integerOrNull('minutes') ?? 0,
      estimateFromHistory: estimate?.stringOrNull('basis') == 'history',
      allowGuestJoin: json.boolean('allow_guest_join'),
      requireGuestName: json.boolean('require_guest_name'),
      requireGuestPhone: json.boolean('require_guest_phone'),
    );
  }

  static PublicService serviceFromJson(JsonMap json) {
    final JsonMap? queue = json.objectOrNull('queue');
    return PublicService(
      id: json.string('id'),
      name: json.string('name'),
      description: json.stringOrNull('description'),
      durationMinutes: json.integerOrNull('estimated_duration_minutes') ?? 0,
      queue: queue == null ? null : queueFromJson(queue),
    );
  }

  static AvailableQueue availableQueueFromJson(JsonMap json) {
    final JsonMap branch = json.object('branch');
    return AvailableQueue(
      queue: queueFromJson(json),
      serviceName: json.object('service').string('name'),
      branchId: branch.string('id'),
      branchName: branch.string('name'),
      city: branch.stringOrNull('city'),
      distanceKm: branch.doubleOrNull('distance_km'),
      business: _businessSummary(json.object('business')),
    );
  }

  static JoinableQueue joinableQueueFromJson(JsonMap json) {
    final JsonMap business = json.object('business');
    final JsonMap branch = json.object('branch');
    final Object? serving = json['now_serving'];
    return JoinableQueue(
      queue: queueFromJson(json),
      businessName: business.string('name'),
      businessSlug: business.stringOrNull('slug'),
      branchName: branch.string('name'),
      serviceName: json.objectOrNull('service')?.stringOrNull('name'),
      city: branch.stringOrNull('city'),
      address: branch.stringOrNull('address'),
      nowServing: serving is List
          ? serving.whereType<String>().toList()
          : const <String>[],
    );
  }

  static DiscoveryCity cityFromJson(JsonMap json) => DiscoveryCity(
    city: json.string('city'),
    country: json.stringOrNull('country'),
    branchesCount: json.integerOrNull('branches_count') ?? 0,
    location: _point(json),
  );

  static PageResult<T> _page<T>(
    ApiEnvelope envelope,
    T Function(JsonMap json) parse,
  ) {
    final List<T> items = envelope.dataList.map(parse).toList();
    return PageResult<T>(
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
  }

  static Map<String, Object?> _from(GeoPoint? point) => <String, Object?>{
    if (point != null) ...<String, Object?>{
      'latitude': point.latitude,
      'longitude': point.longitude,
    },
  };

  // ----- Requests -------------------------------------------------------

  @override
  Future<PageResult<PublicBusiness>> businesses(
    DiscoveryQuery query, {
    ApiCancellation? cancellation,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/discovery/businesses',
      query: query.toApi(),
      cancellation: cancellation,
    );
    return _page(envelope, businessFromJson);
  });

  @override
  Future<PublicBusiness> business(String slug, {GeoPoint? from}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/discovery/businesses/$slug',
          query: _from(from),
        );
        return businessFromJson(envelope.dataObject);
      });

  @override
  Future<PageResult<PublicBranch>> businessBranches(
    String slug, {
    GeoPoint? from,
    int page = 1,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/discovery/businesses/$slug/branches',
      query: <String, Object?>{..._from(from), 'page': page, 'per_page': 30},
    );
    return _page(envelope, branchFromJson);
  });

  @override
  Future<PageResult<PublicBranch>> nearby(DiscoveryQuery query) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/discovery/nearby',
          query: query.toApi(),
        );
        return _page(envelope, branchFromJson);
      });

  @override
  Future<PageResult<AvailableQueue>> availableQueues(DiscoveryQuery query) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/discovery/queues',
          query: query.toApi(),
        );
        return _page(envelope, availableQueueFromJson);
      });

  @override
  Future<PageResult<DiscoveryCity>> cities({String? search, int page = 1}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/discovery/cities',
          query: <String, Object?>{
            if (search != null && search.trim().isNotEmpty) 'q': search.trim(),
            'page': page,
            'per_page': 50,
          },
        );
        return _page(envelope, cityFromJson);
      });

  @override
  Future<PublicBranch> branch(String branchId, {GeoPoint? from}) =>
      AppFailure.guard(() async {
        final ApiEnvelope envelope = await _api.get(
          '/discovery/branches/$branchId',
          query: _from(from),
        );
        return branchFromJson(envelope.dataObject);
      });

  @override
  Future<PageResult<PublicService>> branchServices(
    String branchId, {
    int page = 1,
  }) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get(
      '/discovery/branches/$branchId/services',
      query: <String, Object?>{'page': page, 'per_page': 50},
    );
    return _page(envelope, serviceFromJson);
  });

  @override
  Future<JoinableQueue> queue(String queueId) => AppFailure.guard(() async {
    final ApiEnvelope envelope = await _api.get('/public/queues/$queueId');
    return joinableQueueFromJson(envelope.dataObject);
  });
}
