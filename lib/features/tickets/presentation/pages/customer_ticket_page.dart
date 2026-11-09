import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/config/ticket_config.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/realtime/realtime_providers.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_coordinator.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_providers.dart';
import 'package:dory_app/features/notifications/presentation/widgets/notification_widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/presentation/providers/live_ticket_sync.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_poller.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:dory_app/features/tickets/presentation/widgets/ticket_labels.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The digital ticket.
///
/// Updates come through Reverb when it is connected; otherwise, and while it
/// reconnects, the screen polls as before (LiveTicketSync). Either way it
/// reads the ticket from the API, only while it is really on screen (not in
/// the background, under another screen or in a hidden tab), and stops for
/// good once the ticket is finished. The screen says which mode it is in.
class CustomerTicketPage extends ConsumerStatefulWidget {
  const CustomerTicketPage({super.key, required this.ticket});

  final TicketRef ticket;

  @override
  ConsumerState<CustomerTicketPage> createState() => _CustomerTicketPageState();
}

class _CustomerTicketPageState extends ConsumerState<CustomerTicketPage>
    with WidgetsBindingObserver {
  late final LiveTicketSync _sync = LiveTicketSync(
    poller: TicketPoller(
      refresh: () =>
          ref.read(ticketDetailsProvider(widget.ticket).notifier).poll(),
    ),
  );

  bool _appVisible = true;
  bool _cancelling = false;
  List<String> _channels = const <String>[];
  late final VisibleTicket _visibleTicket;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _visibleTicket = ref.read(visibleTicketProvider);
    ref.listenManual(
      ticketDetailsProvider(widget.ticket),
      (previous, next) => _onTicket(next.value),
      fireImmediately: true,
    );
    // A new account (sign in or out) means a new connection.
    ref.listenManual(
      realtimeClientProvider,
      (previous, next) => _sync.attach(next, _channels),
    );
  }

  void _onTicket(CustomerTicket? ticket) {
    if (ticket == null) {
      return;
    }
    if (ticket.isFinished) {
      // Nothing more can change: stop listening and asking.
      _sync.finish();
      return;
    }
    _channels = ticket.realtimeChannels;
    _sync.attach(ref.read(realtimeClientProvider), _channels);
    // A guest ticket kept on this device gets push alerts when allowed.
    if (widget.ticket.isGuest) {
      unawaited(
        ref.read(deviceRegistrationProvider).syncGuestTicket(ticket.id),
      );
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncPolling();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appVisible = state == AppLifecycleState.resumed;
    _syncPolling();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sync.dispose();
    _visibleTicket.hide(widget.ticket.id);
    super.dispose();
  }

  /// Reads only while the app is in front, this route is on top and this
  /// tab is showing.
  void _syncPolling() {
    final bool visible =
        _appVisible &&
        TickerMode.valuesOf(context).enabled &&
        (ModalRoute.of(context)?.isCurrent ?? true);
    _sync.setVisible(visible);
    // Alerts about this ticket are not shown on top of it.
    visible
        ? _visibleTicket.show(widget.ticket.id)
        : _visibleTicket.hide(widget.ticket.id);
  }

  Future<void> _cancel(CustomerTicket ticket) async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.ticketCancelConfirmTitle,
      message: l10n.ticketCancelConfirmMessage(ticket.number),
      confirmLabel: l10n.ticketCancelAction,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    setState(() => _cancelling = true);
    try {
      await ref.read(ticketDetailsProvider(widget.ticket).notifier).cancel();
      if (mounted) {
        showResultMessage(context, l10n.ticketCancelled);
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, ticketFailureMessage(l10n, failure));
      }
    } on GuestCredentialsMissing {
      if (mounted) {
        showResultMessage(context, l10n.ticketCredentialsMissingTitle);
      }
    } finally {
      if (mounted) {
        setState(() => _cancelling = false);
      }
    }
  }

  Future<void> _rate(int rating) async {
    final AppLocalizations l10n = context.l10n;
    try {
      await ref
          .read(ticketDetailsProvider(widget.ticket).notifier)
          .review(rating);
      if (mounted) {
        showResultMessage(context, l10n.ticketRatingThanks);
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, ticketFailureMessage(l10n, failure));
      }
    }
  }

  Future<void> _forget() async {
    await ref.read(guestTicketsProvider.notifier).remove(widget.ticket.id);
    if (mounted) {
      context.go(AppRoutes.customerTickets);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<CustomerTicket> value = ref.watch(
      ticketDetailsProvider(widget.ticket),
    );

    return DoryPageScaffold(
      title: l10n.ticketTitle,
      body: switch (value) {
        AsyncData(:final CustomerTicket value) => _ticketBody(value),
        AsyncError(:final Object error) => _failure(l10n, error),
        _ => const Padding(
          padding: EdgeInsets.symmetric(vertical: 48),
          child: DoryLoadingIndicator(),
        ),
      },
    );
  }

  Widget _failure(AppLocalizations l10n, Object error) {
    final bool gone =
        error is GuestCredentialsMissing ||
        (error is AppFailure && error.kind == FailureKind.notFound);

    if (widget.ticket.isGuest && gone) {
      return DoryErrorState(
        icon: Icons.lock_clock_outlined,
        title: l10n.ticketCredentialsMissingTitle,
        message: l10n.ticketCredentialsMissingMessage,
        retry: DoryStatusAction(label: l10n.ticketForget, onPressed: _forget),
      );
    }
    return DoryErrorState(
      title: l10n.commonGenericErrorTitle,
      message: failureMessage(l10n, error),
      retry: DoryStatusAction(
        label: l10n.commonRetry,
        onPressed: () => ref.invalidate(ticketDetailsProvider(widget.ticket)),
      ),
    );
  }

  Widget _ticketBody(CustomerTicket ticket) {
    final AppLocalizations l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        _TicketFace(ticket: ticket),
        const SizedBox(height: AppSpacing.md),
        _Details(ticket: ticket),
        if (ticket.canReview || ticket.myRating != null) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          _Rating(ticket: ticket, onRate: _rate),
        ],
        if (ticket.status.isActive) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          NotificationOffer(
            guestTicketId: widget.ticket.isGuest ? ticket.id : null,
          ),
          const SizedBox(height: AppSpacing.md),
          ValueListenableBuilder<bool>(
            valueListenable: _sync.live,
            builder: (context, live, _) => Text(
              live
                  ? l10n.ticketLiveNote
                  : l10n.ticketPollingNote(TicketConfig.pollInterval.inSeconds),
              style: Theme.of(context).textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
          ),
        ],
        // Finished tickets offer no ordinary actions.
        if (ticket.canCancel) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.ticketCancelAction,
            variant: DoryButtonVariant.secondary,
            icon: Icons.close_rounded,
            isLoading: _cancelling,
            onPressed: () => _cancel(ticket),
          ),
        ],
        if (widget.ticket.isGuest && ticket.isFinished) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.ticketForget,
            variant: DoryButtonVariant.tertiary,
            onPressed: _forget,
          ),
        ],
      ],
    );
  }
}

