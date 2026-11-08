import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Ticket Details: customer, status, history and the actions staff may take.
class TicketDetailsPage extends ConsumerStatefulWidget {
  const TicketDetailsPage({
    super.key,
    required this.sessionId,
    required this.ticketId,
  });

  final String sessionId;
  final String ticketId;

  @override
  ConsumerState<TicketDetailsPage> createState() => _TicketDetailsPageState();
}

class _TicketDetailsPageState extends ConsumerState<TicketDetailsPage> {
  bool _busy = false;

  void _refresh() => ref.invalidate(staffTicketProvider(widget.ticketId));

  Future<void> _act(TicketAction action) async {
    if (!await confirmTicketAction(context, action) || !mounted) {
      return;
    }
    setState(() => _busy = true);
    await runQueueAction(
      context,
      () => ref
          .read(queueActionsProvider)
          .act(widget.sessionId, widget.ticketId, action),
      successMessage: context.l10n.queueActionDone,
      onFailure: _refresh,
    );
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<StaffTicket> ticket = ref.watch(
      staffTicketProvider(widget.ticketId),
    );
    final QueueDashboard? dashboard = ref
        .watch(queueDashboardProvider(widget.sessionId))
        .value;

    return AutoRefresh(
      onTick: _refresh,
      channels: <String>['private-queue-sessions.${widget.sessionId}'],
      child: DoryPageScaffold(
        title: ticket.value == null
            ? l10n.queueDashboardTitle
            : l10n.queueTicketTitle(ticket.value!.number),
        body: LiveValueView<StaffTicket>(
          value: ticket,
          onRetry: _refresh,
          data: (value) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              DoryCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Center(
                      child: Text(
                        value.number,
                        textDirection: TextDirection.ltr,
                        style: Theme.of(context).textTheme.displaySmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Center(child: ticketStatusChip(l10n, value.status)),
                    const SizedBox(height: AppSpacing.lg),
                    QueueInfoRow(
                      label: l10n.queueTicketCustomer,
                      value: customerLabel(l10n, value),
                    ),
                    if (value.customerPhone != null)
                      QueueInfoRow(
                        label: l10n.queueTicketPhone,
                        value: value.customerPhone!,
                        valueDirection: TextDirection.ltr,
                      ),
                    if (value.position != null)
                      QueueInfoRow(
                        label: l10n.queueTicketPosition,
                        value: '${value.position}',
                      ),
                    QueueInfoRow(
                      label: l10n.queueTicketJoined,
                      value: formatClock(context, value.joinedAt),
                    ),
                    if (value.servedByName != null)
                      QueueInfoRow(
                        label: l10n.queueTicketStatus,
                        value: value.servedByMe
                            ? l10n.queueServedByYou
                            : l10n.queueServedBy(value.servedByName!),
                      ),
                  ],
                ),
              ),
              if (dashboard?.canOperate ?? false) ...<Widget>[
                const SizedBox(height: AppSpacing.lg),
                TicketActionButtons(
                  ticket: value,
                  canManage: dashboard?.canManage ?? false,
                  busy: _busy,
                  onAction: _act,
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
              DorySectionHeader(title: l10n.queueTicketHistory),
              DoryCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: <Widget>[
                    for (final TicketEvent event in value.events.reversed)
                      ListTile(
                        dense: true,
                        leading: const Icon(Icons.circle_outlined, size: 14),
                        title: Text(ticketEventLabel(l10n, event.type)),
                        subtitle: event.actorName == null
                            ? null
                            : Text(event.actorName!),
                        trailing: Text(formatClock(context, event.at)),
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
