import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Switches between the customer and business workspaces.
///
/// Phase 01 offers the switch to everyone because no session exists. From
/// Phase 02 it is shown only when the user's roles grant the target
/// workspace (see `UserRoleSet.canAccess`).
class WorkspaceSwitchTile extends StatelessWidget {
  const WorkspaceSwitchTile({super.key, required this.currentWorkspace});

  final AppWorkspace currentWorkspace;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final bool toBusiness = currentWorkspace != AppWorkspace.business;
    final AppWorkspace target = toBusiness
        ? AppWorkspace.business
        : AppWorkspace.customer;

    return ListTile(
      leading: Icon(
        toBusiness ? Icons.storefront_outlined : Icons.explore_outlined,
      ),
      title: Text(
        toBusiness
            ? l10n.accountSwitchToBusiness
            : l10n.accountSwitchToCustomer,
      ),
      subtitle: Text(
        toBusiness
            ? l10n.accountSwitchToBusinessDescription
            : l10n.accountSwitchToCustomerDescription,
      ),
      trailing: const Icon(Icons.swap_horiz_rounded),
      onTap: () => context.go(AppRoutes.workspaceHome(target)),
    );
  }
}