class _TicketFace extends StatelessWidget {
  const _TicketFace({required this.ticket});

  final CustomerTicket ticket;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final bool live = ticket.status.isActive;
    final int? position = ticket.position;
    final int? minutes = ticket.estimatedMinutes;
    final int? low = ticket.waitRangeLowMinutes;
    final int? high = ticket.waitRangeHighMinutes;
    final DateTime? arriveBy = ticket.arriveBy;

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: live ? AppColors.brandGradient : null,
        color: live ? null : AppColors.surfaceStrong,
        borderRadius: AppRadii.xlAll,
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          children: <Widget>[
            Semantics(
              label: l10n.ticketNumberSemantics(ticket.number),
              excludeSemantics: true,
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  ticket.number,
                  textDirection: TextDirection.ltr,
                  style: text.displayMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: live ? AppColors.onPrimary : AppColors.textPrimary,
                  ),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              ticketStatusHeadline(l10n, ticket.status),
              style: text.titleMedium?.copyWith(
                color: live ? AppColors.onPrimary : AppColors.textSecondary,
              ),
              textAlign: TextAlign.center,
            ),
            if (position != null) ...<Widget>[
              const SizedBox(height: AppSpacing.md),
              Text(
                l10n.ticketPosition(position),
                style: text.headlineSmall?.copyWith(color: AppColors.onPrimary),
              ),
              Text(
                l10n.ticketPeopleAhead(ticket.peopleAhead ?? 0),
                style: text.bodyMedium?.copyWith(color: AppColors.onPrimary),
              ),
            ],
            if (minutes != null) ...<Widget>[
              const SizedBox(height: AppSpacing.xs),
              Text(
                waitLabel(l10n, minutes),
                style: text.bodyMedium?.copyWith(color: AppColors.onPrimary),
              ),
              if (low != null && high != null && high > low)
                Text(
                  l10n.ticketWaitRange(low, high),
                  style: text.bodySmall?.copyWith(color: AppColors.onPrimary),
                ),
            ],
            if (arriveBy != null) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              Text(
                l10n.ticketArriveBy(formatClock(context, arriveBy)),
                style: text.titleSmall?.copyWith(color: AppColors.onPrimary),
                textAlign: TextAlign.center,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Details extends StatelessWidget {
  const _Details({required this.ticket});

  final CustomerTicket ticket;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final String place = <String>[
      ticket.branchName,
      ?ticket.branchCity,
    ].join(' · ');

    Widget row(String label, String value) => Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.bodySmall),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            flex: 2,
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  ticket.businessName,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              ticketStatusChip(l10n, ticket.status),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          row(l10n.ticketBranch, place),
          if (ticket.branchAddress != null)
            row(l10n.ticketAddress, ticket.branchAddress!),
          row(l10n.ticketService, ticket.serviceName ?? ticket.queueName),
          row(l10n.ticketJoinedAt, formatClock(context, ticket.joinedAt)),
          row(
            l10n.ticketSession,
            <String>[
              formatBusinessDate(context, ticket.businessDate),
              if (ticket.status.isActive) _sessionLabel(l10n),
            ].join(' · '),
          ),
          if (ticket.status.isActive && ticket.nowServing.isNotEmpty)
            row(l10n.ticketServingLabel, ticket.nowServing.join(', ')),
        ],
      ),
    );
  }

  String _sessionLabel(AppLocalizations l10n) => switch (ticket.sessionStatus) {
    QueueSessionStatus.open => l10n.queueStateOpen,
    QueueSessionStatus.paused => l10n.queueStatePaused,
    QueueSessionStatus.closed => l10n.queueStateClosed,
  };
}

class _Rating extends StatelessWidget {
  const _Rating({required this.ticket, required this.onRate});

  final CustomerTicket ticket;
  final ValueChanged<int> onRate;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final int? rating = ticket.myRating;

    return DoryCard(
      child: Column(
        children: <Widget>[
          Text(
            rating == null ? l10n.ticketRatePrompt : l10n.ticketRated,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              for (int star = 1; star <= 5; star++)
                IconButton(
                  tooltip: l10n.ticketRateStars(star),
                  onPressed: ticket.canReview ? () => onRate(star) : null,
                  icon: Icon(
                    star <= (rating ?? 0)
                        ? Icons.star_rounded
                        : Icons.star_outline_rounded,
                    color: AppColors.warning,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
