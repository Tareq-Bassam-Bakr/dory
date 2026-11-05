/// Application-wide constants that are not design tokens or translations.
abstract final class AppConstants {
  /// Brand name. It is a trademark and is never translated.
  static const String appName = 'DORY';

  /// How long the splash screen shows the brand before continuing.
  static const Duration splashMinimumDuration = Duration(milliseconds: 1200);
}

/// Paths of bundled assets.
abstract final class AppAssets {
  static const String fontLicense = 'assets/fonts/ibm_plex_sans_arabic/OFL.txt';
}
