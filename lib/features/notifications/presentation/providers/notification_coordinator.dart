import 'dart:async';
import 'dart:collection';
import 'dart:convert';

import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/notifications/notification_target.dart';
import 'package:dory_app/core/notifications/push_messaging.dart';
import 'package:dory_app/core/realtime/pusher_client.dart';
import 'package:dory_app/core/realtime/realtime_providers.dart';
import 'package:dory_app/core/storage/key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/notifications/domain/notification_entities.dart';
import 'package:dory_app/features/notifications/presentation/providers/notification_providers.dart';
import 'package:dory_app/features/tickets/data/guest_ticket_vault.dart';
import 'package:dory_app/features/tickets/presentation/providers/ticket_providers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

/// Registers this device for push (docs/DEVICE_TOKEN_SECURITY.md).
///
/// - Only while push is available and the user allowed notifications.
/// - Signed-in users: one registration per account, removed before signing
///   out. Guests: one per ticket, proven by the ticket's own token.
/// - The FCM token is never stored by DORY; only the server's device ids
///   (not secret) are remembered, to replace or remove registrations.
class DeviceRegistration {
  DeviceRegistration(this._ref);

  final Ref _ref;

  /// Where the device id of an account or a guest ticket is kept, inside
  /// [StorageKeys.pushDevices].
  static String userOwner(String userId) => 'user:$userId';

  static String guestOwner(String ticketId) => 'ticket:$ticketId';

  /// The device id the server returned for [owner], if any.
  static String? storedDevice(KeyValueStorage storage, String owner) =>
      _stored(storage)[owner];

  static Map<String, String> _stored(KeyValueStorage storage) {
    final String? raw = storage.readString(StorageKeys.pushDevices);
    if (raw == null) {
      return <String, String>{};
    }
    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is Map) {
        return <String, String>{
          for (final MapEntry<Object?, Object?> entry in decoded.entries)
            if (entry.key is String && entry.value is String)
              entry.key! as String: entry.value! as String,
        };
      }
    } on FormatException {
      // Unreadable: start over; the server replaces registrations anyway.
    }
    return <String, String>{};
  }

  Future<void> _remember(String owner, String? deviceId) {
    final Map<String, String> devices = _stored(_storage);
    if (deviceId == null) {
      devices.remove(owner);
    } else {
      devices[owner] = deviceId;
    }
    return devices.isEmpty
        ? _storage.remove(StorageKeys.pushDevices)
        : _storage.writeString(StorageKeys.pushDevices, jsonEncode(devices));
  }

  PushMessaging get _push => _ref.read(pushMessagingProvider);

  DeviceRepository get _devices => _ref.read(deviceRepositoryProvider);

  KeyValueStorage get _storage => _ref.read(keyValueStorageProvider);

  Future<bool> _allowed() async {
    if (!_push.available) {
      return false;
    }
    final NotificationPermission permission = await _ref
        .read(localNotifierProvider)
        .permission();
    return permission == NotificationPermission.granted;
  }

  Future<PushDeviceInfo?> _device() async {
    final String? token = await _push.token();
    if (token == null || token.isEmpty) {
      return null;
    }
    final String language =
        (_ref.read(languageControllerProvider).locale ??
                PlatformDispatcher.instance.locale)
            .languageCode;
    return PushDeviceInfo(
      token: token,
      platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      locale: language == 'ar' ? 'ar' : 'en',
    );
  }

  /// The signed-in user's registration, created or refreshed.
  Future<void> syncUser({String? replaces}) async {
    final String? userId = _ref.read(currentUserIdProvider);
    if (userId == null || !await _allowed()) {
      return;
    }
    final PushDeviceInfo? device = await _device();
    if (device == null) {
      return;
    }
    try {
      final String id = await _devices.registerForUser(
        device,
        replaces: replaces,
      );
      await _remember(userOwner(userId), id);
    } on AppFailure catch (failure, stackTrace) {
      ErrorReporter.report(failure, stackTrace, context: 'Device registration');
    }
  }

  /// Before signing out: this phone stops receiving the account's alerts.
  Future<void> beforeSignOut() async {
    final String? userId = _ref.read(currentUserIdProvider);
    if (userId == null) {
      return;
    }
    final String? deviceId = storedDevice(_storage, userOwner(userId));
    if (deviceId == null) {
      return;
    }
    try {
      await _devices.unregisterForUser(deviceId);
    } on AppFailure catch (failure, stackTrace) {
      ErrorReporter.report(failure, stackTrace, context: 'Device removal');
    }
    await _remember(userOwner(userId), null);
  }

  /// A guest ticket on this device gets the device's alerts.
  Future<void> syncGuestTicket(String ticketId, {String? replaces}) async {
    if (!await _allowed()) {
      return;
    }
    final GuestTicketVault vault = _ref.read(guestTicketVaultProvider);
    final String? accessToken = await vault.tokenFor(ticketId);
    final PushDeviceInfo? device = await _device();
    if (accessToken == null || device == null) {
      return;
    }
    try {
      final String id = await _devices.registerForGuest(
        ticketId,
        accessToken,
        device,
        replaces: replaces,
      );
      await _remember(guestOwner(ticketId), id);
    } on AppFailure catch (failure, stackTrace) {
      ErrorReporter.report(failure, stackTrace, context: 'Guest device');
    }
  }

  /// Every active guest ticket kept on this device.
  Future<void> syncGuestTickets({bool replace = false}) async {
    for (final StoredGuestTicket ticket in _ref.read(guestTicketsProvider)) {
      if (ticket.status.isActive) {
        await syncGuestTicket(
          ticket.id,
          replaces: replace
              ? storedDevice(_storage, guestOwner(ticket.id))
              : null,
        );
      }
    }
  }

  /// FCM issued a new token: replace the old registrations.
  Future<void> onTokenRefresh() async {
    final String? userId = _ref.read(currentUserIdProvider);
    await syncUser(
      replaces: userId == null
          ? null
          : storedDevice(_storage, userOwner(userId)),
    );
    await syncGuestTickets(replace: true);
  }
}

