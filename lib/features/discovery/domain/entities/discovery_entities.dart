import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:flutter/foundation.dart';

/// Where to look: a point and a radius.
@immutable
class SearchArea {
  const SearchArea({required this.point, required this.radiusKm});

  final GeoPoint point;
  final double radiusKm;

  @override
  bool operator ==(Object other) =>
      other is SearchArea && other.point == point && other.radiusKm == radiusKm;

  @override
  int get hashCode => Object.hash(point, radiusKm);
}

/// How result lists are ordered.
enum DiscoverySort {
  relevance('relevance'),
  nearest('nearest'),
  wait('wait'),
  rating('rating');

  const DiscoverySort(this.apiValue);

  final String apiValue;
}

/// Everything a customer can search and filter by. Every filter is applied
/// by the server.
@immutable
class DiscoveryQuery {
  const DiscoveryQuery({
    this.text = '',
    this.categorySlug,
    this.country,
    this.city,
    this.area,
    this.openQueue = false,
    this.accepting = false,
    this.maxWaitMinutes,
    this.minRating,
    this.sort = DiscoverySort.relevance,
    this.page = 1,
    this.perPage = 15,
  });

  final String text;
  final String? categorySlug;
  final String? country;
  final String? city;
  final SearchArea? area;
  final bool openQueue;
  final bool accepting;
  final int? maxWaitMinutes;
  final double? minRating;
  final DiscoverySort sort;
  final int page;
  final int perPage;

  /// The API's query parameters.
  Map<String, Object?> toApi() => <String, Object?>{
    if (text.trim().isNotEmpty) 'q': text.trim(),
    'category': ?categorySlug,
    'country': ?country,
    'city': ?city,
    if (area != null) ...<String, Object?>{
      'latitude': area!.point.latitude,
      'longitude': area!.point.longitude,
      'radius_km': area!.radiusKm,
    },
    if (openQueue) 'open_queue': 1,
    if (accepting) 'accepting': 1,
    'max_wait': ?maxWaitMinutes,
    'min_rating': ?minRating,
    if (sort != DiscoverySort.relevance) 'sort': sort.apiValue,
    'page': page,
    'per_page': perPage,
  };

  @override
  bool operator ==(Object other) =>
      other is DiscoveryQuery &&
      other.text == text &&
      other.categorySlug == categorySlug &&
      other.country == country &&
      other.city == city &&
      other.area == area &&
      other.openQueue == openQueue &&
      other.accepting == accepting &&
      other.maxWaitMinutes == maxWaitMinutes &&
      other.minRating == minRating &&
      other.sort == sort &&
      other.page == page &&
      other.perPage == perPage;

  @override
  int get hashCode => Object.hash(
    text,
    categorySlug,
    country,
    city,
    area,
    openQueue,
    accepting,
    maxWaitMinutes,
    minRating,
    sort,
    page,
    perPage,
  );
}

/// A real average of real ratings. Absent until enough exist.
@immutable
class RatingSummary {
  const RatingSummary({required this.average, required this.count});

  final double average;
  final int count;
}

/// A business in lists, and with [description] and contact details on its
/// own page.
@immutable
class PublicBusiness {
  const PublicBusiness({
    required this.id,
    required this.slug,
    required this.name,
    required this.branchesCount,
    required this.openQueues,
    required this.acceptingQueues,
    this.category,
    this.logoUrl,
    this.coverUrl,
    this.city,
    this.country,
    this.distanceKm,
    this.lowestWaitMinutes,
    this.rating,
    this.isFavorite = false,
    this.description,
    this.email,
    this.phone,
    this.website,
  });

  final String id;
  final String slug;
  final String name;
  final BusinessCategory? category;
  final String? logoUrl;
  final String? coverUrl;
  final int branchesCount;
  final String? city;
  final String? country;
  final double? distanceKm;
  final int openQueues;
  final int acceptingQueues;
  final int? lowestWaitMinutes;
  final RatingSummary? rating;
  final bool isFavorite;
  final String? description;
  final String? email;
  final String? phone;
  final String? website;

