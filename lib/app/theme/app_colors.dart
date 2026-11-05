import 'package:material_ui/material_ui.dart';

/// DORY brand and semantic color tokens.
///
/// Widgets should prefer `Theme.of(context).colorScheme` so that a future
/// dark theme works without code changes. Use these tokens directly only for
/// values the [ColorScheme] does not model (for example [success]).
abstract final class AppColors {
  // Brand
  static const Color primary = Color(0xFF1557A6);
  static const Color secondary = Color(0xFF3B82F6);
  static const Color primaryContainer = Color(0xFFDBEAFE);
  static const Color onPrimaryContainer = Color(0xFF0B3A70);

  // Surfaces
  static const Color background = Color(0xFFEFF6FF);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceMuted = Color(0xFFF8FAFC);
  static const Color surfaceStrong = Color(0xFFF1F5F9);

  // Text
  static const Color textPrimary = Color(0xFF0F172A);
  static const Color textSecondary = Color(0xFF475569);
  static const Color textMuted = Color(0xFF64748B);
  static const Color onPrimary = Color(0xFFFFFFFF);

  // Lines
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderStrong = Color(0xFFCBD5E1);

  // Semantic
  static const Color success = Color(0xFF15803D);
  static const Color successContainer = Color(0xFFDCFCE7);
  static const Color warning = Color(0xFFB45309);
  static const Color warningContainer = Color(0xFFFEF3C7);
  static const Color error = Color(0xFFDC2626);
  static const Color errorContainer = Color(0xFFFEE2E2);
  static const Color onErrorContainer = Color(0xFF7F1D1D);

  /// Brand gradient used by the logo mark and hero surfaces.
  static const LinearGradient brandGradient = LinearGradient(
    begin: AlignmentDirectional.topStart,
    end: AlignmentDirectional.bottomEnd,
    colors: <Color>[primary, secondary],
  );
}