final Provider<DeviceRegistration> deviceRegistrationProvider =
    Provider<DeviceRegistration>(
      DeviceRegistration.new,
      name: 'deviceRegistrationProvider',
    );

/// Everything notifications need while the app runs
/// (docs/PUSH_NOTIFICATIONS.md):
///
/// - opens the right screen for a tapped notification: local, FCM in the
///   background, or the one that started the app;
/// - while signed in and in front, listens on the user's private channel and
///   shows an alert for new notifications, unless their ticket is already on
///   screen;
/// - shows FCM messages that arrive while DORY is in front (Android does
///   not), once even if Reverb already announced them;
/// - keeps the device registration current.
class NotificationCoordinator {
  NotificationCoordinator(this._ref);

  final Ref _ref;
  final List<StreamSubscription<Object?>> _subscriptions =
      <StreamSubscription<Object?>>[];
  final LinkedHashSet<String> _shown = LinkedHashSet<String>();
  RealtimeSubscription? _userChannel;
  NotificationTarget? _pending;
  bool _started = false;
  int _nextId = 1;

  LocalNotifier get _local => _ref.read(localNotifierProvider);

  PushMessaging get _push => _ref.read(pushMessagingProvider);

  Future<void> start() async {
    if (_started) {
      return;
    }
    _started = true;
    try {
      await _local.init(
        onTap: (payload) => open(NotificationTarget.fromData(payload)),
      );
      final Map<String, String>? launched = await _local.launchPayload();
      if (launched != null) {
        open(NotificationTarget.fromData(launched));
      }
    } on Object catch (error, stackTrace) {
      ErrorReporter.report(error, stackTrace, context: 'Local notifications');
    }

    if (await _push.start()) {
      _subscriptions
        ..add(_push.foregroundMessages.listen(_onForegroundPush))
        ..add(
          _push.openedMessages.listen(
            (message) => open(NotificationTarget.fromData(message.data)),
          ),
        )
        ..add(
          _push.tokenRefreshes.listen(
            (_) => unawaited(
              _ref.read(deviceRegistrationProvider).onTokenRefresh(),
            ),
          ),
        );
      final IncomingPush? initial = await _push.initialMessage();
      if (initial != null) {
        open(NotificationTarget.fromData(initial.data));
      }
    }
    await syncDevice();
  }

