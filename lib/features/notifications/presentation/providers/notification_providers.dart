import 'dart:async';

import 'package:dory_app/core/config/push_config.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/notifications/push_messaging.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/notifications/data/api_notification_repository.dart';
import 'package:dory_app/features/notifications/domain/notification_entities.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<NotificationRepository> notificationRepositoryProvider =
    Provider<NotificationRepository>(
      (ref) => ApiNotificationRepository(ref.watch(apiClientProvider)),
      name: 'notificationRepositoryProvider',
    );

final Provider<DeviceRepository> deviceRepositoryProvider =
    Provider<DeviceRepository>(
      (ref) => ApiDeviceRepository(ref.watch(apiClientProvider)),
      name: 'deviceRepositoryProvider',
    );

/// Shows notifications on this device; tests override it.
final Provider<LocalNotifier> localNotifierProvider = Provider<LocalNotifier>(
  (ref) => FlutterLocalNotifier(),
  name: 'localNotifierProvider',
);

/// Firebase Cloud Messaging when the build enables it, nothing otherwise.
final Provider<PushMessaging> pushMessagingProvider = Provider<PushMessaging>(
  (ref) => PushConfig.enabled
      ? FirebasePushMessaging()
      : const DisabledPushMessaging(),
  name: 'pushMessagingProvider',
);

// ----- Unread count ---------------------------------------------------------

/// The signed-in user's unread notifications, for the bell's badge.
class UnreadNotifications extends Notifier<int> {
  int _generation = 0;

  @override
  int build() {
    _generation++;
    if (ref.watch(currentUserIdProvider) != null) {
      unawaited(Future<void>.microtask(refresh));
    }
    return 0;
  }

  Future<void> refresh() async {
    if (ref.read(currentUserIdProvider) == null) {
      return;
    }
    final int generation = _generation;
    try {
      final NotificationPage page = await ref
          .read(notificationRepositoryProvider)
          .list(NotificationFilter.unread);
      if (ref.mounted && generation == _generation) {
        state = page.unreadCount;
      }
    } on AppFailure {
      // The badge keeps its last value; the center shows errors itself.
    }
  }

  void set(int count) => state = count < 0 ? 0 : count;
}

final NotifierProvider<UnreadNotifications, int> unreadNotificationsProvider =
    NotifierProvider<UnreadNotifications, int>(
      UnreadNotifications.new,
      name: 'unreadNotificationsProvider',
    );

// ----- The list ---------------------------------------------------------------

@immutable
class NotificationListState {
  const NotificationListState({
    this.items = const <AppNotification>[],
    this.page = 0,
    this.hasMore = true,
    this.isLoading = false,
    this.error,
  });

  final List<AppNotification> items;
  final int page;
  final bool hasMore;
  final bool isLoading;
  final Object? error;

  NotificationListState copyWith({
    List<AppNotification>? items,
    bool? isLoading,
  }) => NotificationListState(
    items: items ?? this.items,
    page: page,
    hasMore: hasMore,
    isLoading: isLoading ?? this.isLoading,
    error: error,
  );
}

/// One filter of the notification center, page by page. Only the latest
/// load may fill the list (a lesson from Phase 05's duplicate favorites).
class NotificationList extends Notifier<NotificationListState> {
  NotificationList(this.filter);

  final NotificationFilter filter;

  int _generation = 0;
  bool _loading = false;

  NotificationRepository get _repository =>
      ref.read(notificationRepositoryProvider);

  @override
  NotificationListState build() {
    _generation++;
    _loading = false;
    if (ref.watch(currentUserIdProvider) == null) {
      return const NotificationListState(hasMore: false);
    }
    unawaited(Future<void>.microtask(loadMore));
    return const NotificationListState(isLoading: true);
  }

  Future<void> reload() async {
    _generation++;
    _loading = false;
    state = const NotificationListState();
    await loadMore();
  }

  Future<void> loadMore() async {
    if (_loading || !state.hasMore) {
      return;
    }
    final int generation = _generation;
    _loading = true;
    state = state.copyWith(isLoading: true);
    try {
      final NotificationPage page = await _repository.list(
        filter,
        page: state.page + 1,
      );
      if (!ref.mounted || generation != _generation) {
        return;
      }
      _loading = false;
      state = NotificationListState(
        items: <AppNotification>[...state.items, ...page.result.items],
        page: page.result.pagination.currentPage,
        hasMore: page.result.pagination.hasMore,
      );
      ref.read(unreadNotificationsProvider.notifier).set(page.unreadCount);
    } on AppFailure catch (failure) {
      if (ref.mounted && generation == _generation) {
        _loading = false;
        state = NotificationListState(
          items: state.items,
          page: state.page,
          error: failure,
        );
      }
    }
  }

