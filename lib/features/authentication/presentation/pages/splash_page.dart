import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/constants/app_constants.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_controller.dart';
import 'package:dory_app/features/authentication/presentation/providers/auth_state.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Brand screen shown at launch while the stored session is restored.
///
/// It continues once both the minimum brand time has passed and the
/// session check finished: to `?from=` (a deep link that needed the
/// session), to the signed-in user's home workspace, or to welcome.
class SplashPage extends ConsumerStatefulWidget {
  const SplashPage({super.key, this.from});

  final String? from;

  @override
  ConsumerState<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends ConsumerState<SplashPage> {
  Timer? _timer;
  bool _minimumElapsed = false;
  bool _sessionChecked = false;

  @override
  void initState() {
    super.initState();
    _timer = Timer(AppConstants.splashMinimumDuration, () {
      _minimumElapsed = true;
      _continue();
    });
    unawaited(
      ref.read(authControllerProvider.notifier).ready.then((_) {
        _sessionChecked = true;
        _continue();
      }),
    );
  }

  void _continue() {
    if (!mounted || !_minimumElapsed || !_sessionChecked) {
      return;
    }
    final AuthState auth = ref.read(authControllerProvider);
    final String? from = widget.from;
    final Uri? target = from == null ? null : Uri.parse(from);
    context.go(switch (auth) {
      Authenticated(:final user) => from ?? AppRoutes.homeFor(user),
      _ when target == null => AppRoutes.welcome,
      // A protected deep link: sign in first, then continue there.
      _ when AppRoutes.requiresAuthentication(target.path) =>
        AppRoutes.withFrom(AppRoutes.login, target),
      _ => from!,
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      body: Center(
        child: TweenAnimationBuilder<double>(
          tween: Tween<double>(begin: 0, end: 1),
          duration: const Duration(milliseconds: 500),
          curve: Curves.easeOut,
          builder: (context, value, child) => Opacity(
            opacity: value,
            child: Transform.scale(scale: 0.94 + 0.06 * value, child: child),
          ),
          child: DoryLogo(markSize: 88, tagline: context.l10n.appTagline),
        ),
      ),
    );
  }
}
