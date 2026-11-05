/// Responsive layout constants, aligned with Material window size classes.
abstract final class AppLayout {
  /// Below this width the window is "compact" (phones in portrait).
  static const double compactMaxWidth = 600;

  /// Readable content is never stretched wider than this on large screens.
  static const double maxContentWidth = 640;

  /// Forms (login, registration) use a narrower column.
  static const double maxFormWidth = 480;

  static bool isCompact(double width) => width < compactMaxWidth;
}