  /// Marks one as read; the change shows at once and is undone if refused.
  Future<void> markRead(AppNotification notification) async {
    if (notification.read) {
      return;
    }
    final List<AppNotification> before = state.items;
    state = state.copyWith(
      items: <AppNotification>[
        for (final AppNotification item in before)
          if (item.id != notification.id)
            item
          else if (filter != NotificationFilter.unread)
            item.markedRead(),
      ],
    );
    try {
      final int unread = await _repository.markRead(notification.id);
      ref.read(unreadNotificationsProvider.notifier).set(unread);
    } on AppFailure {
      if (ref.mounted) {
        state = state.copyWith(items: before);
      }
      rethrow;
    }
  }

  Future<void> markAllRead() async {
    await _repository.markAllRead();
    ref.read(unreadNotificationsProvider.notifier).set(0);
    if (ref.mounted) {
      await reload();
    }
  }
}

final notificationListProvider = NotifierProvider.autoDispose
    .family<NotificationList, NotificationListState, NotificationFilter>(
      NotificationList.new,
      name: 'notificationListProvider',
    );

// ----- Settings -----------------------------------------------------------------

class NotificationSettingsController
    extends AsyncNotifier<NotificationSettings> {
  @override
  Future<NotificationSettings> build() {
    ref.watch(currentUserIdProvider);
    return ref.read(notificationRepositoryProvider).settings();
  }

  /// Turns one flag on or off: shown at once, saved, undone if refused.
  Future<void> setFlag(String flag, bool value) async {
    final NotificationSettings? current = state.value;
    if (current == null) {
      return;
    }
    state = AsyncData<NotificationSettings>(current.withFlag(flag, value));
    try {
      final NotificationSettings saved = await ref
          .read(notificationRepositoryProvider)
          .updateSettings(<String, bool>{flag: value});
      if (ref.mounted) {
        state = AsyncData<NotificationSettings>(saved);
      }
    } on AppFailure {
      if (ref.mounted) {
        state = AsyncData<NotificationSettings>(current);
      }
      rethrow;
    }
  }
}

final notificationSettingsProvider =
    AsyncNotifierProvider.autoDispose<
      NotificationSettingsController,
      NotificationSettings
    >(NotificationSettingsController.new, name: 'notificationSettingsProvider');

// ----- Permission ---------------------------------------------------------------

/// Whether Android lets DORY show notifications. Asked only when the user
/// chooses to (settings, or the offer after joining a queue).
class NotificationPermissionController
    extends AsyncNotifier<NotificationPermission> {
  @override
  Future<NotificationPermission> build() =>
      ref.read(localNotifierProvider).permission();

  Future<NotificationPermission> request() async {
    final NotificationPermission result = await ref
        .read(localNotifierProvider)
        .requestPermission();
    state = AsyncData<NotificationPermission>(result);
    return result;
  }

  Future<void> openSettings() => ref.read(localNotifierProvider).openSettings();

  /// After returning from the phone settings.
  Future<void> recheck() async {
    state = AsyncData<NotificationPermission>(
      await ref.read(localNotifierProvider).permission(),
    );
  }
}

final notificationPermissionProvider =
    AsyncNotifierProvider<
      NotificationPermissionController,
      NotificationPermission
    >(
      NotificationPermissionController.new,
      name: 'notificationPermissionProvider',
    );

// ----- What is on screen ----------------------------------------------------------

/// The ticket whose screen the user is looking at: alerts about it are not
/// shown again on top of it. A plain notifier, not provider state: screens
/// set it while laying out, and nothing rebuilds from it.
class VisibleTicket {
  String? _ticketId;

  String? get ticketId => _ticketId;

  void show(String ticketId) => _ticketId = ticketId;

  void hide(String ticketId) {
    if (_ticketId == ticketId) {
      _ticketId = null;
    }
  }
}

final Provider<VisibleTicket> visibleTicketProvider = Provider<VisibleTicket>(
  (ref) => VisibleTicket(),
  name: 'visibleTicketProvider',
);