  /// Registration after start, sign-in, a language change or a newly
  /// granted permission.
  Future<void> syncDevice() async {
    final DeviceRegistration registration = _ref.read(
      deviceRegistrationProvider,
    );
    await registration.syncUser();
    await registration.syncGuestTickets();
  }

  /// Opens the screen a notification points to, once the session is known
  /// and the splash screen has made its own navigation (otherwise it would
  /// replace the ticket with the home screen).
  void open(NotificationTarget target) {
    final AuthState auth = _ref.read(authControllerProvider);
    if (auth is AuthRestoring || _onSplash()) {
      _pending = target;
      return;
    }
    _pending = null;
    final bool signedIn = auth is Authenticated;
    final GoRouter router = _ref.read(appRouterProvider);
    if (!signedIn && !target.guest && target.ticketId != null) {
      // Sign in first; the login screen continues to the ticket.
      router.go(
        AppRoutes.withFrom(AppRoutes.login, Uri.parse(target.location)),
      );
    } else {
      final String top = topLocation(router);
      if (top == target.location) {
        // Already there.
      } else if (_area(top) == _area(target.location)) {
        // On top of the current screen, so Back returns to it (or to the
        // home screen the app opened on) instead of leaving DORY: found on a
        // phone.
        unawaited(router.push<void>(target.location));
      } else {
        // Customer and business areas have their own shells: switch.
        router.go(target.location);
      }
    }

    final String? notificationId = target.notificationId;
    if (signedIn && notificationId != null) {
      unawaited(_markRead(notificationId));
    }
  }

  /// The screen on top, with its query: pushed screens keep the router's
  /// own location at the screen below them.
  static String topLocation(GoRouter router) {
    final RouteMatchList current = router.routerDelegate.currentConfiguration;
    if (current.isEmpty) {
      return '';
    }
    final RouteMatchBase top = current.last;
    return top is ImperativeRouteMatch
        ? top.matches.uri.toString()
        : current.uri.toString();
  }

  /// "customer" or "business": the part of the app a location belongs to.
  static String? _area(String location) =>
      Uri.parse(location).pathSegments.firstOrNull;

  void onAuthChanged(AuthState state) => _openPending();

  /// The router moved: a waiting notification may open now.
  void onRouteChanged() => _openPending();

  void _openPending() {
    final NotificationTarget? pending = _pending;
    if (pending != null) {
      open(pending);
    }
  }

  bool _onSplash() {
    final String path = _ref
        .read(appRouterProvider)
        .routerDelegate
        .currentConfiguration
        .uri
        .path;
    return path.isEmpty ||
        path == AppRoutes.root ||
        path.startsWith(AppRoutes.splash);
  }

  /// A new connection (or none): listen on the user's own channel.
  void onRealtimeClient(PusherClient? client) {
    _userChannel?.cancel();
    _userChannel = null;
    final String? userId = _ref.read(currentUserIdProvider);
    if (client == null || userId == null) {
      return;
    }
    final RealtimeSubscription channel = client.subscribe(
      'private-users.$userId',
    );
    _userChannel = channel;
    _subscriptions.add(
      channel.events.listen((event) {
        switch (event.kind) {
          case RealtimeEventKind.subscribed:
            // Subscribed again after the background or a reconnection:
            // alerts sent meanwhile are not replayed, so read the badge.
            unawaited(
              _ref.read(unreadNotificationsProvider.notifier).refresh(),
            );
          case RealtimeEventKind.message
              when event.name == 'notification.created':
            unawaited(_onRealtimeNotification(event.data));
          case RealtimeEventKind.message || RealtimeEventKind.failed:
            break;
        }
      }),
    );
  }

