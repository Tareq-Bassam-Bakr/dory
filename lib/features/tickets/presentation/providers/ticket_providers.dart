import 'dart:async';

import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/api_envelope.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/tickets/data/api_ticket_repository.dart';
import 'package:dory_app/features/tickets/data/guest_ticket_vault.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/domain/repositories/ticket_repository.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<TicketRepository> ticketRepositoryProvider =
    Provider<TicketRepository>(
      (ref) => ApiTicketRepository(ref.watch(apiClientProvider)),
      name: 'ticketRepositoryProvider',
    );

final Provider<GuestTicketVault> guestTicketVaultProvider =
    Provider<GuestTicketVault>(
      (ref) => GuestTicketVault(
        ref.watch(keyValueStorageProvider),
        ref.watch(secureStoreProvider),
      ),
      name: 'guestTicketVaultProvider',
    );

/// The guest tickets kept on this device.
class GuestTickets extends Notifier<List<StoredGuestTicket>> {
  GuestTicketVault get _vault => ref.read(guestTicketVaultProvider);

  @override
  List<StoredGuestTicket> build() => ref.watch(guestTicketVaultProvider).list();

  Future<void> save(CustomerTicket ticket, String accessToken) async {
    await _vault.save(ticket, accessToken);
    state = _vault.list();
  }

  Future<void> update(CustomerTicket ticket) async {
    await _vault.update(ticket);
    state = _vault.list();
  }

  /// Forgets a ticket on this device only; the server is not changed.
  Future<void> remove(String ticketId) async {
    await _vault.remove(ticketId);
    state = _vault.list();
  }

  /// Reads the active tickets once, one after another. Used when the ticket
  /// list opens; it does not repeat, so a list never polls every ticket.
  Future<void> refreshActive() async {
    final TicketRepository repository = ref.read(ticketRepositoryProvider);
    for (final StoredGuestTicket stored in state.toList()) {
      if (!stored.status.isActive) {
        continue;
      }
      final String? token = await _vault.tokenFor(stored.id);
      if (token == null) {
        continue;
      }
      try {
        await update(await repository.guestTicket(stored.id, token));
      } on AppFailure {
        // Offline or no longer readable: the stored details stay as they are.
      }
    }
  }
}

final NotifierProvider<GuestTickets, List<StoredGuestTicket>>
guestTicketsProvider = NotifierProvider<GuestTickets, List<StoredGuestTicket>>(
  GuestTickets.new,
  name: 'guestTicketsProvider',
);

/// One page-by-page list of the signed-in customer's tickets.
@immutable
class TicketListState {
  const TicketListState({
    this.items = const <CustomerTicket>[],
    this.page = 0,
    this.hasMore = true,
    this.isLoading = false,
    this.error,
  });

  final List<CustomerTicket> items;
  final int page;
  final bool hasMore;
  final bool isLoading;
  final Object? error;

  TicketListState copyWith({
    List<CustomerTicket>? items,
    int? page,
    bool? hasMore,
    bool? isLoading,
    Object? error,
  }) => TicketListState(
    items: items ?? this.items,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    isLoading: isLoading ?? this.isLoading,
    error: error,
  );
}

class MyTickets extends Notifier<TicketListState> {
  MyTickets(this.filter);

  final TicketFilter filter;

  /// Raised whenever the list starts over: an answer to an older load must
  /// not add to the new list.
  int _generation = 0;
  bool _loading = false;

  @override
  TicketListState build() {
    _generation++;
    _loading = false;
    final String? user = ref.watch(currentUserIdProvider);
    if (user == null) {
      return const TicketListState(hasMore: false);
    }
    unawaited(Future<void>.microtask(loadMore));
    return const TicketListState(isLoading: true);
  }

  Future<void> reload() async {
    _generation++;
    _loading = false;
    state = const TicketListState();
    await loadMore();
  }

  /// Loads the next page, once at a time.
  Future<void> loadMore() async {
    if (_loading || !state.hasMore) {
      return;
    }
    final int generation = _generation;
    _loading = true;
    state = state.copyWith(isLoading: true);
    try {
      final PageResult<CustomerTicket> result = await ref
          .read(ticketRepositoryProvider)
          .myTickets(filter, page: state.page + 1);
      if (!ref.mounted || generation != _generation) {
        return;
      }
      _loading = false;
      state = TicketListState(
        items: <CustomerTicket>[...state.items, ...result.items],
        page: result.pagination.currentPage,
        hasMore: result.pagination.hasMore,
      );
    } on AppFailure catch (failure) {
      if (ref.mounted && generation == _generation) {
        _loading = false;
        state = state.copyWith(isLoading: false, error: failure);
      }
    }
  }
}

