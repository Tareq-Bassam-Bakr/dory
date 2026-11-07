import 'package:dio/dio.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/discovery/data/api_discovery_repository.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fake_http_adapter.dart';
import '../customer/customer_fixtures.dart';

void main() {
  late FakeHttpAdapter http;
  late ApiDiscoveryRepository repository;

  setUp(() {
    http = customerApi();
    repository = ApiDiscoveryRepository(
      ApiClient(
        Dio(BaseOptions(baseUrl: 'http://dory.test/api/v1'))
          ..httpClientAdapter = http,
      ),
    );
  });

  test('every filter reaches the server as a query parameter', () async {
    await repository.businesses(
      const DiscoveryQuery(
        text: '  clinic ',
        categorySlug: 'beauty-salons',
        country: 'SY',
        city: 'Damascus',
        area: SearchArea(point: GeoPoint(33.5, 36.2), radiusKm: 25),
        openQueue: true,
        accepting: true,
        maxWaitMinutes: 30,
        minRating: 4,
        sort: DiscoverySort.nearest,
        page: 2,
        perPage: 20,
      ),
    );

    expect(
      http.sent('GET', '/discovery/businesses').single.queryParameters,
      <String, Object?>{
        'q': 'clinic',
        'category': 'beauty-salons',
        'country': 'SY',
        'city': 'Damascus',
        'latitude': 33.5,
        'longitude': 36.2,
        'radius_km': 25.0,
        'open_queue': 1,
        'accepting': 1,
        'max_wait': 30,
        'min_rating': 4.0,
        'sort': 'nearest',
        'page': 2,
        'per_page': 20,
      },
    );
  });

  test('an empty query sends only paging', () async {
    await repository.businesses(const DiscoveryQuery());

    expect(
      http.sent('GET', '/discovery/businesses').single.queryParameters,
      <String, Object?>{'page': 1, 'per_page': 15},
    );
  });

  test('a business card is parsed with its live numbers', () async {
    http.reply(
      'GET',
      '/discovery/businesses',
      paged(<Map<String, Object?>>[
        publicBusinessJson(
          distance: 1.25,
          rating: <String, Object?>{'average': 4.5, 'count': 12},
        ),
      ]),
    );

    final PageResult<PublicBusiness> page = await repository.businesses(
      const DiscoveryQuery(),
    );
    final PublicBusiness business = page.items.single;

    expect(business.slug, publicSlug);
    expect(business.category?.slug, 'healthcare-clinics');
    expect(business.lowestWaitMinutes, 15);
    expect(business.distanceKm, 1.25);
    expect(business.rating?.average, 4.5);
    expect(business.rating?.count, 12);
    expect(page.pagination.hasMore, isFalse);
  });

  test('a missing rating stays missing', () async {
    final PageResult<PublicBusiness> page = await repository.businesses(
      const DiscoveryQuery(),
    );

    expect(page.items.single.rating, isNull);
  });

  test('a business profile carries its contact details', () async {
    final PublicBusiness business = await repository.business(publicSlug);

    expect(business.phone, '+963933000000');
    expect(business.email, 'hello@shifa.test');
    expect(business.website, 'https://shifa.test');
    expect(business.description, isNotNull);
  });

  test('distance is computed from the point sent', () async {
    await repository.business(publicSlug, from: const GeoPoint(33, 36));

    expect(
      http
          .sent('GET', '/discovery/businesses/$publicSlug')
          .single
          .queryParameters,
      <String, Object?>{'latitude': 33.0, 'longitude': 36.0},
    );
  });

  test('a branch has coordinates only when both exist', () async {
    http.reply(
      'GET',
      '/discovery/branches/$publicBranchId',
      FakeResponse.ok(publicBranchJson(latitude: null)),
    );

    expect((await repository.branch(publicBranchId)).location, isNull);
  });

  test('services keep their queue state', () async {
    http.reply(
      'GET',
      '/discovery/branches/$publicBranchId/services',
      paged(<Map<String, Object?>>[
        publicServiceJson(
          queue: queueAvailabilityJson(
            status: 'inactive',
            reason: 'branch_inactive',
            accepting: false,
          ),
        ),
        publicServiceJson(id: 'none', withQueue: false),
      ]),
    );

    final List<PublicService> services = (await repository.branchServices(
      publicBranchId,
    )).items;

    expect(services.first.queue?.state, QueueState.inactive);
    expect(services.first.queue?.unavailableReason, 'branch_inactive');
    expect(services.last.queue, isNull);
  });

  test('an unknown queue state is never read as open', () {
    expect(QueueState.fromApi('something-new'), QueueState.closed);
    expect(QueueState.fromApi(null), QueueState.closed);
  });

  test('available queues carry where they are', () async {
    final AvailableQueue queue = (await repository.availableQueues(
      const DiscoveryQuery(),
    )).items.single;

    expect(queue.serviceName, 'General consultation');
    expect(queue.branchId, publicBranchId);
    expect(queue.business.slug, publicSlug);
    expect(queue.queue.acceptingJoins, isTrue);
  });

  test('the join screen reads the public queue by id', () async {
    final JoinableQueue queue = await repository.queue(publicQueueId);

    expect(queue.businessName, 'Shifa Clinic');
    expect(queue.queue.allowGuestJoin, isTrue);
    expect(queue.nowServing, <String>['BAR-002']);
  });

  test('failures become AppFailure with the server code', () async {
    http.reply(
      'GET',
      '/discovery/businesses/$publicSlug',
      FakeResponse.error(404, 'business_not_found'),
    );

    await expectLater(
      repository.business(publicSlug),
      throwsA(
        isA<AppFailure>()
            .having((failure) => failure.kind, 'kind', FailureKind.notFound)
            .having((failure) => failure.code, 'code', 'business_not_found'),
      ),
    );
  });
}
