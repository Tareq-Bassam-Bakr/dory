import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_coordinator.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The bell: opens the notification center, with the unread count.
class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final int unread = ref.watch(unreadNotificationsProvider);
    return IconButton(
      tooltip: unread > 0
          ? l10n.notificationsBellUnread(unread)
          : l10n.homeNotifications,
      icon: Badge(
        isLabelVisible: unread > 0,
        label: Text(unread > 99 ? '99+' : '$unread'),
        child: const Icon(Icons.notifications_none_rounded),
      ),
      onPressed: () => context.push(AppRoutes.customerNotifications),
    );
  }
}

/// Offered once on a waiting ticket: allow notifications, with the reason.
/// Never shown again after "Not now", nor once allowed.
class NotificationOffer extends ConsumerStatefulWidget {
  const NotificationOffer({super.key, this.guestTicketId});

  /// Set for a guest ticket, so its device registration follows.
  final String? guestTicketId;

  static const String dismissedKey = 'dory.notification_offer_dismissed';

  @override
  ConsumerState<NotificationOffer> createState() => _NotificationOfferState();
}

class _NotificationOfferState extends ConsumerState<NotificationOffer> {
  bool _hidden = false;

  Future<void> _allow() async {
    final NotificationPermission result = await ref
        .read(notificationPermissionProvider.notifier)
        .request();
    if (result == NotificationPermission.granted) {
      unawaited(ref.read(notificationCoordinatorProvider).syncDevice());
      final String? ticketId = widget.guestTicketId;
      if (ticketId != null) {
        unawaited(
          ref.read(deviceRegistrationProvider).syncGuestTicket(ticketId),
        );
      }
    }
    if (mounted) {
      setState(() => _hidden = true);
    }
  }

  Future<void> _later() async {
    await ref
        .read(keyValueStorageProvider)
        .writeString(NotificationOffer.dismissedKey, '1');
    if (mounted) {
      setState(() => _hidden = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<NotificationPermission> permission = ref.watch(
      notificationPermissionProvider,
    );
    final bool dismissed =
        ref
            .watch(keyValueStorageProvider)
            .readString(NotificationOffer.dismissedKey) ==
        '1';
    if (_hidden ||
        dismissed ||
        !permission.hasValue ||
        permission.value == NotificationPermission.granted) {
      return const SizedBox.shrink();
    }
    final bool pushAvailable = ref.watch(pushMessagingProvider).available;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            l10n.notificationOfferTitle,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            pushAvailable
                ? l10n.notificationOfferMessagePush
                : l10n.notificationOfferMessageInApp,
          ),
          const SizedBox(height: AppSpacing.sm),
          DoryButton(label: l10n.notificationOfferAllow, onPressed: _allow),
          DoryButton(
            label: l10n.notificationOfferLater,
            variant: DoryButtonVariant.tertiary,
            onPressed: _later,
          ),
        ],
      ),
    );
  }
}

/// Starts notifications with the app: taps, the user's channel, push.
class NotificationsHost extends ConsumerStatefulWidget {
  const NotificationsHost({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<NotificationsHost> createState() => _NotificationsHostState();
}

class _NotificationsHostState extends ConsumerState<NotificationsHost> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        unawaited(ref.read(notificationCoordinatorProvider).start());
      }
    });
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