  PublicBusiness copyWith({bool? isFavorite}) => PublicBusiness(
    id: id,
    slug: slug,
    name: name,
    branchesCount: branchesCount,
    openQueues: openQueues,
    acceptingQueues: acceptingQueues,
    category: category,
    logoUrl: logoUrl,
    coverUrl: coverUrl,
    city: city,
    country: country,
    distanceKm: distanceKm,
    lowestWaitMinutes: lowestWaitMinutes,
    rating: rating,
    isFavorite: isFavorite ?? this.isFavorite,
    description: description,
    email: email,
    phone: phone,
    website: website,
  );
}

/// The short form of a business inside branch and queue data.
@immutable
class BusinessSummary {
  const BusinessSummary({
    required this.id,
    required this.slug,
    required this.name,
    this.logoUrl,
    this.category,
  });

  final String id;
  final String slug;
  final String name;
  final String? logoUrl;
  final BusinessCategory? category;
}

/// A branch as the public sees it. There are no opening hours in the
/// backend: availability comes only from live queue data.
@immutable
class PublicBranch {
  const PublicBranch({
    required this.id,
    required this.name,
    required this.business,
    required this.servicesCount,
    required this.openQueues,
    required this.acceptingQueues,
    this.address,
    this.city,
    this.region,
    this.country,
    this.location,
    this.phone,
    this.distanceKm,
    this.lowestWaitMinutes,
  });

  final String id;
  final String name;
  final BusinessSummary business;
  final String? address;
  final String? city;
  final String? region;
  final String? country;
  final GeoPoint? location;
  final String? phone;
  final double? distanceKm;
  final int servicesCount;
  final int openQueues;
  final int acceptingQueues;
  final int? lowestWaitMinutes;
}

/// A queue's state, as reported by the server for its current session.
enum QueueState {
  open,
  paused,
  closed,
  inactive;

  static QueueState fromApi(String? value) =>
      values.firstWhere((state) => state.name == value, orElse: () => closed);
}

/// Whether a queue can take a customer right now. Always fetched from the
/// server, never derived from an older session.
@immutable
class QueueAvailability {
  const QueueAvailability({
    required this.id,
    required this.name,
    required this.state,
    required this.acceptingJoins,
    required this.isFull,
    required this.waitingCount,
    required this.estimatedMinutes,
    required this.allowGuestJoin,
    required this.requireGuestName,
    required this.requireGuestPhone,
    this.unavailableReason,
    this.capacity,
    this.capacityRemaining,
    this.estimateFromHistory = false,
  });

  final String id;
  final String name;
  final QueueState state;

  /// queue_inactive, business_unavailable, branch_inactive or
  /// service_unavailable, when [state] is [QueueState.inactive].
  final String? unavailableReason;
  final bool acceptingJoins;
  final bool isFull;
  final int waitingCount;
  final int? capacity;
  final int? capacityRemaining;
  final int estimatedMinutes;
  final bool estimateFromHistory;
  final bool allowGuestJoin;
  final bool requireGuestName;
  final bool requireGuestPhone;
}

/// A service of a branch, with its queue if it has one.
@immutable
class PublicService {
  const PublicService({
    required this.id,
    required this.name,
    required this.durationMinutes,
    this.description,
    this.queue,
  });

  final String id;
  final String name;
  final String? description;
  final int durationMinutes;
  final QueueAvailability? queue;
}

/// A queue accepting tickets now, with where it is.
@immutable
class AvailableQueue {
  const AvailableQueue({
    required this.queue,
    required this.serviceName,
    required this.branchId,
    required this.branchName,
    required this.business,
    this.city,
    this.distanceKm,
  });

  final QueueAvailability queue;
  final String serviceName;
  final String branchId;
  final String branchName;
  final String? city;
  final double? distanceKm;
  final BusinessSummary business;
}

/// What the join screen needs about a queue.
@immutable
class JoinableQueue {
  const JoinableQueue({
    required this.queue,
    required this.businessName,
    required this.branchName,
    this.businessSlug,
    this.serviceName,
    this.city,
    this.address,
    this.nowServing = const <String>[],
  });

  final QueueAvailability queue;
  final String businessName;
  final String? businessSlug;
  final String branchName;
  final String? serviceName;
  final String? city;
  final String? address;
  final List<String> nowServing;
}

/// A city with public branches, for choosing a location by hand.
@immutable
class DiscoveryCity {
  const DiscoveryCity({
    required this.city,
    required this.branchesCount,
    this.country,
    this.location,
  });

  final String city;
  final String? country;
  final int branchesCount;
  final GeoPoint? location;
}
