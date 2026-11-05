import 'package:dory_app/shared/enums/app_workspace.dart';
import 'package:dory_app/shared/enums/user_role.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('UserRole.workspace', () {
    test('maps every role to its workspace', () {
      expect(UserRole.customer.workspace, AppWorkspace.customer);
      expect(UserRole.businessOwner.workspace, AppWorkspace.business);
      expect(UserRole.branchManager.workspace, AppWorkspace.business);
      expect(UserRole.employee.workspace, AppWorkspace.business);
      expect(UserRole.superAdmin.workspace, AppWorkspace.administration);
    });
  });

  group('UserRoleSet', () {
    test('a customer who also owns a business gets both workspaces', () {
      const Set<UserRole> roles = <UserRole>{
        UserRole.customer,
        UserRole.businessOwner,
      };
      expect(roles.availableWorkspaces, <AppWorkspace>[
        AppWorkspace.customer,
        AppWorkspace.business,
      ]);
      expect(roles.canAccess(AppWorkspace.customer), isTrue);
      expect(roles.canAccess(AppWorkspace.business), isTrue);
      expect(roles.canAccess(AppWorkspace.administration), isFalse);
    });

    test('several business roles collapse into one workspace', () {
      const Set<UserRole> roles = <UserRole>{
        UserRole.branchManager,
        UserRole.employee,
      };
      expect(roles.availableWorkspaces, <AppWorkspace>[AppWorkspace.business]);
    });

    test('an employee cannot access the customer workspace by role', () {
      const Set<UserRole> roles = <UserRole>{UserRole.employee};
      expect(roles.canAccess(AppWorkspace.customer), isFalse);
    });

    test('no roles grant no workspace', () {
      const Set<UserRole> roles = <UserRole>{};
      expect(roles.availableWorkspaces, isEmpty);
      for (final AppWorkspace workspace in AppWorkspace.values) {
        expect(roles.canAccess(workspace), isFalse);
      }
    });

    test('workspace order is stable regardless of role order', () {
      const Set<UserRole> roles = <UserRole>{
        UserRole.superAdmin,
        UserRole.employee,
        UserRole.customer,
      };
      expect(roles.availableWorkspaces, AppWorkspace.values);
    });
  });
}
