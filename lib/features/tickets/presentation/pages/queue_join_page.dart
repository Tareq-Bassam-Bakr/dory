import 'dart:math';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:dory_app/features/tickets/presentation/widgets/ticket_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Confirms a queue before joining it, signed in or as a guest.
///
/// The queue's state is read from the server when this screen opens, and the
/// server decides again when the customer joins. One idempotency key lives
/// as long as this screen, so a double tap or a retry after a lost response
/// cannot create a second ticket.
class QueueJoinPage extends ConsumerStatefulWidget {
  const QueueJoinPage({super.key, required this.queueId});

  final String queueId;

  @override
  ConsumerState<QueueJoinPage> createState() => _QueueJoinPageState();
}

class _QueueJoinPageState extends ConsumerState<QueueJoinPage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final String _idempotencyKey = _newKey();

  bool _consent = false;
  bool _joining = false;
  Map<String, String> _fieldErrors = const <String, String>{};
  String? _error;

  static String _newKey() {
    const String alphabet =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final Random random = Random.secure();
    return String.fromCharCodes(
      Iterable<int>.generate(
        32,
        (_) => alphabet.codeUnitAt(random.nextInt(alphabet.length)),
      ),
    );
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _join(JoinableQueue target, {required bool asGuest}) async {
    final AppLocalizations l10n = context.l10n;
    if (_joining || (asGuest && !(_form.currentState?.validate() ?? false))) {
      return;
    }

    setState(() {
      _joining = true;
      _error = null;
      _fieldErrors = const <String, String>{};
    });

    final TicketActions actions = ref.read(ticketActionsProvider);
    try {
      final CustomerTicket ticket = asGuest
          ? await actions.joinAsGuest(
              widget.queueId,
              GuestDetails(
                name: _name.text,
                phone: _phone.text,
                consent: _consent,
              ),
              idempotencyKey: _idempotencyKey,
            )
          : await actions.join(widget.queueId, idempotencyKey: _idempotencyKey);
      if (!mounted) {
        return;
      }
      showResultMessage(context, l10n.joinConfirmed(ticket.number));
      context.pushReplacement(
        AppRoutes.customerTicket(ticket.id, guest: asGuest),
      );
    } on AppFailure catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        _joining = false;
        _fieldErrors = failure.fieldErrors;
        _error = failure.fieldErrors.isEmpty
            ? ticketFailureMessage(l10n, failure)
            : null;
      });
      // The queue may have changed: show what the server says now.
      ref.invalidate(joinableQueueProvider(widget.queueId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<JoinableQueue> queue = ref.watch(
      joinableQueueProvider(widget.queueId),
    );

    return DoryPageScaffold(
      title: l10n.joinTitle,
      body: AsyncValueView<JoinableQueue>(
        value: queue,
        onRetry: () => ref.invalidate(joinableQueueProvider(widget.queueId)),
        data: _content,
      ),
    );
  }

  Widget _content(JoinableQueue target) {
    final AppLocalizations l10n = context.l10n;
    final bool signedIn = ref.watch(currentUserIdProvider) != null;
    final QueueAvailability queue = target.queue;
    final String? blocked = joinBlockedMessage(l10n, queue);
    final String? error = _error;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        _Summary(target: target),
        const SizedBox(height: AppSpacing.md),
        if (error != null) ...<Widget>[
          DoryNotice(message: error, tone: DoryNoticeTone.error),
          const SizedBox(height: AppSpacing.md),
        ],
        if (blocked != null)
          DoryNotice(message: blocked, tone: DoryNoticeTone.warning)
        else if (signedIn)
          DoryButton(
            label: l10n.joinAction,
            icon: Icons.confirmation_number_outlined,
            isLoading: _joining,
            onPressed: () => _join(target, asGuest: false),
          )
        else if (queue.allowGuestJoin)
          _guestForm(target)
        else
          _SignInPrompt(
            message: l10n.joinGuestsNotAllowed,
            from: AppRoutes.customerQueue(widget.queueId),
          ),
      ],
    );
  }

  /// Asks only for what this queue requires.
  Widget _guestForm(JoinableQueue target) {
    final AppLocalizations l10n = context.l10n;
    final QueueAvailability queue = target.queue;

    return Form(
      key: _form,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          DorySectionHeader(title: l10n.joinAsGuestTitle),
          if (queue.requireGuestName) ...<Widget>[
            DoryTextField(
              controller: _name,
              label: l10n.joinGuestName,
              prefixIcon: Icons.person_outline_rounded,
              textCapitalization: TextCapitalization.words,
              errorText: _fieldErrors['name'],
              validator: (value) => (value?.trim().length ?? 0) >= 2
                  ? null
                  : l10n.joinGuestNameRequired,
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          if (queue.requireGuestPhone) ...<Widget>[
            DoryTextField(
              controller: _phone,
              label: l10n.joinGuestPhone,
              prefixIcon: Icons.phone_outlined,
              keyboardType: TextInputType.phone,
              textDirection: TextDirection.ltr,
              errorText: _fieldErrors['phone'],
              validator: (value) => (value?.trim().isEmpty ?? true)
                  ? l10n.joinGuestPhoneRequired
                  : null,
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _consent,
              onChanged: (value) => setState(() => _consent = value ?? false),
              title: Text(l10n.joinGuestConsent),
              subtitle: _fieldErrors['consent'] == null
                  ? null
                  : Text(
                      _fieldErrors['consent']!,
                      style: const TextStyle(color: AppColors.error),
                    ),
            ),
            const SizedBox(height: AppSpacing.sm),
          ],
          DoryButton(
            label: l10n.joinAsGuestAction,
            icon: Icons.confirmation_number_outlined,
            isLoading: _joining,
            onPressed: queue.requireGuestPhone && !_consent
                ? null
                : () => _join(target, asGuest: true),
          ),
          const SizedBox(height: AppSpacing.md),
          _SignInPrompt(
            message: l10n.joinAccountBenefit,
            from: AppRoutes.customerQueue(widget.queueId),
          ),
        ],
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.target});

  final JoinableQueue target;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final QueueAvailability queue = target.queue;
    final int? remaining = queue.capacityRemaining;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(target.serviceName ?? queue.name, style: text.titleLarge),
          Text(target.businessName, style: text.bodyMedium),
          Text(
            <String>[target.branchName, ?target.city].join(' · '),
            style: text.bodySmall,
          ),
          const SizedBox(height: AppSpacing.md),
          queueStateChip(l10n, queue),
          const SizedBox(height: AppSpacing.md),
          _Fact(
            icon: Icons.groups_outlined,
            label: l10n.joinPeopleWaiting(queue.waitingCount),
          ),
          if (queue.state == QueueState.open)
            _Fact(
              icon: Icons.schedule_rounded,
              label: waitLabel(l10n, queue.estimatedMinutes),
            ),
          if (remaining != null)
            _Fact(
              icon: Icons.event_seat_outlined,
              label: l10n.joinPlacesLeft(remaining),
            ),
          _Fact(
            icon: queue.allowGuestJoin
                ? Icons.person_outline_rounded
                : Icons.lock_outline_rounded,
            label: queue.allowGuestJoin
                ? l10n.joinGuestsAllowed
                : l10n.joinAccountRequired,
          ),
          if (target.nowServing.isNotEmpty)
            _Fact(
              icon: Icons.campaign_outlined,
              label: l10n.ticketNowServing(target.nowServing.join(', ')),
            ),
        ],
      ),
    );
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.xs),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 18, color: AppColors.textMuted),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}

/// Encourages an account without ever requiring one.
class _SignInPrompt extends StatelessWidget {
  const _SignInPrompt({required this.message, required this.from});

  final String message;
  final String from;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryNotice(message: message),
        DoryButton(
          label: l10n.welcomeLogin,
          variant: DoryButtonVariant.tertiary,
          // go, not push: the login screen leaves through the router's
          // redirect, which a pushed screen never gets.
          onPressed: () =>
              context.go(AppRoutes.withFrom(AppRoutes.login, Uri.parse(from))),
        ),
      ],
    );
  }
}
