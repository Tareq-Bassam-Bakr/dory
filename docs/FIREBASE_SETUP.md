# Firebase setup

Push is implemented and off ([PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md)).
These steps turn it on. They need your Google account and are done by you.
DORY never creates a project, a key or a config file for you. Firebase
Cloud Messaging is free. Nothing below needs a paid plan: stay on the
**Spark** plan and do not enable billing.

## A. Create the project

1. Open <https://console.firebase.google.com>, choose **Create a project**,
   and name it, for example "DORY Dev".
2. Google Analytics is not needed: turn it off.

## B. Add the Android app

1. In the project: **Add app → Android**.
2. Package name: **`com.dory.app`** (the `applicationId` in
   `android/app/build.gradle.kts`). It must match exactly.
3. The SHA-1 fingerprint is not needed for push.
4. Download **`google-services.json`**.

## C. Put `google-services.json` in place

Copy it to `android/app/google-services.json`. Git ignores it:
it identifies your project, and keeping it out of the repository is the
rule here.

The build applies the Google services plugin only when this file exists
(`android/app/build.gradle.kts`). Without the file, the app builds and runs
with push off.

## D. Create the service account key (server)

1. **Project settings → Service accounts → Generate new private key**.
2. Save the JSON **outside the repository**, for example
   `D:\dory-secrets\firebase-service-account.json`.
3. This file is a secret: it can send messages as your project. Never commit
   it, never send it in chat, never copy it into the app.

## E. Configure the backend (`backend/.env`)

```dotenv
FCM_ENABLED=true
FIREBASE_CREDENTIALS=D:/dory-secrets/firebase-service-account.json
```

Then clear the cached configuration and restart the queue worker (push is
sent by the worker):

```powershell
D:\php84\php.exe artisan config:clear
```

`stop_dory.bat` and `start_dory.bat` restart everything. If the file is
missing or unreadable, DORY keeps push off without failing requests.

## F. Build the app with push

`start_dory.bat` adds `DORY_FCM_ENABLED=true` by itself when
`FCM_ENABLED=true` and `android/app/google-services.json` exists. By hand:

```powershell
flutter run --dart-define=DORY_FCM_ENABLED=true --dart-define=DORY_REVERB_APP_KEY=<key> ...
```

## G. Test on a real phone

1. Sign in, join a queue, and allow notifications when the ticket screen
   offers them. In `push_devices`, check that a row with your user exists
   (the token is encrypted; check only that the row is there).
2. Put DORY in the background. From the staff side, call the ticket. "It's
   your turn" should appear.
3. Swipe DORY away and repeat with "Skip". Tap the notification: the ticket
   opens.
4. As a guest (signed out): join, allow notifications, call the ticket.
5. Arabic: switch the app to Arabic. The device registration is updated to
   `ar` at once; call the ticket again.
6. Force-stop DORY and call again: nothing arrives. This is expected
   ([PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md#the-app)).
7. The worker window (DORY-QUEUE) shows `SendPushMessage` done for each
   device. "Accepted" means FCM took the message, not that the phone
   showed it. Only the phone proves delivery.

## H. Troubleshooting

| Symptom | Check |
|---|---|
| No `push_devices` row | `DORY_FCM_ENABLED=true` in the build, permission granted, `google-services.json` present before the build |
| Row, but no message | `FCM_ENABLED=true`, `config:clear`, worker restarted, credential path readable |
| Worker log "push device revoked" | FCM rejected the token, often after reinstalling. Reopen the app; it registers again |
| Nothing after force stop | Expected |

## I. Rotate or remove

- If the service account key leaks: delete the key under **Service
  accounts**, then generate a new one.
- To turn push off: `FCM_ENABLED=false`, `config:clear`, and restart.

## J. iOS (later)

iOS push needs an Apple Developer account, an APNs key uploaded to Firebase,
`GoogleService-Info.plist`, the Push Notifications capability, and
`UIBackgroundModes` → `remote-notification` in `ios/Runner/Info.plist`.

`test/core/location/location_policy_test.dart` currently forbids any
`UIBackgroundModes` entry, so that background location cannot slip in.
When APNs is configured, narrow that test to allow exactly
`remote-notification` and nothing else.

None of this exists yet. iOS push is **not** claimed to work.

## Package versions

`firebase_messaging` 17 deprecates `getToken()` and `onTokenRefresh` in
favour of Firebase Installation ID registration (`register()`). DORY
uses 16.7.1 with the classic token, which the HTTP v1 API on the server
accepts. Moving to 17 belongs to the first real-device test with a Firebase
project, where the new flow can be checked end to end.
