import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:dory_app/core/realtime/realtime_providers.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// How often live queue screens reload when real-time is unavailable.
const Duration queueRefreshInterval = Duration(seconds: 15);

/// Keeps a staff screen current (docs/REALTIME_ARCHITECTURE.md).
///
/// With [channels] and a live Reverb connection, [onTick] runs when the
/// queue changes (bursts collapse into one call) and after every
/// (re)subscription, and the timer is off. Otherwise, and whenever the
/// connection drops, [onTick] runs every [interval] as before.
class AutoRefresh extends ConsumerStatefulWidget {
  const AutoRefresh({
    super.key,
    required this.onTick,
    required this.child,
    this.interval = queueRefreshInterval,
    this.channels = const <String>[],
  });

  final VoidCallback onTick;
  final Widget child;
  final Duration interval;
  final List<String> channels;

  @override
  ConsumerState<AutoRefresh> createState() => _AutoRefreshState();
}

class _AutoRefreshState extends ConsumerState<AutoRefresh> {
  Timer? _timer;
  Timer? _debounce;
  PusherClient? _client;
  final List<RealtimeSubscription> _subscriptions = <RealtimeSubscription>[];
  final List<StreamSubscription<Object?>> _listeners =
      <StreamSubscription<Object?>>[];
  bool _live = false;

  @override
  void initState() {
    super.initState();
    _startTimer();
    ref.listenManual<PusherClient?>(
      realtimeClientProvider,
      (previous, next) => _attach(next),
      fireImmediately: true,
    );
  }

  void _attach(PusherClient? client) {
    _detach();
    _client = client;
    if (client == null || widget.channels.isEmpty) {
      return;
    }
    for (final String channel in widget.channels) {
      final RealtimeSubscription subscription = client.subscribe(channel);
      _subscriptions.add(subscription);
      _listeners.add(
        subscription.events.listen((event) {
          if (event.kind == RealtimeEventKind.message) {
            _changed();
          } else {
            _evaluate();
          }
        }),
      );
    }
    _listeners.add(client.statusChanges.listen((_) => _evaluate()));
    _evaluate();
  }

  void _evaluate() {
    final PusherClient? client = _client;
    final bool live =
        client != null &&
        client.isConnected &&
        _subscriptions.isNotEmpty &&
        _subscriptions.every((subscription) => subscription.isSubscribed);
    if (live == _live) {
      return;
    }
    _live = live;
    if (live) {
      _timer?.cancel();
      _timer = null;
      // Events missed while away are not replayed: read once.
      _changed();
    } else {
      _startTimer();
    }
  }

  void _changed() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 250), () {
      if (mounted) {
        widget.onTick();
      }
    });
  }

  void _startTimer() {
    _timer ??= Timer.periodic(widget.interval, (_) => widget.onTick());
  }

  void _detach() {
    for (final StreamSubscription<Object?> listener in _listeners) {
      unawaited(listener.cancel());
    }
    _listeners.clear();
    for (final RealtimeSubscription subscription in _subscriptions) {
      subscription.cancel();
    }
    _subscriptions.clear();
    _client = null;
    if (_live) {
      _live = false;
      _startTimer();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _debounce?.cancel();
    _detach();
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Like AsyncValueView, but keeps showing the last data while a refresh is
/// running, so polling does not flash a spinner.
class LiveValueView<T> extends StatelessWidget {
  const LiveValueView({
    super.key,
    required this.value,
    required this.data,
    required this.onRetry,
  });

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    if (value.hasValue) {
      return data(value.requireValue);
    }
    if (value.hasError) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 48),
        child: DoryErrorState(
          title: l10n.commonGenericErrorTitle,
          message: queueFailureMessage(l10n, value.error!),
          retry: DoryStatusAction(label: l10n.commonRetry, onPressed: onRetry),
        ),
      );
    }
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 48),
      child: DoryLoadingIndicator(),
    );
  }
}

/// Runs a queue action and reports the outcome in a snackbar. Returns
/// whether it succeeded.
Future<bool> runQueueAction(
  BuildContext context,
  Future<void> Function() action, {
  String? successMessage,
  VoidCallback? onFailure,
}) async {
  final AppLocalizations l10n = context.l10n;
  try {
    await action();
    if (context.mounted && successMessage != null) {
      showResultMessage(context, successMessage);
    }
    return true;
  } on AppFailure catch (failure) {
    onFailure?.call();
    if (context.mounted) {
      showResultMessage(context, queueFailureMessage(l10n, failure));
    }
    return false;
  } on Object catch (error) {
    onFailure?.call();
    if (context.mounted) {
      showResultMessage(context, failureMessage(l10n, error));
    }
    return false;
  }
}

