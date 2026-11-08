import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/notifications/notification_target.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/notifications/domain/notification_entities.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_providers.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:material_ui/material_ui.dart';

/// The notification center (docs/NOTIFICATION_CENTER.md): the signed-in
/// user's notifications from the server, all, unread or read.
class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  NotificationFilter _filter = NotificationFilter.all;

  Future<void> _open(AppNotification notification) async {
    final NotificationList list = ref.read(
      notificationListProvider(_filter).notifier,
    );
    unawaited(list.markRead(notification).catchError((Object _) {}));
    final NotificationTarget target =
        NotificationTarget.fromData(<String, Object?>{
          'kind': notification.kind,
          'ticket': notification.ticketId,
          'queue': notification.queueId,
        });
    if (target.location != AppRoutes.customerNotifications) {
      await context.push(target.location);
    }
  }

  Future<void> _markAllRead() async {
    final AppLocalizations l10n = context.l10n;
    try {
      await ref.read(notificationListProvider(_filter).notifier).markAllRead();
      for (final NotificationFilter filter in NotificationFilter.values) {
        if (filter != _filter) {
          ref.invalidate(notificationListProvider(filter));
        }
      }
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
    final int unread = ref.watch(unreadNotificationsProvider);

    return DoryPageScaffold(
      title: l10n.notificationsTitle,
      scrollable: false,
      padding: EdgeInsets.zero,
      actions: <Widget>[
        if (signedIn && unread > 0)
          IconButton(
            tooltip: l10n.notificationsMarkAllRead,
            icon: const Icon(Icons.done_all_rounded),
            onPressed: _markAllRead,
          ),
        IconButton(
          tooltip: l10n.notificationSettingsTitle,
          icon: const Icon(Icons.tune_rounded),
          onPressed: () => context.push(AppRoutes.customerNotificationSettings),
        ),
      ],
      body: signedIn
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                SizedBox(
                  height: 48,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.pageGutter,
                    ),
                    children: <Widget>[
                      for (final NotificationFilter filter
                          in NotificationFilter.values)
                        Padding(
                          padding: const EdgeInsetsDirectional.only(
                            end: AppSpacing.sm,
                          ),
                          child: ChoiceChip(
                            label: Text(_filterLabel(l10n, filter)),
                            selected: filter == _filter,
                            onSelected: (_) => setState(() => _filter = filter),
                          ),
                        ),
                    ],
                  ),
                ),
                Expanded(
                  child: _NotificationList(filter: _filter, onOpen: _open),
                ),
              ],
            )
          : Padding(
              padding: const EdgeInsets.all(AppSpacing.pageGutter),
              child: DoryEmptyState(
                icon: Icons.notifications_none_rounded,
                title: l10n.notificationsSignInTitle,
                message: l10n.notificationsSignInMessage,
                action: DoryStatusAction(
                  label: l10n.welcomeLogin,
                  onPressed: () => context.push(
                    AppRoutes.withFrom(
                      AppRoutes.login,
                      Uri.parse(AppRoutes.customerNotifications),
                    ),
                  ),
                ),
              ),
            ),
    );
  }

  static String _filterLabel(
    AppLocalizations l10n,
    NotificationFilter filter,
  ) => switch (filter) {
    NotificationFilter.all => l10n.notificationsFilterAll,
    NotificationFilter.unread => l10n.notificationsFilterUnread,
    NotificationFilter.read => l10n.notificationsFilterRead,
  };
}

class _NotificationList extends ConsumerWidget {
  const _NotificationList({required this.filter, required this.onOpen});

  final NotificationFilter filter;
  final Future<void> Function(AppNotification) onOpen;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final NotificationListState state = ref.watch(
      notificationListProvider(filter),
    );
    final NotificationList list = ref.read(
      notificationListProvider(filter).notifier,
    );
    final Object? error = state.error;

    if (state.items.isEmpty) {
      if (state.isLoading) {
        return const DoryLoadingIndicator();
      }
      if (error != null) {
        return DoryErrorState(
          title: l10n.commonGenericErrorTitle,
          message: failureMessage(l10n, error),
          retry: DoryStatusAction(
            label: l10n.commonRetry,
            onPressed: list.reload,
          ),
        );
      }
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.pageGutter),
        child: DoryEmptyState(
          icon: Icons.notifications_none_rounded,
          title: filter == NotificationFilter.unread
              ? l10n.notificationsNoUnreadTitle
              : l10n.notificationsEmptyTitle,
          message: l10n.notificationsEmptyMessage,
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: list.reload,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.extentAfter < 300) {
            unawaited(list.loadMore());
          }
          return false;
        },
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.pageGutter,
            AppSpacing.sm,
            AppSpacing.pageGutter,
            AppSpacing.xl,
          ),
          itemCount: state.items.length + (state.isLoading ? 1 : 0),
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, index) {
            if (index >= state.items.length) {
              return const SizedBox(height: 64, child: DoryLoadingIndicator());
            }
            final AppNotification item = state.items[index];
            return _NotificationTile(notification: item, onOpen: onOpen);
          },
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, required this.onOpen});

  final AppNotification notification;
  final Future<void> Function(AppNotification) onOpen;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final ColorScheme colors = Theme.of(context).colorScheme;

    return Semantics(
      container: true,
      label: notification.read ? null : l10n.notificationsUnreadLabel,
      child: DoryCard(
        padding: const EdgeInsets.all(AppSpacing.md),
        onTap: () => onOpen(notification),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(
              notification.forStaff
                  ? Icons.storefront_outlined
                  : Icons.confirmation_number_outlined,
              color: colors.primary,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    notification.title,
                    style: notification.read
                        ? text.titleSmall
                        : text.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(notification.body, style: text.bodyMedium),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    DateFormat.MMMd(
                      Localizations.localeOf(context).toLanguageTag(),
                    ).add_Hm().format(notification.createdAt.toLocal()),
                    style: text.labelSmall,
                  ),
                ],
              ),
            ),
            if (!notification.read)
              Padding(
                padding: const EdgeInsetsDirectional.only(start: AppSpacing.sm),
                child: Icon(Icons.circle, size: 10, color: colors.primary),
              ),
          ],
        ),
      ),
    );
  }
}
