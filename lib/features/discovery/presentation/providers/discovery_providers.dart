import 'dart:async';
import 'dart:convert';

import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/location/location_service.dart';
import 'package:dory_app/core/network/api_client.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/data/api_discovery_repository.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/domain/repositories/discovery_repository.dart';
import 'package:dory_app/features/maps/presentation/providers/location_providers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<DiscoveryRepository> discoveryRepositoryProvider =
    Provider<DiscoveryRepository>(
      (ref) => ApiDiscoveryRepository(ref.watch(apiClientProvider)),
      name: 'discoveryRepositoryProvider',
    );

/// How long typing must pause before a search is sent.
const Duration searchDebounce = Duration(milliseconds: 350);

// ----- Home sections --------------------------------------------------------

/// Real businesses, open queues first. "Featured" is an ordering, not a paid
/// placement: nothing here is promoted.
final FutureProvider<List<PublicBusiness>> featuredBusinessesProvider =
    FutureProvider<List<PublicBusiness>>(
      (ref) async {
        ref.watch(currentUserIdProvider);
        final SearchArea? area = ref.watch(
          customerLocationProvider.select((location) => location.area),
        );
        final PageResult<PublicBusiness> page = await ref
            .watch(discoveryRepositoryProvider)
            .businesses(DiscoveryQuery(perPage: 6, area: area));
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'featuredBusinessesProvider',
    );

/// Branches around the customer, nearest first. Empty until a location is
/// known; the section then offers to set one.
final FutureProvider<List<PublicBranch>> nearbyBranchesProvider =
    FutureProvider<List<PublicBranch>>(
      (ref) async {
        final SearchArea? area = ref.watch(
          customerLocationProvider.select((location) => location.area),
        );
        if (area == null) {
          return const <PublicBranch>[];
        }
        final PageResult<PublicBranch> page = await ref
            .watch(discoveryRepositoryProvider)
            .nearby(DiscoveryQuery(area: area, perPage: 6));
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'nearbyBranchesProvider',
    );

final FutureProvider<List<AvailableQueue>> availableQueuesProvider =
    FutureProvider<List<AvailableQueue>>(
      (ref) async {
        final SearchArea? area = ref.watch(
          customerLocationProvider.select((location) => location.area),
        );
        final PageResult<AvailableQueue> page = await ref
            .watch(discoveryRepositoryProvider)
            .availableQueues(DiscoveryQuery(area: area, perPage: 6));
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'availableQueuesProvider',
    );

// ----- Details --------------------------------------------------------------

final publicBusinessProvider = FutureProvider.autoDispose
    .family<PublicBusiness, String>(
      (ref, slug) {
        ref.watch(currentUserIdProvider);
        final GeoPoint? from = ref.watch(
          customerLocationProvider.select((location) => location.point),
        );
        return ref
            .watch(discoveryRepositoryProvider)
            .business(slug, from: from);
      },
      retry: noAutomaticRetry,
      name: 'publicBusinessProvider',
    );

final publicBranchesProvider = FutureProvider.autoDispose
    .family<List<PublicBranch>, String>(
      (ref, slug) async {
        final GeoPoint? from = ref.watch(
          customerLocationProvider.select((location) => location.point),
        );
        final PageResult<PublicBranch> page = await ref
            .watch(discoveryRepositoryProvider)
            .businessBranches(slug, from: from);
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'publicBranchesProvider',
    );

final publicBranchProvider = FutureProvider.autoDispose
    .family<PublicBranch, String>(
      (ref, branchId) {
        final GeoPoint? from = ref.watch(
          customerLocationProvider.select((location) => location.point),
        );
        return ref
            .watch(discoveryRepositoryProvider)
            .branch(branchId, from: from);
      },
      retry: noAutomaticRetry,
      name: 'publicBranchProvider',
    );

final branchServicesProvider = FutureProvider.autoDispose
    .family<List<PublicService>, String>(
      (ref, branchId) async {
        final PageResult<PublicService> page = await ref
            .watch(discoveryRepositoryProvider)
            .branchServices(branchId);
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'branchServicesProvider',
    );

/// Always fetched fresh: a queue's state is never taken from a cache.
final joinableQueueProvider = FutureProvider.autoDispose
    .family<JoinableQueue, String>(
      (ref, queueId) => ref.watch(discoveryRepositoryProvider).queue(queueId),
      retry: noAutomaticRetry,
      name: 'joinableQueueProvider',
    );

final citiesProvider = FutureProvider.autoDispose
    .family<List<DiscoveryCity>, String>(
      (ref, search) async {
        final PageResult<DiscoveryCity> page = await ref
            .watch(discoveryRepositoryProvider)
            .cities(search: search);
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'citiesProvider',
    );

// ----- Explore ---------------------------------------------------------------

/// What the customer picked on the Explore screen.
@immutable
class ExploreFilters {
  const ExploreFilters({
    this.text = '',
    this.categorySlug,
    this.nearby = false,
    this.openQueue = false,
    this.accepting = false,
    this.maxWaitMinutes,
    this.minRating,
    this.sort = DiscoverySort.relevance,
  });

  final String text;
  final String? categorySlug;
  final bool nearby;
  final bool openQueue;
  final bool accepting;
  final int? maxWaitMinutes;
  final double? minRating;
  final DiscoverySort sort;

  /// Filters applied besides the text and the category.
  int get activeCount => <bool>[
    nearby,
    openQueue,
    accepting,
    maxWaitMinutes != null,
    minRating != null,
    sort != DiscoverySort.relevance,
  ].where((active) => active).length;

  ExploreFilters copyWith({
    String? text,
    Object? categorySlug = _keep,
    bool? nearby,
    bool? openQueue,
    bool? accepting,
    Object? maxWaitMinutes = _keep,
    Object? minRating = _keep,
    DiscoverySort? sort,
  }) => ExploreFilters(
    text: text ?? this.text,
    categorySlug: identical(categorySlug, _keep)
        ? this.categorySlug
        : categorySlug as String?,
    nearby: nearby ?? this.nearby,
    openQueue: openQueue ?? this.openQueue,
    accepting: accepting ?? this.accepting,
    maxWaitMinutes: identical(maxWaitMinutes, _keep)
        ? this.maxWaitMinutes
        : maxWaitMinutes as int?,
    minRating: identical(minRating, _keep)
        ? this.minRating
        : minRating as double?,
    sort: sort ?? this.sort,
  );

  static const Object _keep = Object();
}

/// Results of the Explore screen.
@immutable
class ExploreState {
  const ExploreState({
    this.filters = const ExploreFilters(),
    this.items = const <PublicBusiness>[],
    this.page = 0,
    this.total = 0,
    this.hasMore = true,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
  });

  final ExploreFilters filters;
  final List<PublicBusiness> items;
  final int page;
  final int total;
  final bool hasMore;
  final bool isLoading;
  final bool isLoadingMore;
  final Object? error;

  ExploreState copyWith({
    ExploreFilters? filters,
    List<PublicBusiness>? items,
    int? page,
    int? total,
    bool? hasMore,
    bool? isLoading,
    bool? isLoadingMore,
    Object? error,
  }) => ExploreState(
    filters: filters ?? this.filters,
    items: items ?? this.items,
    page: page ?? this.page,
    total: total ?? this.total,
    hasMore: hasMore ?? this.hasMore,
    isLoading: isLoading ?? this.isLoading,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    error: error,
  );
}

/// Debounced, cancellable, paged search. Every change of the filters cancels
/// the request in flight, so a slow old answer can never replace a newer one.
class ExploreController extends Notifier<ExploreState> {
  Timer? _debounce;
  ApiCancellation? _inFlight;
  int _generation = 0;

  @override
  ExploreState build() {
    ref
      ..watch(currentUserIdProvider)
      ..listen(customerLocationProvider.select((location) => location.area), (
        previous,
        next,
      ) {
        if (state.filters.nearby) {
          unawaited(_search());
        }
      })
      ..onDispose(() {
        _debounce?.cancel();
        _inFlight?.cancel();
      });
    unawaited(Future<void>.microtask(_search));
    return const ExploreState(isLoading: true);
  }

  /// The customer typed: waits for a pause before searching.
  void setText(String text) {
    if (text == state.filters.text) {
      return;
    }
    state = state.copyWith(filters: state.filters.copyWith(text: text));
    _debounce?.cancel();
    _debounce = Timer(searchDebounce, () => unawaited(_search()));
  }

  /// Any other filter applies at once.
  void setFilters(ExploreFilters filters) {
    _debounce?.cancel();
    state = state.copyWith(filters: filters);
    unawaited(_search());
  }

  Future<void> retry() => _search();

  DiscoveryQuery _query(int page) {
    final ExploreFilters filters = state.filters;
    final CustomerLocation location = ref.read(customerLocationProvider);
    final SearchArea? area = filters.nearby ? location.area : null;
    return DiscoveryQuery(
      text: filters.text,
      categorySlug: filters.categorySlug,
      area: area,
      openQueue: filters.openQueue,
      accepting: filters.accepting,
      maxWaitMinutes: filters.maxWaitMinutes,
      minRating: filters.minRating,
      sort: filters.sort == DiscoverySort.nearest && area == null
          ? DiscoverySort.relevance
          : filters.sort,
      page: page,
    );
  }

  Future<void> _search() async {
    final int generation = ++_generation;
    _inFlight?.cancel();
    final ApiCancellation cancellation = ApiCancellation();
    _inFlight = cancellation;
    state = state.copyWith(isLoading: true, isLoadingMore: false);

    try {
      final PageResult<PublicBusiness> result = await ref
          .read(discoveryRepositoryProvider)
          .businesses(_query(1), cancellation: cancellation);
      if (generation != _generation) {
        return;
      }
      state = ExploreState(
        filters: state.filters,
        items: result.items,
        page: result.pagination.currentPage,
        total: result.pagination.total,
        hasMore: result.pagination.hasMore,
      );
    } on AppFailure catch (failure) {
      if (generation == _generation) {
        state = state.copyWith(isLoading: false, error: failure);
      }
    }
  }

  Future<void> loadMore() async {
    if (state.isLoading || state.isLoadingMore || !state.hasMore) {
      return;
    }
    final int generation = _generation;
    state = state.copyWith(isLoadingMore: true);
    try {
      final PageResult<PublicBusiness> result = await ref
          .read(discoveryRepositoryProvider)
          .businesses(_query(state.page + 1));
      if (generation != _generation) {
        return;
      }
      state = state.copyWith(
        items: <PublicBusiness>[...state.items, ...result.items],
        page: result.pagination.currentPage,
        hasMore: result.pagination.hasMore,
        isLoadingMore: false,
      );
    } on AppFailure catch (failure) {
      if (generation == _generation) {
        state = state.copyWith(isLoadingMore: false, error: failure);
      }
    }
  }
}

final NotifierProvider<ExploreController, ExploreState> exploreProvider =
    NotifierProvider<ExploreController, ExploreState>(
      ExploreController.new,
      name: 'exploreProvider',
    );

// ----- Recent searches ---------------------------------------------------------

/// The last searches on this device, newest first.
class RecentSearches extends Notifier<List<String>> {
  static const int _limit = 8;

  KeyValueStorage get _storage => ref.read(keyValueStorageProvider);

  @override
  List<String> build() {
    final String? raw = ref
        .watch(keyValueStorageProvider)
        .readString(StorageKeys.recentSearches);
    if (raw == null) {
      return const <String>[];
    }
    try {
      final Object? decoded = jsonDecode(raw);
      return decoded is List
          ? decoded.whereType<String>().take(_limit).toList()
          : const <String>[];
    } on FormatException {
      return const <String>[];
    }
  }

  Future<void> add(String search) async {
    final String trimmed = search.trim();
    if (trimmed.length < 2) {
      return;
    }
    await _store(
      <String>[
        trimmed,
        ...state.where((item) => item.toLowerCase() != trimmed.toLowerCase()),
      ].take(_limit).toList(),
    );
  }

  Future<void> clear() => _store(const <String>[]);

  Future<void> _store(List<String> searches) async {
    state = searches;
    await _storage.writeString(
      StorageKeys.recentSearches,
      jsonEncode(searches),
    );
  }
}

final NotifierProvider<RecentSearches, List<String>> recentSearchesProvider =
    NotifierProvider<RecentSearches, List<String>>(
      RecentSearches.new,
      name: 'recentSearchesProvider',
    );

/// Branches for the map, around the current location.
final mapBranchesProvider = FutureProvider.autoDispose
    .family<List<PublicBranch>, String?>(
      (ref, categorySlug) async {
        final SearchArea? area = ref.watch(
          customerLocationProvider.select((location) => location.area),
        );
        if (area == null) {
          return const <PublicBranch>[];
        }
        final PageResult<PublicBranch> page = await ref
            .watch(discoveryRepositoryProvider)
            .nearby(
              DiscoveryQuery(
                area: area,
                categorySlug: categorySlug,
                perPage: 100,
              ),
            );
        return page.items;
      },
      retry: noAutomaticRetry,
      name: 'mapBranchesProvider',
    );
