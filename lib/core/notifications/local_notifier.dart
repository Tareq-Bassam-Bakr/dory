import 'dart:async';
import 'dart:convert';

import 'package:dory_app/core/config/push_config.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Whether Android lets DORY show notifications.
enum NotificationPermission {
  granted,

  /// Refused or turned off in the phone settings.
  denied,
}

/// Shows notifications on this device and reports taps on them
/// (docs/PUSH_NOTIFICATIONS.md). Tests replace it with a fake.
abstract interface class LocalNotifier {
  /// Creates the "DORY Queue Updates" channel; [onTap] receives the payload
  /// of a tapped notification while the app runs.
  Future<void> init({required void Function(Map<String, String>) onTap});

  Future<NotificationPermission> permission();

  /// Android 13+ shows the system prompt once; older versions allow by
  /// default. Never called before the user asked for it.
  Future<NotificationPermission> requestPermission();

  /// Opens DORY's notification settings, for a user who refused earlier.
  Future<void> openSettings();

  Future<void> show({
    required int id,
    required String title,
    required String body,
    required Map<String, String> payload,
  });

  /// The payload of the notification that started the app, if any.
  Future<Map<String, String>?> launchPayload();
}

/// [LocalNotifier] with flutter_local_notifications.
class FlutterLocalNotifier implements LocalNotifier {
  FlutterLocalNotifier([FlutterLocalNotificationsPlugin? plugin])
    : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;

  static const MethodChannel _settings = MethodChannel('dory/settings');

  static const AndroidNotificationChannel channel = AndroidNotificationChannel(
    PushConfig.androidChannelId,
    'DORY Queue Updates',
    description: 'Your turn, service updates and queue changes.',
    importance: Importance.high,
  );

  AndroidFlutterLocalNotificationsPlugin? get _android => _plugin
      .resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin
      >();

  @override
  Future<void> init({required void Function(Map<String, String>) onTap}) async {
    await _plugin.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('ic_stat_dory'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: (response) {
        final Map<String, String>? payload = decodePayload(response.payload);
        if (payload != null) {
          onTap(payload);
        }
      },
    );
    await _android?.createNotificationChannel(channel);
  }

  @override
  Future<NotificationPermission> permission() async {
    final bool? enabled = await _android?.areNotificationsEnabled();
    return enabled ?? false
        ? NotificationPermission.granted
        : NotificationPermission.denied;
  }

  @override
  Future<NotificationPermission> requestPermission() async {
    final bool? granted = await _android?.requestNotificationsPermission();
    return granted ?? false
        ? NotificationPermission.granted
        : NotificationPermission.denied;
  }

  @override
  Future<void> openSettings() async {
    try {
      await _settings.invokeMethod<void>('openNotificationSettings');
    } on PlatformException {
      // Nothing more to do: the screen still explains where the setting is.
    }
  }

  @override
  Future<void> show({
    required int id,
    required String title,
    required String body,
    required Map<String, String> payload,
  }) => _plugin.show(
    id: id,
    title: title,
    body: body,
    notificationDetails: NotificationDetails(
      android: AndroidNotificationDetails(
        channel.id,
        channel.name,
        channelDescription: channel.description,
        importance: Importance.high,
        priority: Priority.high,
        icon: 'ic_stat_dory',
      ),
    ),
    payload: jsonEncode(payload),
  );

  @override
  Future<Map<String, String>?> launchPayload() async {
    final NotificationAppLaunchDetails? details = await _plugin
        .getNotificationAppLaunchDetails();
    if (details == null || !details.didNotificationLaunchApp) {
      return null;
    }
    return decodePayload(details.notificationResponse?.payload);
  }

  @visibleForTesting
  static Map<String, String>? decodePayload(String? payload) {
    if (payload == null || payload.isEmpty) {
      return null;
    }
    try {
      final Object? decoded = jsonDecode(payload);
      if (decoded is! Map) {
        return null;
      }
      return <String, String>{
        for (final MapEntry<Object?, Object?> entry in decoded.entries)
          if (entry.key is String && entry.value is String)
            entry.key! as String: entry.value! as String,
      };
    } on FormatException {
      return null;
    }
  }
}
