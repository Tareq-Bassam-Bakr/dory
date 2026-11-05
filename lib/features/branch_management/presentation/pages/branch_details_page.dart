import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// A branch's details, its services entry point and, when allowed, edit
/// and delete. Coordinates are shown as OpenStreetMap-compatible WGS 84.
class BranchDetailsPage extends ConsumerWidget {
  const BranchDetailsPage({
    super.key,
    required this.businessId,
    required this.branchId,
  });

  final String businessId;
  final String branchId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Branch> branch = ref.watch(branchProvider(branchId));
    return DoryPageScaffold(
      title: branch.value?.name ?? context.l10n.businessBranches,
      body: AsyncValueView<Branch>(
        value: branch,
        onRetry: () => ref.invalidate(branchProvider(branchId)),
        data: (value) => _BranchDetails(businessId: businessId, branch: value),
      ),
    );
  }
}

class _BranchDetails extends ConsumerStatefulWidget {
  const _BranchDetails({required this.businessId, required this.branch});

  final String businessId;
  final Branch branch;

  @override
  ConsumerState<_BranchDetails> createState() => _BranchDetailsState();
}

class _BranchDetailsState extends ConsumerState<_BranchDetails> {
  bool _deleting = false;

  Future<void> _delete() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.branchDeleteConfirmTitle,
      message: l10n.branchDeleteConfirmMessage,
      confirmLabel: l10n.commonDelete,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    setState(() => _deleting = true);
    try {
      await ref
          .read(branchActionsProvider)
          .delete(widget.businessId, widget.branch.id);
      if (mounted) {
        showResultMessage(context, l10n.branchDeleted);
        context.go(AppRoutes.branches(widget.businessId));
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        showResultMessage(context, failureMessage(l10n, failure));
        setState(() => _deleting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final Branch branch = widget.branch;
    final String address = <String?>[
      branch.address,
      branch.city,
      branch.region,
      branch.country,
    ].whereType<String>().where((s) => s.isNotEmpty).join(', ');

    Widget row(
      IconData icon,
      String label,
      String? value, {
      bool ltr = false,
    }) => ListTile(
      leading: Icon(icon),
      title: Text(label),
      subtitle: Text(
        value == null || value.isEmpty ? l10n.onboardingNotProvided : value,
        textDirection: ltr ? TextDirection.ltr : null,
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                branch.name,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
            activeChip(l10n, isActive: branch.isActive),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        DorySectionHeader(title: l10n.branchDetailsSection),
        DoryCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: <Widget>[
              row(Icons.place_outlined, l10n.branchAddressLabel, address),
              row(
                Icons.map_outlined,
                l10n.branchLocationSection,
                branch.hasLocation
                    ? '${branch.latitude}, ${branch.longitude}'
                    : l10n.branchNoLocation,
                ltr: branch.hasLocation,
              ),
              row(
                Icons.phone_outlined,
                l10n.branchPhoneLabel,
                branch.phone,
                ltr: true,
              ),
              row(
                Icons.mail_outline_rounded,
                l10n.branchEmailLabel,
                branch.email,
                ltr: true,
              ),
              row(
                Icons.schedule_rounded,
                l10n.timezoneLabel,
                branch.timezone,
                ltr: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        DoryCard(
          padding: EdgeInsets.zero,
          child: ListTile(
            leading: const Icon(Icons.design_services_outlined),
            title: Text(l10n.servicesTitle),
            subtitle: Text(
              l10n.businessStatsServices(branch.servicesCount ?? 0),
            ),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () =>
                context.go(AppRoutes.services(widget.businessId, branch.id)),
          ),
        ),
        if (branch.canUpdate) ...<Widget>[
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: l10n.branchEditTitle,
            icon: Icons.edit_outlined,
            variant: DoryButtonVariant.secondary,
            onPressed: () =>
                context.go(AppRoutes.branchEdit(widget.businessId, branch.id)),
          ),
        ],
        if (branch.canDelete) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          DoryButton(
            label: l10n.branchDelete,
            icon: Icons.delete_outline_rounded,
            variant: DoryButtonVariant.tertiary,
            isLoading: _deleting,
            onPressed: _delete,
          ),
        ],
      ],
    );
  }
}
