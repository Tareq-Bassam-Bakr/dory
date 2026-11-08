import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Waiting Tickets List, in queue order.
class WaitingTicketsPage extends StatelessWidget {
  const WaitingTicketsPage({super.key, required this.sessionId});

  final String sessionId;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return _SessionTicketsPage(
      sessionId: sessionId,
      title: l10n.queueWaitingTitle,
      statuses: const <TicketStatus>[TicketStatus.waiting],
      empty: l10n.queueNobodyWaiting,
    );
  }
}

/// Currently Serving Tickets: called and in service.
class ServingTicketsPage extends StatelessWidget {
  const ServingTicketsPage({super.key, required this.sessionId});

  final String sessionId;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return _SessionTicketsPage(
      sessionId: sessionId,
      title: l10n.queueServingTitle,
      statuses: const <TicketStatus>[TicketStatus.called, TicketStatus.serving],
      empty: l10n.queueNobodyServed,
    );
  }
}

class _SessionTicketsPage extends ConsumerWidget {
  const _SessionTicketsPage({
    required this.sessionId,
    required this.title,
    required this.statuses,
    required this.empty,
  });

  final String sessionId;
  final String title;
  final List<TicketStatus> statuses;
  final String empty;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ({String sessionId, String statuses}) key = sessionTicketsKey(
      sessionId,
      statuses,
    );
    final AsyncValue<List<StaffTicket>> tickets = ref.watch(
      sessionTicketsProvider(key),
    );
    void refresh() => ref.invalidate(sessionTicketsProvider(key));

    return AutoRefresh(
      onTick: refresh,
      channels: <String>['private-queue-sessions.$sessionId'],
      child: DoryPageScaffold(
        title: title,
        body: LiveValueView<List<StaffTicket>>(
          value: tickets,
          onRetry: refresh,
          data: (items) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const RefreshModeNote(),
              const SizedBox(height: 12),
              if (items.isEmpty)
                DoryCard(
                  child: DoryEmptyState(
                    icon: Icons.inbox_outlined,
                    title: empty,
                    message: '',
                  ),
                )
              else
                DoryCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: <Widget>[
                      for (final StaffTicket ticket in items)
                        StaffTicketTile(
                          ticket: ticket,
                          onTap: () => context.go(
                            AppRoutes.queueTicket(sessionId, ticket.id),
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
