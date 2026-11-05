import 'package:material_ui/material_ui.dart';

/// DORY type scale.
///
/// Letter spacing is always zero: positive tracking breaks the joining of
/// Arabic letters. Line heights are slightly taller than Material defaults
/// because Arabic glyphs have deeper ascenders and descenders.
abstract final class AppTypography {
  /// Font family declared in pubspec.yaml.
  static const String fontFamily = 'IBMPlexSansArabic';

  static TextTheme textTheme({
    required Color primaryText,
    required Color secondaryText,
  }) {
    TextStyle style(
      double size,
      FontWeight weight,
      double height, {
      Color? color,
    }) {
      return TextStyle(
        fontFamily: fontFamily,
        fontSize: size,
        fontWeight: weight,
        height: height,
        letterSpacing: 0,
        color: color ?? primaryText,
      );
    }

    return TextTheme(
      displayLarge: style(48, FontWeight.w700, 1.2),
      displayMedium: style(40, FontWeight.w700, 1.2),
      displaySmall: style(32, FontWeight.w700, 1.25),
      headlineLarge: style(28, FontWeight.w700, 1.3),
      headlineMedium: style(24, FontWeight.w700, 1.3),
      headlineSmall: style(20, FontWeight.w600, 1.35),
      titleLarge: style(18, FontWeight.w600, 1.4),
      titleMedium: style(16, FontWeight.w600, 1.4),
      titleSmall: style(14, FontWeight.w600, 1.4),
      bodyLarge: style(16, FontWeight.w400, 1.5),
      bodyMedium: style(14, FontWeight.w400, 1.5, color: secondaryText),
      bodySmall: style(12, FontWeight.w400, 1.45, color: secondaryText),
      labelLarge: style(14, FontWeight.w600, 1.3),
      labelMedium: style(12, FontWeight.w500, 1.3),
      labelSmall: style(11, FontWeight.w500, 1.3, color: secondaryText),
    );
  }
}
