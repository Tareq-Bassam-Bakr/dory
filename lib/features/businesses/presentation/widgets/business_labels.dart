import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/shared/components/status_chip.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:material_ui/material_ui.dart';

/// Localized role names.
String roleLabel(AppLocalizations l10n, BusinessRole role) => switch (role) {
  BusinessRole.owner => l10n.businessRoleOwner,
  BusinessRole.manager => l10n.businessRoleManager,
  BusinessRole.employee => l10n.businessRoleEmployee,
};

/// Chip for a business status.
Widget businessStatusChip(AppLocalizations l10n, BusinessStatus status) =>
    switch (status) {
      BusinessStatus.active => StatusChip(
        label: l10n.commonActive,
        tone: StatusTone.positive,
      ),
      BusinessStatus.inactive => StatusChip(
        label: l10n.commonInactive,
        tone: StatusTone.neutral,
      ),
      BusinessStatus.suspended || BusinessStatus.archived => StatusChip(
        label: l10n.commonSuspended,
        tone: StatusTone.negative,
      ),
    };

/// Chip for an active/inactive branch or service.
Widget activeChip(AppLocalizations l10n, {required bool isActive}) => isActive
    ? StatusChip(label: l10n.commonActive, tone: StatusTone.positive)
    : StatusChip(label: l10n.commonInactive, tone: StatusTone.neutral);
