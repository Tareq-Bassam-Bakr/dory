import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Shown for unknown locations, e.g. a stale deep link.
class NotFoundPage extends StatelessWidget {
  const NotFoundPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return DoryPageScaffold(
      scrollable: false,
      body: DoryErrorState(
        icon: Icons.explore_off_outlined,
        title: l10n.notFoundTitle,
        message: l10n.notFoundMessage,
        retry: DoryStatusAction(
          label: l10n.notFoundAction,
          onPressed: () => context.go(AppRoutes.welcome),
        ),
      ),
    );
  }
}
