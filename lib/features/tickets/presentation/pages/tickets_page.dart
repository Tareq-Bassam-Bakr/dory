import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/tickets/data/guest_ticket_vault.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:dory_app/features/tickets/presentation/widgets/ticket_labels.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The customer's ticket center.
///
/// Signed-in customers see their tickets from the server. Tickets taken as a
/// guest stay on this device and are listed apart: they are not linked to an
/// account, and the screen never suggests otherwise. Lists refresh when
/// opened and on pull; only an open ticket polls.
class TicketsPage extends ConsumerStatefulWidget {
  const TicketsPage({super.key});

  @override
  ConsumerState<TicketsPage> createState() => _TicketsPageState();
}

class _TicketsPageState extends ConsumerState<TicketsPage> {
  TicketFilter _filter = TicketFilter.active;

  @override
  void initState() {
    super.initState();
    unawaited(
      Future<void>.microtask(
        () => ref.read(guestTicketsProvider.notifier).refreshActive(),
      ),
    );
  }

  Future<void> _refresh() async {
    await Future.wait(<Future<void>>[
      ref.read(guestTicketsProvider.notifier).refreshActive(),
      if (ref.read(currentUserIdProvider) != null)
        ref.read(myTicketsProvider(_filter).notifier).reload(),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final bool signedIn = ref.watch(currentUserIdProvider) != null;
    final TicketListState mine = ref.watch(myTicketsProvider(_filter));
    final List<StoredGuestTicket> guest = <StoredGuestTicket>[
      for (final StoredGuestTicket ticket in ref.watch(guestTicketsProvider))
        if (_filter.matches(ticket.status)) ticket,
    ];
    final Object? error = mine.error;
    final bool empty = mine.items.isEmpty && guest.isEmpty;

    return DoryPageScaffold(
      title: l10n.ticketsTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.pageGutter,
              ),
              children: <Widget>[
                for (final TicketFilter filter in TicketFilter.values)
                  Padding(
                    padding: const EdgeInsetsDirectional.only(
                      end: AppSpacing.sm,
                    ),
                    child: ChoiceChip(
                      label: Text(ticketFilterLabel(l10n, filter)),
                      selected: filter == _filter,
                      onSelected: (_) => setState(() => _filter = filter),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _refresh,
              child: NotificationListener<ScrollNotification>(
                onNotification: (notification) {
                  if (signedIn && notification.metrics.extentAfter < 300) {
                    unawaited(
                      ref.read(myTicketsProvider(_filter).notifier).loadMore(),
                    );
                  }
                  return false;
                },
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.pageGutter,
                    AppSpacing.sm,
                    AppSpacing.pageGutter,
                    AppSpacing.xl,
                  ),
                  children: <Widget>[
                    if (!signedIn) ...<Widget>[
                      const _AccountPrompt(),
                      const SizedBox(height: AppSpacing.md),
                    ],
                    if (error != null && mine.items.isEmpty)
                      DoryErrorState(
                        icon: Icons.cloud_off_rounded,
                        title: failureMessage(l10n, error),
                        retry: DoryStatusAction(
                          label: l10n.commonRetry,
                          onPressed: () => ref
                              .read(myTicketsProvider(_filter).notifier)
                              .reload(),
                        ),
                      )
                    else if (empty && mine.isLoading)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 48),
                        child: DoryLoadingIndicator(),
                      )
                    else if (empty)
                      DoryEmptyState(
                        icon: Icons.confirmation_number_outlined,
                        title: l10n.ticketsEmptyTitle,
                        message: l10n.ticketsEmptyMessage,
                        action: DoryStatusAction(
                          label: l10n.ticketsFindQueue,
                          onPressed: () =>
                              context.go(AppRoutes.customerExplore),
                        ),
                      ),
                    for (final CustomerTicket ticket in mine.items)
                      _TicketRow(
                        number: ticket.number,
                        status: ticket.status,
                        title: ticket.businessName,
                        subtitle: ticket.serviceName ?? ticket.queueName,
                        joinedAt: ticket.joinedAt,
                        onTap: () =>
                            context.push(AppRoutes.customerTicket(ticket.id)),
                      ),
                    if (mine.isLoading && mine.items.isNotEmpty)
                      const SizedBox(height: 64, child: DoryLoadingIndicator()),
                    if (guest.isNotEmpty) ...<Widget>[
                      if (signedIn) ...<Widget>[
                        const SizedBox(height: AppSpacing.md),
                        DorySectionHeader(title: l10n.ticketsOnThisDevice),
                        DoryNotice(message: l10n.ticketsGuestNotLinked),
                        const SizedBox(height: AppSpacing.sm),
                      ],
                      for (final StoredGuestTicket ticket in guest)
                        _GuestTicketRow(ticket: ticket),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Encourages an account without requiring one.
class _AccountPrompt extends StatelessWidget {
  const _AccountPrompt();

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            l10n.ticketsGuestPrompt,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          DoryButton(
            label: l10n.welcomeLogin,
            variant: DoryButtonVariant.tertiary,
            onPressed: () => context.go(AppRoutes.login),
          ),
        ],
      ),
    );
  }
}

class _GuestTicketRow extends ConsumerWidget {
  const _GuestTicketRow({required this.ticket});

  final StoredGuestTicket ticket;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    return _TicketRow(
      number: ticket.number,
      status: ticket.status,
      title: ticket.businessName,
      subtitle: ticket.serviceName ?? ticket.branchName,
      joinedAt: ticket.joinedAt,
      onTap: () =>
          context.push(AppRoutes.customerTicket(ticket.id, guest: true)),
      trailing: ticket.status.isActive
          ? null
          : IconButton(
              tooltip: l10n.ticketForget,
              icon: const Icon(Icons.delete_outline_rounded),
              onPressed: () =>
                  ref.read(guestTicketsProvider.notifier).remove(ticket.id),
            ),
    );
  }
}

class _TicketRow extends StatelessWidget {
  const _TicketRow({
    required this.number,
    required this.status,
    required this.title,
    required this.subtitle,
    required this.joinedAt,
    required this.onTap,
    this.trailing,
  });

  final String number;
  final TicketStatus status;
  final String title;
  final String subtitle;
  final DateTime joinedAt;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: DoryCard(
        padding: const EdgeInsets.all(AppSpacing.md),
        onTap: onTap,
        child: Row(
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    number,
                    style: text.titleLarge,
                    textDirection: TextDirection.ltr,
                  ),
                  Text(
                    title,
                    style: text.titleSmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    subtitle,
                    style: text.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Wrap(
                    spacing: AppSpacing.sm,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: <Widget>[
                      ticketStatusChip(l10n, status),
                      Text(
                        formatClock(context, joinedAt),
                        style: text.labelSmall,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            ?trailing,
          ],
        ),
      ),
    );
  }
}
