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

/// Queue Details: the configuration, the current session and its controls,
/// and links to the dashboard, staff, QR code and history.
class QueueDetailsPage extends ConsumerStatefulWidget {
  const QueueDetailsPage({super.key, required this.queueId});

  final String queueId;

  @override
  ConsumerState<QueueDetailsPage> createState() => _QueueDetailsPageState();
}

class _QueueDetailsPageState extends ConsumerState<QueueDetailsPage> {
  bool _busy = false;

  Future<void> _sessionAction(
    Future<QueueSession> Function(QueueActions actions) action,
    String message,
  ) async {
    setState(() => _busy = true);
    await runQueueAction(
      context,
      () => action(ref.read(queueActionsProvider)),
      successMessage: message,
      onFailure: () => ref.invalidate(queueProvider(widget.queueId)),
    );
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  Future<void> _close() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.queueCloseConfirmTitle,
      message: l10n.queueCloseConfirmMessage,
      confirmLabel: l10n.queueClose,
      destructive: true,
    );
    if (confirmed && mounted) {
      await _sessionAction(
        (actions) => actions.close(widget.queueId),
        l10n.queueClosedMessage,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<QueueConfig> queue = ref.watch(
      queueProvider(widget.queueId),
    );

    return DoryPageScaffold(
      title: queue.value?.name ?? l10n.queuesTitle,
      body: LiveValueView<QueueConfig>(
        value: queue,
        onRetry: () => ref.invalidate(queueProvider(widget.queueId)),
        data: (value) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            _session(l10n, value),
            const SizedBox(height: AppSpacing.xl),
            _configuration(l10n, value),
            const SizedBox(height: AppSpacing.xl),
            DoryCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: <Widget>[
                  if (value.canManage)
                    _link(
                      Icons.tune_rounded,
                      l10n.queueEditTitle,
                      AppRoutes.queueEdit(value.id),
                    ),
                  _link(
                    Icons.groups_outlined,
                    l10n.queueStaffTitle,
                    AppRoutes.queueStaff(value.id),
                  ),
                  if (value.canManage)
                    _link(
                      Icons.qr_code_2_rounded,
                      l10n.queueQrTitle,
                      AppRoutes.queueQr(value.id),
                    ),
                  _link(
                    Icons.history_rounded,
                    l10n.queueHistoryTitle,
                    AppRoutes.queueSessions(value.id),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _link(IconData icon, String label, String location) => ListTile(
    leading: Icon(icon),
    title: Text(label),
    trailing: const Icon(Icons.chevron_right_rounded),
    onTap: () => context.go(location),
  );

  Widget _session(AppLocalizations l10n, QueueConfig queue) {
    final QueueSession? session = queue.currentSession;
    final TextTheme text = Theme.of(context).textTheme;
    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  queue.service?.name ?? queue.name,
                  style: text.titleMedium,
                ),
              ),
              if (queue.isActive)
                sessionStatusChip(l10n, session?.status)
              else
                sessionStatusChip(l10n, null),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          if (session == null)
            Text(l10n.queueSessionNone, style: text.bodyMedium)
          else
            Text(
              l10n.queueSessionOpenSince(
                formatClock(context, session.openedAt),
                session.sessionNumber,
              ),
              style: text.bodyMedium,
            ),
          if (session?.status == QueueSessionStatus.paused) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryNotice(
              message: l10n.queueSessionPausedNotice,
              tone: DoryNoticeTone.warning,
            ),
          ],
          if (!queue.isActive) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryNotice(
              message: l10n.queueErrorQueueInactive,
              tone: DoryNoticeTone.warning,
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          if (session != null)
            DoryButton(
              label: l10n.queueOpenDashboard,
              icon: Icons.space_dashboard_outlined,
              onPressed: () => context.go(AppRoutes.queueSession(session.id)),
            ),
          if (queue.canOperate) ...<Widget>[
            if (session == null && queue.isActive)
              DoryButton(
                label: l10n.queueOpen,
                icon: Icons.play_circle_outline_rounded,
                onPressed: () => context.go(AppRoutes.queueOpen(queue.id)),
              ),
            if (session?.status == QueueSessionStatus.open) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              DoryButton(
                label: l10n.queuePause,
                icon: Icons.pause_circle_outline_rounded,
                variant: DoryButtonVariant.secondary,
                isLoading: _busy,
                onPressed: () => _sessionAction(
                  (actions) => actions.pause(queue.id),
                  l10n.queuePausedMessage,
                ),
              ),
            ],
            if (session?.status == QueueSessionStatus.paused) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              DoryButton(
                label: l10n.queueResume,
                icon: Icons.play_circle_outline_rounded,
                variant: DoryButtonVariant.secondary,
                isLoading: _busy,
                onPressed: () => _sessionAction(
                  (actions) => actions.resume(queue.id),
                  l10n.queueResumed,
                ),
              ),
            ],
            if (session != null) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              DoryButton(
                label: l10n.queueClose,
                icon: Icons.stop_circle_outlined,
                variant: DoryButtonVariant.tertiary,
                onPressed: _busy ? null : _close,
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _configuration(AppLocalizations l10n, QueueConfig queue) {
    final int? capacity = queue.capacity;
    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          QueueInfoRow(label: l10n.queueBranchLabel, value: queue.branch.name),
          if (queue.service != null)
            QueueInfoRow(
              label: l10n.queueServiceLabel,
              value: queue.service!.name,
            ),
          QueueInfoRow(
            label: l10n.queuePrefixLabel,
            value: firstTicketExample(queue.ticketPrefix),
            valueDirection: TextDirection.ltr,
          ),
          QueueInfoRow(
            label: l10n.queueDurationLabel,
            value: l10n.serviceDurationValue(queue.effectiveServiceMinutes),
          ),
          QueueInfoRow(
            label: l10n.queueCapacityLabel,
            value: capacity == null
                ? l10n.queueCapacityNone
                : l10n.queueCapacityValue(capacity),
          ),
          QueueInfoRow(
            label: l10n.queueGraceLabel,
            value: l10n.queueGraceValue(queue.gracePeriodSeconds),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            queue.allowGuestJoin
                ? l10n.queueGuestsAllowed
                : l10n.queueGuestsNotAllowed,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

/// Open Queue Session: explains what opening does, then opens.
class OpenQueueSessionPage extends ConsumerStatefulWidget {
  const OpenQueueSessionPage({super.key, required this.queueId});

  final String queueId;

  @override
  ConsumerState<OpenQueueSessionPage> createState() =>
      _OpenQueueSessionPageState();
}

class _OpenQueueSessionPageState extends ConsumerState<OpenQueueSessionPage> {
  bool _busy = false;

  Future<void> _open() async {
    final AppLocalizations l10n = context.l10n;
    setState(() => _busy = true);
    QueueSession? session;
    final bool ok = await runQueueAction(
      context,
      () async =>
          session = await ref.read(queueActionsProvider).open(widget.queueId),
      successMessage: l10n.queueOpened,
    );
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok && session != null) {
      context.go(AppRoutes.queueSession(session!.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<QueueConfig> queue = ref.watch(
      queueProvider(widget.queueId),
    );
    return DoryPageScaffold(
      title: l10n.queueOpenTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: LiveValueView<QueueConfig>(
        value: queue,
        onRetry: () => ref.invalidate(queueProvider(widget.queueId)),
        data: (value) {
          final QueueSession? session = value.currentSession;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              DoryCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(
                      value.name,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      l10n.queueOpenMessage(
                        firstTicketExample(value.ticketPrefix),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      l10n.queueOpenGuestsNote,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              if (!value.isActive)
                DoryNotice(
                  message: l10n.queueErrorQueueInactive,
                  tone: DoryNoticeTone.warning,
                )
              else if (session != null) ...<Widget>[
                DoryNotice(message: l10n.queueErrorSessionAlreadyActive),
                const SizedBox(height: AppSpacing.lg),
                DoryButton(
                  label: l10n.queueOpenDashboard,
                  onPressed: () =>
                      context.go(AppRoutes.queueSession(session.id)),
                ),
              ] else if (value.canOperate)
                DoryButton(
                  label: l10n.queueOpen,
                  icon: Icons.play_circle_outline_rounded,
                  isLoading: _busy,
                  onPressed: _open,
                )
              else
                DoryNotice(message: l10n.errorForbidden),
            ],
          );
        },
      ),
    );
  }
}
