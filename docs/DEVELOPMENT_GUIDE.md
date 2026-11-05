# Development Guide

## Requirements

| Tool | Version used for Phase 01 | Notes |
|---|---|---|
| Flutter | 3.44.4 (stable) | Minimum declared in `pubspec.yaml`: `>=3.44.0` |
| Dart | 3.12.2 | `sdk: ^3.12.2` |
| Android SDK | Platform 36.1, build-tools 36.1 | Licenses accepted (`flutter doctor --android-licenses`) |
| JDK | 17+ (Android Studio's bundled JBR 21 works) | |
| Android minSdk | 24 (Flutter default) | Android 7.0+ |
| Xcode | Required for iOS builds (macOS only) | iOS project is generated but not yet built in Phase 01 |

Check your environment:

```bash
flutter doctor -v
```

## Setup

```bash
flutter pub get
```

`pub get` also generates localizations (`generate: true`). To regenerate them
by hand after editing an ARB file:

```bash
flutter gen-l10n
```

## Running

List devices and emulators:

```bash
flutter devices
```

```bash
flutter emulators --launch Pixel_4
```

Run in debug mode on a specific device:

```bash
flutter run -d emulator-5554
```

While `flutter run` is active: `r` hot reload, `R` hot restart, `q` quit.

## Quality gates

Run all four before every commit or pull request:

```bash
dart format --set-exit-if-changed lib test integration_test
```

```bash
flutter analyze
```

```bash
flutter test
```

```bash
flutter test integration_test -d emulator-5554
```

- `analysis_options.yaml` extends `flutter_lints` with strict casts,
  inference, and raw types, plus extra rules (package imports, trailing
  commas, final locals, unawaited futures, and more).
- `flutter test` runs unit, widget, and layout tests (no device needed).
- `integration_test/` runs the **real** `bootstrap()` on a device or emulator,
  including platform shared_preferences.

## Testing conventions

- Test files mirror `lib/` paths and end with `_test.dart`.
- Use `test/helpers/test_app.dart`:
  - `createTestContainer()` creates a `ProviderContainer` with in-memory
    storage, auto-disposed after the test.
  - `pumpDoryApp(tester, container)` pumps the full app.
  - `pumpLocalizedWidget(tester, widget)` pumps one widget with the DORY theme
    and localization.
  - `setTestWindowSize(tester, size)` sets the window size, reset
    automatically. The default test surface (800×600) is **wider than the
    compact breakpoint**, so pass a phone size when testing phone layouts.
- Import `package:material_ui/material_ui.dart` in tests, not
  `package:flutter/material.dart`. Note that `flutter_test` helpers bound to
  the framework's Material classes (`find.byTooltip`, `tester.pageBack()`) do
  not see `material_ui` widgets. Use `find.byIcon`, `find.text`, or semantics
  finders instead.
- Dispose a `SemanticsHandle` at the end of the test body, not in
  `addTearDown`. The framework checks for leaked handles before teardowns run.

## Dependency policy

Add a package only when Phase code uses it. Prefer first-party (`flutter.dev`,
`dart.dev`) packages and pin with caret constraints.

### Installed in Phase 01

| Package | Resolved | Purpose |
|---|---|---|
| `material_ui` | 1.2.0 | Material Design library (decoupled from the framework). Required because go_router 18 depends on it. 1.4+ needs Flutter 3.47. |
| `flutter_riverpod` | 3.4.3 | State management and DI |
| `go_router` | 18.1.3 | Navigation, deep links, shells |
| `shared_preferences` | 2.5.6 | Persisted language preference |
| `intl` | 0.20.2 | Required by generated localizations (pinned by `flutter_localizations`) |
| `flutter_localizations` | SDK | Locale data for gen-l10n |
| `integration_test` (dev) | SDK | On-device tests |
| `flutter_lints` (dev) | 6.0.0 | Lint rules |

### Evaluated and deferred

| Package | Latest (Nov 2026) | Compatible with this SDK? | Deferred until |
|---|---|---|---|
| `dio` | 5.11.1 | Yes | Phase 02: first API calls (auth) |
| `flutter_secure_storage` | 11.2.0 | Yes | Phase 02: storing auth tokens |
| `freezed` | 4.0.3 | **No**: needs Dart ≥ 3.13. Use 3.x, or upgrade Flutter. | Phase 02: DTOs and immutable state |
| `freezed_annotation` | 3.1.0 | Yes | With `freezed` |
| `json_serializable` | 6.15.2 | Yes | Phase 02: DTOs |
| `build_runner` | 2.17.1 | Yes | With `freezed` / `json_serializable` |
| `flutter_map` | 8.3.2 | Yes | Maps phase |
| `latlong2` | 0.10.1 | Yes | With `flutter_map` |
| `google_fonts` | 9.0.0 | **No**: needs Flutter ≥ 3.47 | Not planned: the font is bundled instead |

Check for updates with:

```bash
flutter pub outdated
```

## App identity

| Platform | Identifier | Where to change it |
|---|---|---|
| Android `applicationId` | `com.dory.app` | `android/gradle.properties` → `dory.applicationId`, or per build: `flutter build apk -Pdory.applicationId=<id>` |
| Android code namespace | `com.dory.dory_app` | `android/app/build.gradle.kts` (`namespace`) and the `MainActivity.kt` package. Internal only. |
| iOS bundle identifier | `com.dory.app` | Xcode → Runner target → Signing & Capabilities (`PRODUCT_BUNDLE_IDENTIFIER`) |
| Display name | `DORY` | `AndroidManifest.xml` `android:label`, iOS `Info.plist` `CFBundleDisplayName` |

`com.dory.app` is a **placeholder**. Before the first store release, replace
it with a reverse domain the company owns (for example `com.<company>.dory`).
**An app ID can never change after it is published.**

## Release checklist (not needed for Phase 01)

- [ ] Final application/bundle identifiers (see above).
- [ ] Release signing config for Android (currently uses the debug key).
- [ ] Official launcher icons and splash assets (currently Flutter defaults).
- [ ] Official logo to replace the `DoryLogo` placeholder.
- [ ] Crash reporting wired into `ErrorReporter`.
- [ ] Android 13+ per-app language support (`android:localeConfig`).

## Environment notes (Phase 01 machine)

- The Windows desktop target cannot be built (Visual Studio is not
  installed). It is not needed: DORY targets Android and iOS.
- **Gradle memory.** Flutter's template sets `org.gradle.jvmargs=-Xmx8G
  -XX:MaxMetaspaceSize=4G`. On the 8 GB Phase 01 machine, with the emulator
  running, the Gradle daemon crashed with a native out-of-memory error
  ("Gradle build daemon disappeared unexpectedly"). `android/gradle.properties`
  now caps the daemon at `-Xmx3G` (metaspace 1G) and the Kotlin daemon at 1G.
  Raise these on larger machines or CI if a build reports heap exhaustion.
- On low-RAM machines, the first Android build is slow (about 15 minutes,
  including Gradle and dependency downloads). Later builds are incremental.
  Closing other heavy apps while the emulator runs helps a lot.
