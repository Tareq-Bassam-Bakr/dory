import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:dory_app/shared/components/status_chip.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:material_ui/material_ui.dart';

String ticketStatusLabel(AppLocalizations l10n, TicketStatus status) =>
    switch (status) {
      TicketStatus.waiting => l10n.queueTicketStatusWaiting,
      TicketStatus.called => l10n.queueTicketStatusCalled,
      TicketStatus.serving => l10n.queueTicketStatusServing,
      TicketStatus.completed => l10n.queueTicketStatusCompleted,
      TicketStatus.cancelled => l10n.queueTicketStatusCancelled,
      TicketStatus.skipped => l10n.queueTicketStatusSkipped,
      TicketStatus.noShow => l10n.queueTicketStatusNoShow,
      TicketStatus.expired => l10n.queueTicketStatusExpired,
    };

Widget ticketStatusChip(AppLocalizations l10n, TicketStatus status) =>
    StatusChip(
      label: ticketStatusLabel(l10n, status),
      tone: switch (status) {
        TicketStatus.waiting => StatusTone.neutral,
        TicketStatus.called || TicketStatus.serving => StatusTone.warning,
        TicketStatus.completed => StatusTone.positive,
        _ => StatusTone.negative,
      },
    );

/// [status] null means the queue has no open or paused session.
Widget sessionStatusChip(AppLocalizations l10n, QueueSessionStatus? status) =>
    switch (status) {
      QueueSessionStatus.open => StatusChip(
        label: l10n.queueSessionStatusOpen,
        tone: StatusTone.positive,
      ),
      QueueSessionStatus.paused => StatusChip(
        label: l10n.queueSessionStatusPaused,
        tone: StatusTone.warning,
      ),
      _ => StatusChip(
        label: l10n.queueSessionStatusClosed,
        tone: StatusTone.neutral,
      ),
    };

String ticketEventLabel(AppLocalizations l10n, String type) => switch (type) {
  'joined' => l10n.queueEventJoined,
  'called' => l10n.queueEventCalled,
  'service_started' => l10n.queueEventServiceStarted,
  'service_completed' => l10n.queueEventServiceCompleted,
  'cancelled' => l10n.queueEventCancelled,
  'skipped' => l10n.queueEventSkipped,
  'no_show' => l10n.queueEventNoShow,
  'expired' => l10n.queueEventExpired,
  'recalled' => l10n.queueEventRecalled,
  _ => type,
};

String? closeReasonLabel(AppLocalizations l10n, String? reason) =>
    switch (reason) {
      'closed_by_staff' => l10n.queueCloseReasonStaff,
      'day_ended' => l10n.queueCloseReasonDayEnded,
      _ => null,
    };

/// The customer's name, or "Guest" / "Customer".
String customerLabel(AppLocalizations l10n, StaffTicket ticket) {
  final String? name = ticket.customerName;
  if (name != null && name.trim().isNotEmpty) {
    return name;
  }
  return ticket.isGuest ? l10n.queueGuest : l10n.queueCustomer;
}

/// Explains a failed queue action, including the queue engine's own codes.
String queueFailureMessage(AppLocalizations l10n, Object error) {
  if (error is AppFailure) {
    final String? message = switch (error.code) {
      'session_already_active' => l10n.queueErrorSessionAlreadyActive,
      'queue_has_active_session' => l10n.queueErrorHasActiveSession,
      'no_waiting_tickets' => l10n.queueErrorNoWaiting,
      'staff_busy' => l10n.queueErrorStaffBusy,
      'ticket_handled_by_other_staff' => l10n.queueErrorOtherStaff,
      'grace_period_active' => l10n.queueErrorGracePeriod,
      'ticket_state_changed' => l10n.queueErrorTicketChanged,
      'invalid_ticket_transition' => l10n.queueErrorInvalidTransition,
      'queue_closed' => l10n.queueErrorQueueClosed,
      'queue_inactive' => l10n.queueErrorQueueInactive,
      'no_active_session' ||
      'session_not_open' => l10n.queueErrorNoActiveSession,
      'session_not_paused' => l10n.queueErrorSessionNotPaused,
      _ => null,
    };
    if (message != null) {
      return message;
    }
  }
  return failureMessage(l10n, error);
}

/// "14:05" in the user's language.
String formatClock(BuildContext context, DateTime time) => DateFormat.Hm(
  Localizations.localeOf(context).toLanguageTag(),
).format(time.toLocal());

/// "6 Nov 2026" from a "YYYY-MM-DD" business date.
String formatBusinessDate(BuildContext context, String date) {
  final DateTime? parsed = DateTime.tryParse(date);
  return parsed == null
      ? date
      : DateFormat.yMMMd(
          Localizations.localeOf(context).toLanguageTag(),
        ).format(parsed);
}

/// "BAR-001", the first ticket number of a day.
String firstTicketExample(String prefix) =>
    '${prefix.isEmpty ? 'A' : prefix.toUpperCase()}-001';
