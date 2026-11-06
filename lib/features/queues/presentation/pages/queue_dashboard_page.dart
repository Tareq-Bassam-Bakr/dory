import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Active Queue Dashboard: counts, the next customers, who is being served,
/// and the operator's own customer with its actions. Polls the server.
class QueueDashboardPage extends ConsumerStatefulWidget {
  const QueueDashboardPage({super.key, required this.sessionId});

  final String sessionId;

  @override
  ConsumerState<QueueDashboardPage> createState() => _QueueDashboardPageState();
}

class _QueueDashboardPageState extends ConsumerState<QueueDashboardPage> {
  bool _busy = false;

  void _refresh() => ref.invalidate(queueDashboardProvider(widget.sessionId));

  Future<void> _callNext() async {
    final AppLocalizations l10n = context.l10n;
    setState(() => _busy = true);
    StaffTicket? called;
    await runQueueAction(
      context,
      () async => called = await ref
          .read(queueActionsProvider)
          .callNext(widget.sessionId),
      onFailure: _refresh,
    );
    if (mounted) {
      setState(() => _busy = false);
      if (called != null) {
        showResultMessage(context, l10n.queueCalledMessage(called!.number));
      }
    }
  }

  Future<void> _act(StaffTicket ticket, TicketAction action) async {
    if (!await confirmTicketAction(context, action) || !mounted) {
      return;
    }
    setState(() => _busy = true);
    await runQueueAction(
      context,
      () => ref
          .read(queueActionsProvider)
          .act(widget.sessionId, ticket.id, action),
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
    final AsyncValue<QueueDashboard> dashboard = ref.watch(
      queueDashboardProvider(widget.sessionId),
    );

    return AutoRefresh(
      onTick: _refresh,
      child: DoryPageScaffold(
        title: dashboard.value?.queueName ?? l10n.queueDashboardTitle,
        body: LiveValueView<QueueDashboard>(
          value: dashboard,
          onRetry: _refresh,
          data: (value) => _body(l10n, value),
        ),
      ),
    );
  }

  Widget _body(AppLocalizations l10n, QueueDashboard value) {
    final StaffTicket? mine = value.myTicket;
    final bool live = value.isCurrentSession;
    final int missed =
        value.count(TicketStatus.noShow) + value.count(TicketStatus.skipped);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                l10n.queueAutoRefreshNote(queueRefreshInterval.inSeconds),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            sessionStatusChip(l10n, value.session.status),
          ],
        ),
        if (!live) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryNotice(message: l10n.queueSessionEnded),
        ] else if (value.session.status ==
            QueueSessionStatus.paused) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryNotice(
            message: l10n.queueSessionPausedNotice,
            tone: DoryNoticeTone.warning,
          ),
        ],
        const SizedBox(height: AppSpacing.lg),
        _metricRow(
          DoryMetricTile(
            label: l10n.queueCountWaiting,
            icon: Icons.hourglass_top_rounded,
            value: '${value.count(TicketStatus.waiting)}',
            noValueLabel: '0',
          ),
          DoryMetricTile(
            label: l10n.queueCountInService,
            icon: Icons.support_agent_rounded,
            value:
                '${value.count(TicketStatus.called) + value.count(TicketStatus.serving)}',
            noValueLabel: '0',
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        _metricRow(
          DoryMetricTile(
            label: l10n.queueCountDone,
            icon: Icons.check_circle_outline_rounded,
            value: '${value.count(TicketStatus.completed)}',
            noValueLabel: '0',
          ),
          DoryMetricTile(
            label: l10n.queueEstimateLabel,
            icon: Icons.timer_outlined,
            value: l10n.queueEstimateValue(value.estimatedWaitMinutes),
            noValueLabel: '-',
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          '${value.estimateFromHistory ? l10n.queueEstimateBasisHistory : l10n.queueEstimateBasisConfigured}'
          '${missed > 0 ? ' · ${l10n.queueCountMissed}: $missed' : ''}',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpacing.xl),
        if (value.canOperate && mine != null) ...<Widget>[
          DorySectionHeader(title: l10n.queueYourCustomer),
          DoryCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                StaffTicketTile(
                  ticket: mine,
                  onTap: () => context.go(
                    AppRoutes.queueTicket(widget.sessionId, mine.id),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                TicketActionButtons(
                  ticket: mine,
                  canManage: value.canManage,
                  busy: _busy,
                  onAction: (action) => _act(mine, action),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
        ] else if (value.canOperate && live) ...<Widget>[
          DoryButton(
            label: l10n.queueCallNext,
            icon: Icons.campaign_outlined,
            isLoading: _busy,
            onPressed: value.count(TicketStatus.waiting) == 0
                ? null
                : _callNext,
          ),
          const SizedBox(height: AppSpacing.xl),
        ],
        DorySectionHeader(title: l10n.queueNextUp),
        _ticketList(
          value.nextUp,
          empty: l10n.queueNobodyWaiting,
          more: l10n.queueViewAllWaiting,
          moreLocation: AppRoutes.queueSessionWaiting(widget.sessionId),
        ),
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.queueInServiceTitle),
        _ticketList(
          value.inService,
          empty: l10n.queueNobodyServed,
          more: l10n.queueViewAllServing,
          moreLocation: AppRoutes.queueSessionServing(widget.sessionId),
        ),
      ],
    );
  }

  /// Two tiles side by side, as tall as the taller one.
  Widget _metricRow(Widget first, Widget second) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Expanded(child: first),
        const SizedBox(width: AppSpacing.md),
        Expanded(child: second),
      ],
    ),
  );

  Widget _ticketList(
    List<StaffTicket> tickets, {
    required String empty,
    required String more,
    required String moreLocation,
  }) {
    return DoryCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: <Widget>[
          if (tickets.isEmpty)
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Text(empty),
            )
          else
            for (final StaffTicket ticket in tickets)
              StaffTicketTile(
                ticket: ticket,
                onTap: () => context.go(
                  AppRoutes.queueTicket(widget.sessionId, ticket.id),
                ),
              ),
          ListTile(
            title: Text(more),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.go(moreLocation),
          ),
        ],
      ),
    );
  }
}
