import 'dart:async';

import 'package:dory_app/core/errors/error_reporter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// A push message as the app sees it: the visible text and the data
/// (kind and public ids; never a guest ticket token).
@immutable
class IncomingPush {
  const IncomingPush({this.title, this.body, this.data = const {}});

  final String? title;
  final String? body;
  final Map<String, Object?> data;
}

/// Firebase Cloud Messaging, behind an interface so the app runs, and is
/// tested, without Firebase (docs/PUSH_NOTIFICATIONS.md).
abstract interface class PushMessaging {
  /// Starts Firebase. False when push is unavailable: not configured, or
  /// Firebase could not start. The app then works without push.
  Future<bool> start();

  bool get available;

  Future<String?> token();

  Stream<String> get tokenRefreshes;

  /// Messages received while DORY is in front (Android does not show them).
  Stream<IncomingPush> get foregroundMessages;

  /// Notifications tapped while DORY was in the background.
  Stream<IncomingPush> get openedMessages;

  /// The notification that started DORY from a terminated state.
  Future<IncomingPush?> initialMessage();
}

/// Push is off (`DORY_FCM_ENABLED` not set): nothing touches Firebase.
class DisabledPushMessaging implements PushMessaging {
  const DisabledPushMessaging();

  @override
  Future<bool> start() async => false;

  @override
  bool get available => false;

  @override
  Future<String?> token() async => null;

  @override
  Stream<String> get tokenRefreshes => const Stream<String>.empty();

  @override
  Stream<IncomingPush> get foregroundMessages =>
      const Stream<IncomingPush>.empty();

  @override
  Stream<IncomingPush> get openedMessages => const Stream<IncomingPush>.empty();

  @override
  Future<IncomingPush?> initialMessage() async => null;
}

/// Runs in a background isolate when a message arrives while DORY is not
/// in front. Android displays the notification part itself; nothing here
/// may touch the UI or read secrets.
@pragma('vm:entry-point')
Future<void> doryFirebaseBackgroundHandler(RemoteMessage message) async {}

/// [PushMessaging] with firebase_messaging. Needs the Android project's
/// google-services.json (docs/FIREBASE_SETUP.md).
class FirebasePushMessaging implements PushMessaging {
  bool _available = false;

  @override
  bool get available => _available;

  @override
  Future<bool> start() async {
    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(doryFirebaseBackgroundHandler);
      _available = true;
    } on Object catch (error, stackTrace) {
      ErrorReporter.report(
        error,
        stackTrace,
        context: 'Firebase did not start; push notifications are off',
      );
      _available = false;
    }
    return _available;
  }

  @override
  Future<String?> token() async {
    if (!_available) {
      return null;
    }
    try {
      return await FirebaseMessaging.instance.getToken();
    } on Object catch (error, stackTrace) {
      ErrorReporter.report(error, stackTrace, context: 'FCM token unavailable');
      return null;
    }
  }

  @override
  Stream<String> get tokenRefreshes => _available
      ? FirebaseMessaging.instance.onTokenRefresh
      : const Stream<String>.empty();

  @override
  Stream<IncomingPush> get foregroundMessages => _available
      ? FirebaseMessaging.onMessage.map(_convert)
      : const Stream<IncomingPush>.empty();

  @override
  Stream<IncomingPush> get openedMessages => _available
      ? FirebaseMessaging.onMessageOpenedApp.map(_convert)
      : const Stream<IncomingPush>.empty();

  @override
  Future<IncomingPush?> initialMessage() async {
    if (!_available) {
      return null;
    }
    final RemoteMessage? message = await FirebaseMessaging.instance
        .getInitialMessage();
    return message == null ? null : _convert(message);
  }

  static IncomingPush _convert(RemoteMessage message) => IncomingPush(
    title: message.notification?.title,
    body: message.notification?.body,
    data: Map<String, Object?>.of(message.data),
  );
}