/// A row in a ticket list: number, customer, position or wait, status.
class StaffTicketTile extends StatelessWidget {
  const StaffTicketTile({super.key, required this.ticket, this.onTap});

  final StaffTicket ticket;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final int? position = ticket.position;
    final String? server = ticket.servedByName;
    final List<String> details = <String>[
      if (position != null) l10n.queuePositionValue(position),
      l10n.queueWaitedValue((ticket.waitedSeconds / 60).floor()),
      if (ticket.servedByMe)
        l10n.queueServedByYou
      else if (server != null)
        l10n.queueServedBy(server),
    ];
    return ListTile(
      leading: SizedBox(
        width: 72,
        child: Text(
          ticket.number,
          textDirection: TextDirection.ltr,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      title: Text(customerLabel(l10n, ticket)),
      subtitle: Text(details.join(' · ')),
      trailing: ticketStatusChip(l10n, ticket.status),
      onTap: onTap,
    );
  }
}

/// The staff actions allowed for [ticket] in its current state. The server
/// re-checks every rule; this only hides what cannot work.
class TicketActionButtons extends StatelessWidget {
  const TicketActionButtons({
    super.key,
    required this.ticket,
    required this.canManage,
    required this.onAction,
    this.busy = false,
  });

  final StaffTicket ticket;
  final bool canManage;
  final bool busy;
  final ValueChanged<TicketAction> onAction;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final bool mine = ticket.servedByMe || canManage;
    final DateTime? noShowAt = ticket.noShowAllowedAt;
    final bool noShowReady =
        noShowAt == null || !DateTime.now().isBefore(noShowAt);

    final List<(TicketAction, String, IconData, DoryButtonVariant)> actions =
        switch (ticket.status) {
          TicketStatus.waiting =>
            <(TicketAction, String, IconData, DoryButtonVariant)>[
              if (canManage)
                (
                  TicketAction.call,
                  l10n.queueActionCall,
                  Icons.campaign_outlined,
                  DoryButtonVariant.primary,
                ),
              (
                TicketAction.skip,
                l10n.queueActionSkip,
                Icons.skip_next_outlined,
                DoryButtonVariant.tertiary,
              ),
            ],
          TicketStatus.called when mine =>
            <(TicketAction, String, IconData, DoryButtonVariant)>[
              (
                TicketAction.start,
                l10n.queueActionStart,
                Icons.play_arrow_rounded,
                DoryButtonVariant.primary,
              ),
              (
                TicketAction.recall,
                l10n.queueActionRecall,
                Icons.undo_rounded,
                DoryButtonVariant.secondary,
              ),
              if (noShowReady)
                (
                  TicketAction.noShow,
                  l10n.queueActionNoShow,
                  Icons.person_off_outlined,
                  DoryButtonVariant.tertiary,
                ),
            ],
          TicketStatus.serving when mine =>
            <(TicketAction, String, IconData, DoryButtonVariant)>[
              (
                TicketAction.complete,
                l10n.queueActionComplete,
                Icons.check_rounded,
                DoryButtonVariant.primary,
              ),
            ],
          _ => const <(TicketAction, String, IconData, DoryButtonVariant)>[],
        };

    if (actions.isEmpty) {
      return const SizedBox.shrink();
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final (
              TicketAction action,
              String label,
              IconData icon,
              DoryButtonVariant variant,
            )
            in actions) ...<Widget>[
          DoryButton(
            label: label,
            icon: icon,
            variant: variant,
            onPressed: busy ? null : () => onAction(action),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        if (ticket.status == TicketStatus.called && !noShowReady)
          Text(
            l10n.queueNoShowAvailableAt(formatClock(context, noShowAt)),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
      ],
    );
  }
}

/// Asks before skipping; other actions run directly.
Future<bool> confirmTicketAction(
  BuildContext context,
  TicketAction action,
) async {
  if (action != TicketAction.skip) {
    return true;
  }
  final AppLocalizations l10n = context.l10n;
  return confirmAction(
    context,
    title: l10n.queueSkipConfirmTitle,
    message: l10n.queueSkipConfirmMessage,
    confirmLabel: l10n.queueActionSkip,
    destructive: true,
  );
}

/// A label/value line in a details card.
class QueueInfoRow extends StatelessWidget {
  const QueueInfoRow({
    super.key,
    required this.label,
    required this.value,
    this.valueDirection,
  });

  final String label;
  final String value;
  final TextDirection? valueDirection;

  @override
  Widget build(BuildContext context) {
    final TextTheme text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            child: Text(
              label,
              style: text.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              textDirection: valueDirection,
              style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
