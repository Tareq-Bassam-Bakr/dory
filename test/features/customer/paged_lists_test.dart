import 'dart:async';

import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/favorites/domain/repositories/favorite_repository.dart';
import 'package:dory_app/features/favorites/presentation/providers/favorite_providers.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/domain/repositories/ticket_repository.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _Business extends Fake implements PublicBusiness {
  _Business(this.id);

  @override
  final String id;
}

class _Ticket extends Fake implements CustomerTicket {
  _Ticket(this.id);

  @override
  final String id;
}

PageResult<T> _onePage<T>(List<T> items) => PageResult<T>(
  items: items,
  pagination: Pagination(
    currentPage: 1,
    perPage: 20,
    total: items.length,
    lastPage: 1,
  ),
);

/// Answers only when the test says so, to control the order of responses.
class _SlowFavorites extends Fake implements FavoriteRepository {
  final List<Completer<PageResult<PublicBusiness>>> requests =
      <Completer<PageResult<PublicBusiness>>>[];

  @override
  Future<PageResult<PublicBusiness>> favorites({int page = 1}) {
    final Completer<PageResult<PublicBusiness>> request =
        Completer<PageResult<PublicBusiness>>();
    requests.add(request);
    return request.future;
  }

  @override
  Future<PublicBusiness> add(String businessId) async => _Business(businessId);

  @override
  Future<void> remove(String businessId) async {}
}

class _SlowTickets extends Fake implements TicketRepository {
  final List<Completer<PageResult<CustomerTicket>>> requests =
      <Completer<PageResult<CustomerTicket>>>[];

  @override
  Future<PageResult<CustomerTicket>> myTickets(
    TicketFilter filter, {
    int page = 1,
  }) {
    final Completer<PageResult<CustomerTicket>> request =
        Completer<PageResult<CustomerTicket>>();
    requests.add(request);
    return request.future;
  }
}

Future<void> _settle() => Future<void>.delayed(Duration.zero);

/// Two loads of the first page can overlap: one when the list starts, one
/// from a reload. Found on a real phone, where saving a business from Explore
/// listed it twice in Favorites. Only the latest load may fill the list.
void main() {
  group('saved businesses', () {
    late _SlowFavorites repository;
    late ProviderContainer container;
    final _Business clinic = _Business('clinic');

    setUp(() {
      repository = _SlowFavorites();
      container = ProviderContainer.test(
        overrides: [
          currentUserIdProvider.overrideWithValue('user-1'),
          favoriteRepositoryProvider.overrideWithValue(repository),
        ],
      );
    });

    List<String> saved() => <String>[
      for (final PublicBusiness business
          in container.read(serverFavoritesProvider).items)
        business.id,
    ];

    test('saving during the first load lists the business once', () async {
      final ServerFavorites favorites = container.read(
        serverFavoritesProvider.notifier,
      );
      final Future<bool> saving = favorites.setFavorite(clinic, true);
      await _settle();
      expect(repository.requests, hasLength(2));

      repository.requests[0].complete(_onePage(<PublicBusiness>[clinic]));
      await _settle();
      repository.requests[1].complete(_onePage(<PublicBusiness>[clinic]));
      await saving;
      await _settle();

      expect(saved(), <String>['clinic']);
      expect(container.read(serverFavoritesProvider).isLoading, isFalse);
    });

    test('an older answer arriving last changes nothing', () async {
      final ServerFavorites favorites = container.read(
        serverFavoritesProvider.notifier,
      );
      final Future<bool> saving = favorites.setFavorite(clinic, true);
      await _settle();

      repository.requests[1].complete(_onePage(<PublicBusiness>[clinic]));
      await saving;
      repository.requests[0].complete(
        _onePage(<PublicBusiness>[_Business('older')]),
      );
      await _settle();

      expect(saved(), <String>['clinic']);
    });

    test('removing during a load does not bring the business back', () async {
      final ServerFavorites favorites = container.read(
        serverFavoritesProvider.notifier,
      );
      await _settle();

      final Future<bool> removing = favorites.setFavorite(clinic, false);
      await _settle();
      repository.requests[0].complete(_onePage(<PublicBusiness>[clinic]));
      await _settle();
      repository.requests[1].complete(_onePage(<PublicBusiness>[]));
      await removing;
      await _settle();

      expect(saved(), isEmpty);
      expect(container.read(serverFavoritesProvider).isLoading, isFalse);
    });

    test('scrolling during a load asks for the page only once', () async {
      final ServerFavorites favorites = container.read(
        serverFavoritesProvider.notifier,
      );
      await _settle();
      await favorites.loadMore();
      await _settle();

      expect(repository.requests, hasLength(1));
    });
  });

  group('my tickets', () {
    late _SlowTickets repository;
    late ProviderContainer container;
    final _Ticket ticket = _Ticket('ticket');

    setUp(() {
      repository = _SlowTickets();
      container = ProviderContainer.test(
        overrides: [
          currentUserIdProvider.overrideWithValue('user-1'),
          ticketRepositoryProvider.overrideWithValue(repository),
        ],
      );
      container.listen(myTicketsProvider(TicketFilter.active), (_, _) {});
    });

    List<String> tickets() => <String>[
      for (final CustomerTicket item
          in container.read(myTicketsProvider(TicketFilter.active)).items)
        item.id,
    ];

    test('refreshing during the first load lists a ticket once', () async {
      await _settle();
      final Future<void> reloading = container
          .read(myTicketsProvider(TicketFilter.active).notifier)
          .reload();
      await _settle();
      expect(repository.requests, hasLength(2));

      repository.requests[0].complete(_onePage(<CustomerTicket>[ticket]));
      await _settle();
      repository.requests[1].complete(_onePage(<CustomerTicket>[ticket]));
      await reloading;
      await _settle();

      expect(tickets(), <String>['ticket']);
      expect(
        container.read(myTicketsProvider(TicketFilter.active)).isLoading,
        isFalse,
      );
    });

    test('an older answer arriving last changes nothing', () async {
      await _settle();
      final Future<void> reloading = container
          .read(myTicketsProvider(TicketFilter.active).notifier)
          .reload();
      await _settle();

      repository.requests[1].complete(_onePage(<CustomerTicket>[ticket]));
      await reloading;
      repository.requests[0].complete(
        _onePage(<CustomerTicket>[_Ticket('older')]),
      );
      await _settle();

      expect(tickets(), <String>['ticket']);
    });

    test('scrolling during a load asks for the page only once', () async {
      await _settle();
      await container
          .read(myTicketsProvider(TicketFilter.active).notifier)
          .loadMore();
      await _settle();

      expect(repository.requests, hasLength(1));
    });
  });
}
