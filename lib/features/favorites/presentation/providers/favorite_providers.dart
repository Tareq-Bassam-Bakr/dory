import 'dart:async';
import 'dart:convert';

import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/json.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/favorites/data/api_favorite_repository.dart';
import 'package:dory_app/features/favorites/domain/repositories/favorite_repository.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<FavoriteRepository> favoriteRepositoryProvider =
    Provider<FavoriteRepository>(
      (ref) => ApiFavoriteRepository(ref.watch(apiClientProvider)),
      name: 'favoriteRepositoryProvider',
    );

/// A business saved on this device while signed out. Only what is needed to
/// list it and open its page.
@immutable
class LocalFavorite {
  const LocalFavorite({
    required this.id,
    required this.slug,
    required this.name,
    this.city,
  });

  factory LocalFavorite.fromJson(JsonMap json) => LocalFavorite(
    id: json.string('id'),
    slug: json.string('slug'),
    name: json.string('name'),
    city: json.stringOrNull('city'),
  );

  final String id;
  final String slug;
  final String name;
  final String? city;

  JsonMap toJson() => <String, Object?>{
    'id': id,
    'slug': slug,
    'name': name,
    'city': city,
  };
}

/// Favorites of signed-out visitors. They stay on this device and are never
/// sent to the server; the screen says so.
class LocalFavorites extends Notifier<List<LocalFavorite>> {
  KeyValueStorage get _storage => ref.read(keyValueStorageProvider);

  @override
  List<LocalFavorite> build() {
    final String? raw = ref
        .watch(keyValueStorageProvider)
        .readString(StorageKeys.localFavorites);
    if (raw == null) {
      return const <LocalFavorite>[];
    }
    try {
      final Object? decoded = jsonDecode(raw);
      return decoded is List
          ? <LocalFavorite>[
              for (final Object? item in decoded)
                LocalFavorite.fromJson(asJsonMap(item)),
            ]
          : const <LocalFavorite>[];
    } on FormatException {
      return const <LocalFavorite>[];
    }
  }

  bool contains(String businessId) =>
      state.any((favorite) => favorite.id == businessId);

  Future<void> toggle(LocalFavorite favorite) async {
    final List<LocalFavorite> next = contains(favorite.id)
        ? <LocalFavorite>[
            for (final LocalFavorite item in state)
              if (item.id != favorite.id) item,
          ]
        : <LocalFavorite>[favorite, ...state];
    state = next;
    await _storage.writeString(
      StorageKeys.localFavorites,
      jsonEncode(<JsonMap>[
        for (final LocalFavorite item in next) item.toJson(),
      ]),
    );
  }
}

final NotifierProvider<LocalFavorites, List<LocalFavorite>>
localFavoritesProvider = NotifierProvider<LocalFavorites, List<LocalFavorite>>(
  LocalFavorites.new,
  name: 'localFavoritesProvider',
);

/// Whether a business is saved, across every screen showing it. Lists carry
/// the server's answer; a change made here wins until they reload.
class FavoriteChanges extends Notifier<Map<String, bool>> {
  @override
  Map<String, bool> build() {
    ref.watch(currentUserIdProvider);
    return const <String, bool>{};
  }

  void set(String businessId, bool saved) {
    state = <String, bool>{...state, businessId: saved};
  }
}

final NotifierProvider<FavoriteChanges, Map<String, bool>>
favoriteChangesProvider = NotifierProvider<FavoriteChanges, Map<String, bool>>(
  FavoriteChanges.new,
  name: 'favoriteChangesProvider',
);

/// The signed-in customer's saved businesses, page by page.
@immutable
class FavoriteListState {
  const FavoriteListState({
    this.items = const <PublicBusiness>[],
    this.page = 0,
    this.hasMore = true,
    this.isLoading = false,
    this.error,
  });

  final List<PublicBusiness> items;
  final int page;
  final bool hasMore;
  final bool isLoading;
  final Object? error;
}

class ServerFavorites extends Notifier<FavoriteListState> {
  FavoriteRepository get _repository => ref.read(favoriteRepositoryProvider);

  @override
  FavoriteListState build() {
    if (ref.watch(currentUserIdProvider) == null) {
      return const FavoriteListState(hasMore: false);
    }
    unawaited(Future<void>.microtask(loadMore));
    return const FavoriteListState(isLoading: true);
  }

  Future<void> reload() async {
    state = const FavoriteListState();
    await loadMore();
  }

  Future<void> loadMore() async {
    final bool first = state.page == 0;
    if ((!first && state.isLoading) || !state.hasMore) {
      return;
    }
    state = FavoriteListState(
      items: state.items,
      page: state.page,
      isLoading: true,
    );
    try {
      final PageResult<PublicBusiness> result = await _repository.favorites(
        page: state.page + 1,
      );
      state = FavoriteListState(
        items: <PublicBusiness>[...state.items, ...result.items],
        page: result.pagination.currentPage,
        hasMore: result.pagination.hasMore,
      );
    } on AppFailure catch (failure) {
      state = FavoriteListState(
        items: state.items,
        page: state.page,
        error: failure,
      );
    }
  }

  /// Saves or removes a business. Throws [AppFailure] and changes nothing
  /// when the server refuses.
  Future<bool> setFavorite(PublicBusiness business, bool saved) async {
    if (saved) {
      await _repository.add(business.id);
      ref.read(favoriteChangesProvider.notifier).set(business.id, true);
      await reload();
    } else {
      await _repository.remove(business.id);
      ref.read(favoriteChangesProvider.notifier).set(business.id, false);
      state = FavoriteListState(
        items: <PublicBusiness>[
          for (final PublicBusiness item in state.items)
            if (item.id != business.id) item,
        ],
        page: state.page,
        hasMore: state.hasMore,
      );
    }
    return saved;
  }
}

final NotifierProvider<ServerFavorites, FavoriteListState>
serverFavoritesProvider = NotifierProvider<ServerFavorites, FavoriteListState>(
  ServerFavorites.new,
  name: 'serverFavoritesProvider',
);
