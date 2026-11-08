import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/notifications/domain/notification_entities.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_coordinator.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_providers.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Notification preferences (on the server) and this phone's permission
/// (docs/NOTIFICATION_CENTER.md). The permission is asked only when the
/// user taps the button; refusing it changes nothing else in the app.
class NotificationSettingsPage extends ConsumerStatefulWidget {
  const NotificationSettingsPage({super.key});

  @override
  ConsumerState<NotificationSettingsPage> createState() =>
      _NotificationSettingsPageState();
}

class _NotificationSettingsPageState
    extends ConsumerState<NotificationSettingsPage>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Back from the phone settings: read the permission again.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(ref.read(notificationPermissionProvider.notifier).recheck());
    }
  }

  Future<void> _allow() async {
    final NotificationPermission result = await ref
        .read(notificationPermissionProvider.notifier)
        .request();
    if (result == NotificationPermission.granted) {
      unawaited(ref.read(notificationCoordinatorProvider).syncDevice());
    }
  }

  Future<void> _toggle(String flag, bool value) async {
    final AppLocalizations l10n = context.l10n;
    try {
      await ref
          .read(notificationSettingsProvider.notifier)
          .setFlag(flag, value);
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, failureMessage(l10n, failure));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final bool signedIn = ref.watch(currentUserIdProvider) != null;
    final bool pushAvailable = ref.watch(pushMessagingProvider).available;

    return DoryPageScaffold(
      title: l10n.notificationSettingsTitle,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          DorySectionHeader(title: l10n.notificationSettingsPhone),
          _PermissionCard(onAllow: _allow),
          const SizedBox(height: AppSpacing.sm),
          DoryNotice(
            message: pushAvailable
                ? l10n.notificationSettingsPushReady
                : l10n.notificationSettingsPushUnavailable,
          ),
          if (signedIn) ...<Widget>[
            const SizedBox(height: AppSpacing.lg),
            DorySectionHeader(title: l10n.notificationSettingsWhat),
            _Preferences(onToggle: _toggle),
          ],
        ],
      ),
    );
  }
}

class _PermissionCard extends ConsumerWidget {
  const _PermissionCard({required this.onAllow});

  final Future<void> Function() onAllow;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<NotificationPermission> permission = ref.watch(
      notificationPermissionProvider,
    );
    final bool granted = permission.value == NotificationPermission.granted;

    return DoryCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(
                granted
                    ? Icons.notifications_active_outlined
                    : Icons.notifications_off_outlined,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Text(
                  granted
                      ? l10n.notificationPermissionGranted
                      : l10n.notificationPermissionDenied,
                ),
              ),
            ],
          ),
          if (!granted && permission.hasValue) ...<Widget>[
            const SizedBox(height: AppSpacing.sm),
            DoryButton(
              label: l10n.notificationPermissionAllow,
              onPressed: onAllow,
            ),
            DoryButton(
              label: l10n.notificationPermissionOpenSettings,
              variant: DoryButtonVariant.tertiary,
              onPressed: () => ref
                  .read(notificationPermissionProvider.notifier)
                  .openSettings(),
            ),
          ],
        ],
      ),
    );
  }
}

class _Preferences extends ConsumerWidget {
  const _Preferences({required this.onToggle});

  final Future<void> Function(String flag, bool value) onToggle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<NotificationSettings> settings = ref.watch(
      notificationSettingsProvider,
    );

    return switch (settings) {
      AsyncData(:final NotificationSettings value) => DoryCard(
        padding: EdgeInsets.zero,
        child: Column(
          children: <Widget>[
            for (final String flag in NotificationSettings.flags)
              SwitchListTile(
                title: Text(_title(l10n, flag)),
                subtitle: Text(_subtitle(l10n, flag)),
                value: value.flag(flag),
                onChanged: (next) => onToggle(flag, next),
              ),
          ],
        ),
      ),
      AsyncError(:final Object error) => DoryErrorState(
        title: l10n.commonGenericErrorTitle,
        message: failureMessage(l10n, error),
        retry: DoryStatusAction(
          label: l10n.commonRetry,
          onPressed: () => ref.invalidate(notificationSettingsProvider),
        ),
      ),
      _ => const Padding(
        padding: EdgeInsets.all(AppSpacing.lg),
        child: DoryLoadingIndicator(),
      ),
    };
  }

  static String _title(AppLocalizations l10n, String flag) => switch (flag) {
    'ticket_updates' => l10n.notificationPrefTickets,
    'almost_turn' => l10n.notificationPrefAlmostTurn,
    'queue_updates' => l10n.notificationPrefQueue,
    'staff_new_tickets' => l10n.notificationPrefStaffNew,
    'staff_cancellations' => l10n.notificationPrefStaffCancelled,
    _ => l10n.notificationPrefPush,
  };

  static String _subtitle(AppLocalizations l10n, String flag) => switch (flag) {
    'ticket_updates' => l10n.notificationPrefTicketsHint,
    'almost_turn' => l10n.notificationPrefAlmostTurnHint,
    'queue_updates' => l10n.notificationPrefQueueHint,
    'staff_new_tickets' => l10n.notificationPrefStaffNewHint,
    'staff_cancellations' => l10n.notificationPrefStaffCancelledHint,
    _ => l10n.notificationPrefPushHint,
  };
}
