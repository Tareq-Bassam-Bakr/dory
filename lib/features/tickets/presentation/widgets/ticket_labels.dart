import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/tickets/domain/entities/customer_ticket.dart';

/// What each ticket state means for the customer.
String ticketStatusHeadline(AppLocalizations l10n, TicketStatus status) =>
    switch (status) {
      TicketStatus.waiting => l10n.ticketHeadlineWaiting,
      TicketStatus.called => l10n.ticketHeadlineCalled,
      TicketStatus.serving => l10n.ticketHeadlineServing,
      TicketStatus.completed => l10n.ticketHeadlineCompleted,
      TicketStatus.cancelled => l10n.ticketHeadlineCancelled,
      TicketStatus.skipped => l10n.ticketHeadlineSkipped,
      TicketStatus.noShow => l10n.ticketHeadlineNoShow,
      TicketStatus.expired => l10n.ticketHeadlineExpired,
    };

String ticketFilterLabel(AppLocalizations l10n, TicketFilter filter) =>
    switch (filter) {
      TicketFilter.active => l10n.ticketFilterActive,
      TicketFilter.waiting => l10n.queueTicketStatusWaiting,
      TicketFilter.called => l10n.queueTicketStatusCalled,
      TicketFilter.serving => l10n.queueTicketStatusServing,
      TicketFilter.completed => l10n.queueTicketStatusCompleted,
      TicketFilter.cancelled => l10n.queueTicketStatusCancelled,
      TicketFilter.history => l10n.ticketFilterHistory,
    };

/// Explains a failed join, cancellation or rating.
String ticketFailureMessage(AppLocalizations l10n, Object error) {
  if (error is AppFailure) {
    final String? message = switch (error.code) {
      'queue_full' => l10n.joinBlockedFull,
      'queue_paused' => l10n.joinBlockedPaused,
      'queue_closed' => l10n.joinBlockedClosed,
      'branch_inactive' => l10n.joinBlockedBranchInactive,
      'business_unavailable' => l10n.joinBlockedBusinessUnavailable,
      'service_unavailable' ||
      'queue_inactive' => l10n.joinBlockedServiceUnavailable,
      'guest_join_disabled' => l10n.joinGuestsNotAllowed,
      'duplicate_active_ticket' => l10n.joinAlreadyInQueue,
      'cancellation_not_allowed' => l10n.ticketCancelNotAllowed,
      'already_reviewed' => l10n.ticketAlreadyRated,
      'ticket_not_reviewable' => l10n.ticketNotRateable,
      _ => null,
    };
    if (message != null) {
      return message;
    }
  }
  return queueFailureMessage(l10n, error);
}
