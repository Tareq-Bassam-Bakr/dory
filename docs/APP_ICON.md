# App icon

The approved DORY icon is a 1024 × 1024 PNG without transparency, kept as
the master in [`assets/branding/DORY_App_Icon_1024.png`](../assets/branding/DORY_App_Icon_1024.png).
Every platform icon is generated from it; none is drawn by hand. The design
was not changed.

## Generating

The configuration is in `pubspec.yaml` (`flutter_launcher_icons`, a dev
dependency). After replacing the master, keep the same file name and run:

```powershell
dart run flutter_launcher_icons
```

Then revert `ios/Runner.xcodeproj/project.pbxproj`. Version 0.14.4 also
rewrites the unrelated setting
`ASSETCATALOG_COMPILER_GENERATE_SWIFT_ASSET_SYMBOL_EXTENSIONS` from `YES` to
`AppIcon` there. The app already uses the `AppIcon` set, so nothing in that
file needs to change.

## Android

- **Adaptive icon** (Android 8+): `res/mipmap-anydpi-v26/ic_launcher.xml`.
  - The foreground is the master (`drawable-*/ic_launcher_foreground.png`)
    with a 16% inset on each side. Launchers show only the central 72 of
    the layer's 108 units, so this inset makes the whole master fill the
    visible area instead of being cropped.
  - The background colour, `#062D73`, is the average of the master's own
    border, so no seam shows on any launcher shape. Each launcher then
    cuts its shape (circle, rounded square, …) from the blue corners.
- **Legacy icons** for older launchers: `mipmap-*/ic_launcher.png`, the
  master as a square.
- Not included: a monochrome layer for Android 13 themed icons. It would
  need a single-colour version of the design, which does not exist yet.
- `drawable/ic_stat_dory.xml` stays the small notification icon. Android
  requires that one to be a single-colour silhouette.

## iOS

`ios/Runner/Assets.xcassets/AppIcon.appiconset`: every size, including the
1024 marketing icon, from the master without alpha. iOS rounds the corners
itself. It was generated and checked for completeness (25 entries, every
file present), but not built: that needs a Mac.

## Checked

- **Previews:** the adaptive icon composed and cut to a circle and a rounded
  square, next to the legacy icon. The whole design shows in each.
- **Build:** `flutter build apk --debug` succeeded. `aapt2 dump badging`
  shows the icon `res/mipmap-anydpi-v26/ic_launcher.xml` and the label
  `DORY`.
- **Phone:** on the TECNO KM7 (Android 15), the launcher shows the new icon
  in its rounded-square shape, with the whole design, and DORY starts
  normally.