  Future<void> _onRealtimeNotification(Map<String, Object?> data) async {
    await _ref.read(unreadNotificationsProvider.notifier).refresh();
    final NotificationTarget target = NotificationTarget.fromData(
      <String, Object?>{...data, 'notification': data['id']},
    );
    if (!_shouldShow(target)) {
      return;
    }
    // The texts come from the API, in the app's language.
    try {
      final NotificationPage page = await _ref
          .read(notificationRepositoryProvider)
          .list(NotificationFilter.unread);
      final AppNotification? notification = page.result.items
          .where((item) => item.id == target.notificationId)
          .firstOrNull;
      if (notification != null) {
        await _show(target, notification.title, notification.body);
      }
    } on AppFailure {
      // The badge is updated; the alert itself is skipped.
    }
  }

  Future<void> _onForegroundPush(IncomingPush message) async {
    final NotificationTarget target = NotificationTarget.fromData(message.data);
    final String? title = message.title;
    if (title == null || !_shouldShow(target)) {
      return;
    }
    if (_ref.read(currentUserIdProvider) != null) {
      unawaited(_ref.read(unreadNotificationsProvider.notifier).refresh());
    }
    await _show(target, title, message.body ?? '');
  }

  bool _shouldShow(NotificationTarget target) {
    if (_shown.contains(target.dedupeKey)) {
      return false;
    }
    final String? visible = _ref.read(visibleTicketProvider).ticketId;
    return visible == null || visible != target.ticketId;
  }

  Future<void> _show(
    NotificationTarget target,
    String title,
    String body,
  ) async {
    _shown.add(target.dedupeKey);
    if (_shown.length > 50) {
      _shown.remove(_shown.first);
    }
    if (await _local.permission() != NotificationPermission.granted) {
      return;
    }
    await _local.show(
      id: _nextId++,
      title: title,
      body: body,
      payload: target.toPayload(),
    );
  }

  Future<void> _markRead(String notificationId) async {
    try {
      final int unread = await _ref
          .read(notificationRepositoryProvider)
          .markRead(notificationId);
      _ref.read(unreadNotificationsProvider.notifier).set(unread);
    } on AppFailure {
      // Not found or offline: the center still lists it as unread.
    }
  }

  void dispose() {
    _userChannel?.cancel();
    for (final StreamSubscription<Object?> subscription in _subscriptions) {
      unawaited(subscription.cancel());
    }
    _subscriptions.clear();
  }
}

final Provider<NotificationCoordinator> notificationCoordinatorProvider =
    Provider<NotificationCoordinator>((ref) {
      final NotificationCoordinator coordinator = NotificationCoordinator(ref);
      final GoRouter router = ref.watch(appRouterProvider);
      router.routerDelegate.addListener(coordinator.onRouteChanged);
      ref.onDispose(
        () => router.routerDelegate.removeListener(coordinator.onRouteChanged),
      );
      ref.listen<AuthState>(
        authControllerProvider,
        (previous, next) => coordinator.onAuthChanged(next),
      );
      ref.listen<PusherClient?>(
        realtimeClientProvider,
        (previous, next) => coordinator.onRealtimeClient(next),
        fireImmediately: true,
      );
      ref.listen<String?>(currentUserIdProvider, (previous, next) {
        if (next != null && next != previous) {
          unawaited(coordinator.syncDevice());
        }
      });
      // Push texts follow the device registration's language.
      ref.listen<AppLanguage>(languageControllerProvider, (previous, next) {
        if (previous != null && previous != next) {
          unawaited(coordinator.syncDevice());
        }
      });
      ref.onDispose(coordinator.dispose);
      return coordinator;
    }, name: 'notificationCoordinatorProvider');
