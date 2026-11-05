import 'package:dory_app/shared/enums/app_workspace.dart';

/// Roles a user can hold. A user holds a *set* of roles, never a single one.
///
/// Business-scoped roles (owner, manager, employee) will be granted per
/// business or branch by the backend; Phase 01 only models the role kinds.
enum UserRole {
  customer(AppWorkspace.customer),
  businessOwner(AppWorkspace.business),
  branchManager(AppWorkspace.business),
  employee(AppWorkspace.business),
  superAdmin(AppWorkspace.administration);

  const UserRole(this.workspace);

  /// Workspace this role gives access to.
  final AppWorkspace workspace;
}

extension UserRoleSet on Set<UserRole> {
  /// Workspaces available to a user holding these roles, in a stable order.
  List<AppWorkspace> get availableWorkspaces => <AppWorkspace>[
    for (final AppWorkspace workspace in AppWorkspace.values)
      if (any((role) => role.workspace == workspace)) workspace,
  ];

  bool canAccess(AppWorkspace workspace) =>
      any((role) => role.workspace == workspace);
}
