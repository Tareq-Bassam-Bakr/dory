import 'dart:io';

import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/features/authentication/presentation/pages/welcome_page.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/test_app.dart';

void main() {
  group('AppTheme.light', () {
    final ThemeData theme = AppTheme.light;

    test('uses the DORY brand palette', () {
      expect(theme.colorScheme.primary, const Color(0xFF1557A6));
      expect(theme.colorScheme.secondary, const Color(0xFF3B82F6));
      expect(theme.scaffoldBackgroundColor, const Color(0xFFEFF6FF));
      expect(theme.colorScheme.onSurface, const Color(0xFF0F172A));
      expect(theme.colorScheme.surface, const Color(0xFFFFFFFF));
      expect(theme.brightness, Brightness.light);
    });

    test('uses Material 3', () {
      expect(theme.useMaterial3, isTrue);
    });

    test('sets IBM Plex Sans Arabic with zero tracking on every style', () {
      final TextTheme text = theme.textTheme;
      final Map<String, TextStyle?> styles = <String, TextStyle?>{
        'displayLarge': text.displayLarge,
        'displayMedium': text.displayMedium,
        'displaySmall': text.displaySmall,
        'headlineLarge': text.headlineLarge,
        'headlineMedium': text.headlineMedium,
        'headlineSmall': text.headlineSmall,
        'titleLarge': text.titleLarge,
        'titleMedium': text.titleMedium,
        'titleSmall': text.titleSmall,
        'bodyLarge': text.bodyLarge,
        'bodyMedium': text.bodyMedium,
        'bodySmall': text.bodySmall,
        'labelLarge': text.labelLarge,
        'labelMedium': text.labelMedium,
        'labelSmall': text.labelSmall,
      };

      styles.forEach((name, style) {
        expect(style, isNotNull, reason: name);
        expect(style!.fontFamily, AppTypography.fontFamily, reason: name);
        // Positive letter spacing breaks Arabic letter joining.
        expect(style.letterSpacing, 0, reason: name);
      });
    });

    test('keeps surfaces free of Material 3 elevation tint', () {
      expect(theme.colorScheme.surfaceTint.a, 0);
    });

    test('bundles every declared font file and its license', () {
      const String dir = 'assets/fonts/ibm_plex_sans_arabic';
      for (final String file in <String>[
        'IBMPlexSansArabic-Regular.ttf',
        'IBMPlexSansArabic-Medium.ttf',
        'IBMPlexSansArabic-SemiBold.ttf',
        'IBMPlexSansArabic-Bold.ttf',
        'OFL.txt',
      ]) {
        expect(File('$dir/$file').existsSync(), isTrue, reason: file);
      }
    });
  });

  testWidgets('DoryApp applies the DORY theme', (tester) async {
    final ProviderContainer container = createTestContainer();
    await pumpDoryApp(tester, container);
    container.read(appRouterProvider).go(AppRoutes.welcome);
    await tester.pumpAndSettle();

    final ThemeData applied = Theme.of(
      tester.element(find.byType(WelcomePage)),
    );
    expect(applied.colorScheme.primary, AppColors.primary);
    expect(applied.textTheme.bodyLarge?.fontFamily, AppTypography.fontFamily);
  });
}