final myTicketsProvider = NotifierProvider.autoDispose
    .family<MyTickets, TicketListState, TicketFilter>(
      MyTickets.new,
      name: 'myTicketsProvider',
    );

/// Thrown when a guest ticket's token is gone from this device.
class GuestCredentialsMissing implements Exception {
  const GuestCredentialsMissing();
}

/// One open ticket. The page drives [poll] through a [TicketPoller].
class TicketDetails extends AsyncNotifier<CustomerTicket> {
  TicketDetails(this.ticketRef);

  final TicketRef ticketRef;

  TicketRepository get _repository => ref.read(ticketRepositoryProvider);

  @override
  Future<CustomerTicket> build() {
    ref.watch(currentUserIdProvider);
    return _read();
  }

  Future<CustomerTicket> _read() async {
    // Read everything before the first await: the screen can close, and this
    // provider be disposed, while a request is in flight.
    final TicketRepository repository = _repository;
    if (!ticketRef.isGuest) {
      return repository.ticket(ticketRef.id);
    }
    final String? token = await ref
        .read(guestTicketVaultProvider)
        .tokenFor(ticketRef.id);
    if (token == null) {
      throw const GuestCredentialsMissing();
    }
    final CustomerTicket ticket = await repository.guestTicket(
      ticketRef.id,
      token,
    );
    if (ref.mounted) {
      await ref.read(guestTicketsProvider.notifier).update(ticket);
    }
    return ticket;
  }

  /// One refresh for the poller. Keeps the last good ticket on failure, so a
  /// lost connection does not blank the screen.
  Future<PollOutcome> poll() async {
    try {
      final CustomerTicket ticket = await _read();
      if (ref.mounted) {
        state = AsyncData<CustomerTicket>(ticket);
      }
      return ticket.isFinished ? PollOutcome.finished : PollOutcome.active;
    } on GuestCredentialsMissing catch (error, stackTrace) {
      if (ref.mounted) {
        state = AsyncError<CustomerTicket>(error, stackTrace);
      }
      return PollOutcome.finished;
    } on AppFailure catch (failure, stackTrace) {
      if (failure.kind == FailureKind.notFound) {
        if (ref.mounted) {
          state = AsyncError<CustomerTicket>(failure, stackTrace);
        }
        return PollOutcome.finished;
      }
      return PollOutcome.failed;
    }
  }

  Future<void> cancel() async {
    final CustomerTicket ticket;
    if (ticketRef.isGuest) {
      final String? token = await ref
          .read(guestTicketVaultProvider)
          .tokenFor(ticketRef.id);
      if (token == null) {
        throw const GuestCredentialsMissing();
      }
      ticket = await _repository.cancelGuestTicket(ticketRef.id, token);
      await ref.read(guestTicketsProvider.notifier).update(ticket);
    } else {
      ticket = await _repository.cancel(ticketRef.id);
      _refreshLists();
    }
    state = AsyncData<CustomerTicket>(ticket);
  }

  Future<void> review(int rating) async {
    final CustomerTicket ticket = await _repository.review(
      ticketRef.id,
      rating,
    );
    _refreshLists();
    state = AsyncData<CustomerTicket>(ticket);
  }

  void _refreshLists() => ref.invalidate(myTicketsProvider);
}

final ticketDetailsProvider = AsyncNotifierProvider.autoDispose
    .family<TicketDetails, CustomerTicket, TicketRef>(
      TicketDetails.new,
      retry: noAutomaticRetry,
      name: 'ticketDetailsProvider',
    );

/// Joins queues and remembers the result.
class TicketActions {
  const TicketActions(this._ref);

  final Ref _ref;

  Future<CustomerTicket> join(
    String queueId, {
    required String idempotencyKey,
  }) async {
    final CustomerTicket ticket = await _ref
        .read(ticketRepositoryProvider)
        .join(queueId, idempotencyKey: idempotencyKey);
    _ref.invalidate(myTicketsProvider);
    return ticket;
  }

  /// The token goes straight from the response into secure storage.
  Future<CustomerTicket> joinAsGuest(
    String queueId,
    GuestDetails details, {
    required String idempotencyKey,
  }) async {
    final GuestJoinResult result = await _ref
        .read(ticketRepositoryProvider)
        .joinAsGuest(queueId, details, idempotencyKey: idempotencyKey);
    await _ref
        .read(guestTicketsProvider.notifier)
        .save(result.ticket, result.accessToken);
    return result.ticket;
  }
}

final Provider<TicketActions> ticketActionsProvider = Provider<TicketActions>(
  TicketActions.new,
  name: 'ticketActionsProvider',
);
