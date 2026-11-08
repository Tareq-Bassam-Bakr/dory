/// Push notifications through Firebase Cloud Messaging
/// (docs/PUSH_NOTIFICATIONS.md, docs/FIREBASE_SETUP.md).
///
/// Off unless the build passes `--dart-define=DORY_FCM_ENABLED=true` and the
/// Android project has its `google-services.json`. While off, the app never
/// touches Firebase: notifications still arrive in the notification center
/// and, while the app is open, through Reverb.
abstract final class PushConfig {
  static const bool enabled = bool.fromEnvironment('DORY_FCM_ENABLED');

  /// The Android notification channel; the server sends with the same id.
  static const String androidChannelId = 'dory_queue_updates';
}
