import 'dart:async';

import 'package:dory_app/features/tickets/data/api_ticket_repository.dart';
import 'package:dory_app/features/tickets/data/guest_ticket_vault.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/domain/repositories/ticket_repository.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/test_app.dart';
import '../customer/customer_fixtures.dart';

/// Found on a phone: a ticket read still in flight when its screen closed
/// used the disposed provider and threw.
void main() {
  const TicketRef guestTicket = TicketRef(customerTicketId, isGuest: true);

  test(
    'a guest ticket read that ends after its screen closed is harmless',
    () async {
      final Completer<CustomerTicket> response = Completer<CustomerTicket>();
      final ProviderContainer container = createTestContainer(
        overrides: [
          ticketRepositoryProvider.overrideWithValue(
            _SlowRepository(response.future),
          ),
          guestTicketVaultProvider.overrideWithValue(_Vault()),
        ],
      );

      // The ticket screen is open; the poller starts a refresh.
      final ProviderSubscription<AsyncValue<CustomerTicket>> screen = container
          .listen(ticketDetailsProvider(guestTicket), (_, _) {});
      final Future<PollOutcome> refresh = container
          .read(ticketDetailsProvider(guestTicket).notifier)
          .poll();
      await Future<void>.delayed(Duration.zero);

      // The customer leaves the screen before the server answers.
      screen.close();
      await container.pump();
      expect(container.exists(ticketDetailsProvider(guestTicket)), isFalse);

      response.complete(
        ApiTicketRepository.ticketFromJson(customerTicketJson()),
      );
      expect(await refresh, PollOutcome.active);
    },
  );
}

class _SlowRepository implements TicketRepository {
  _SlowRepository(this._answer);

  final Future<CustomerTicket> _answer;

  @override
  Future<CustomerTicket> guestTicket(String ticketId, String accessToken) =>
      _answer;

  @override
  Object? noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _Vault implements GuestTicketVault {
  @override
  Future<String?> tokenFor(String ticketId) async => guestAccessToken;

  @override
  Object? noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
