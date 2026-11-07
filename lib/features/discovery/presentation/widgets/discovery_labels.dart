import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/shared/components/status_chip.dart';
import 'package:material_ui/material_ui.dart';

/// "About 15 min", or "No wait right now". Estimates are always approximate.
String waitLabel(AppLocalizations l10n, int minutes) =>
    minutes <= 0 ? l10n.discoveryNoWait : l10n.discoveryWaitMinutes(minutes);

/// "350 m" or "2.4 km".
String distanceLabel(AppLocalizations l10n, double kilometres) => kilometres < 1
    ? l10n.discoveryDistanceMeters((kilometres * 1000).round())
    : l10n.discoveryDistanceKm(kilometres.toStringAsFixed(1));

/// "Damascus · 2.4 km" from whichever parts are known.
///
/// Each part is isolated for the text direction: a city in another script
/// would otherwise separate the number from its unit.
String placeLine(AppLocalizations l10n, {String? city, double? distanceKm}) {
  final List<String> parts = <String>[
    if (city != null && city.isNotEmpty) city,
    if (distanceKm != null) distanceLabel(l10n, distanceKm),
  ];
  return parts.length < 2
      ? parts.join()
      : parts.map((part) => '\u2068$part\u2069').join(' · ');
}

/// The state of a queue as reported by the server.
Widget queueStateChip(AppLocalizations l10n, QueueAvailability queue) {
  if (queue.state == QueueState.open && queue.isFull) {
    return StatusChip(label: l10n.queueStateFull, tone: StatusTone.warning);
  }
  return switch (queue.state) {
    QueueState.open => StatusChip(
      label: l10n.queueStateOpen,
      tone: StatusTone.positive,
    ),
    QueueState.paused => StatusChip(
      label: l10n.queueStatePaused,
      tone: StatusTone.warning,
    ),
    QueueState.closed => StatusChip(
      label: l10n.queueStateClosed,
      tone: StatusTone.neutral,
    ),
    QueueState.inactive => StatusChip(
      label: l10n.queueStateUnavailable,
      tone: StatusTone.neutral,
    ),
  };
}

/// Why a customer cannot join right now, or null when they can.
String? joinBlockedMessage(AppLocalizations l10n, QueueAvailability queue) {
  if (queue.acceptingJoins) {
    return null;
  }
  if (queue.state == QueueState.open && queue.isFull) {
    return l10n.joinBlockedFull;
  }
  return switch (queue.state) {
    QueueState.open => null,
    QueueState.paused => l10n.joinBlockedPaused,
    QueueState.closed => l10n.joinBlockedClosed,
    QueueState.inactive => switch (queue.unavailableReason) {
      'branch_inactive' => l10n.joinBlockedBranchInactive,
      'business_unavailable' => l10n.joinBlockedBusinessUnavailable,
      _ => l10n.joinBlockedServiceUnavailable,
    },
  };
}
