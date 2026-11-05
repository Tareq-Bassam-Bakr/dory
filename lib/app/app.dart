import 'package:dory_app/app/localization/app_locales.dart';
import 'package:dory_app/app/localization/language_controller.dart';
import 'package:dory_app/app/router/app_router.dart';
import 'package:dory_app/app/theme/app_theme.dart';
import 'package:dory_app/core/constants/app_constants.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Root widget: wires theme, localization, and routing together.
class DoryApp extends ConsumerWidget {
  const DoryApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GoRouter router = ref.watch(appRouterProvider);
    final AppLanguage language = ref.watch(languageControllerProvider);

    return MaterialApp.router(
      title: AppConstants.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      themeMode: ThemeMode.light,
      // null follows the device language; unsupported languages fall back to
      // the first entry of AppLocales.supported (English).
      locale: language.locale,
      supportedLocales: AppLocales.supported,
      localizationsDelegates: AppLocales.delegates,
      routerConfig: router,
    );
  }
}
