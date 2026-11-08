import 'dart:async';

import 'package:dory_app/core/notifications/local_notifier.dart';
import 'package:dory_app/core/notifications/push_messaging.dart';

/// Records shown notifications and answers permission questions as told.
class FakeLocalNotifier implements LocalNotifier {
  FakeLocalNotifier({
    this.current = NotificationPermission.denied,
    this.answer = NotificationPermission.granted,
    this.launch,
  });

  /// What the phone reports now.
  NotificationPermission current;

  /// What the user answers to the system prompt.
  NotificationPermission answer;
  Map<String, String>? launch;

  void Function(Map<String, String>)? onTap;
  int requests = 0;
  int settingsOpened = 0;
  final List<({String title, String body, Map<String, String> payload})> shown =
      <({String title, String body, Map<String, String> payload})>[];

  @override
  Future<void> init({required void Function(Map<String, String>) onTap}) async {
    this.onTap = onTap;
  }

  @override
  Future<NotificationPermission> permission() async => current;

  @override
  Future<NotificationPermission> requestPermission() async {
    requests++;
    current = answer;
    return answer;
  }

  @override
  Future<void> openSettings() async {
    settingsOpened++;
  }

  @override
  Future<void> show({
    required int id,
    required String title,
    required String body,
    required Map<String, String> payload,
  }) async {
    shown.add((title: title, body: body, payload: payload));
  }

  @override
  Future<Map<String, String>?> launchPayload() async => launch;

  /// The user taps a notification this fake showed (or any payload).
  void tap(Map<String, String> payload) => onTap?.call(payload);
}

/// Firebase Cloud Messaging without Firebase: the test plays FCM's part.
class FakePushMessaging implements PushMessaging {
  FakePushMessaging({this.deviceToken = 'fcm-token-1', this.initial});

  String? deviceToken;
  IncomingPush? initial;
  bool started = false;

  final StreamController<String> refreshes =
      StreamController<String>.broadcast();
  final StreamController<IncomingPush> foreground =
      StreamController<IncomingPush>.broadcast();
  final StreamController<IncomingPush> opened =
      StreamController<IncomingPush>.broadcast();

  @override
  Future<bool> start() async => started = true;

  @override
  bool get available => started;

  @override
  Future<String?> token() async => deviceToken;

  @override
  Stream<String> get tokenRefreshes => refreshes.stream;

  @override
  Stream<IncomingPush> get foregroundMessages => foreground.stream;

  @override
  Stream<IncomingPush> get openedMessages => opened.stream;

  @override
  Future<IncomingPush?> initialMessage() async => initial;
}
