import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The branches of a business the user may reach.
class BranchListPage extends ConsumerWidget {
  const BranchListPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Branch>> branches = ref.watch(
      branchesProvider(businessId),
    );
    final bool canCreate =
        ref
            .watch(businessProvider(businessId))
            .value
            ?.permissions
            .createBranches ??
        false;

    return DoryPageScaffold(
      title: l10n.businessBranches,
      body: AsyncValueView<List<Branch>>(
        value: branches,
        onRetry: () => ref.invalidate(branchesProvider(businessId)),
        data: (items) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (items.isEmpty)
              DoryCard(
                child: DoryEmptyState(
                  icon: Icons.store_mall_directory_outlined,
                  title: l10n.branchesEmptyTitle,
                  message: canCreate
                      ? l10n.branchesEmptyMessage
                      : l10n.branchesEmptyLimitedMessage,
                ),
              )
            else
              for (final Branch branch in items) ...<Widget>[
                _BranchTile(businessId: businessId, branch: branch),
                const SizedBox(height: AppSpacing.md),
              ],
            if (canCreate) ...<Widget>[
              const SizedBox(height: AppSpacing.lg),
              DoryButton(
                label: l10n.branchAdd,
                icon: Icons.add_rounded,
                onPressed: () => context.go(AppRoutes.branchCreate(businessId)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _BranchTile extends StatelessWidget {
  const _BranchTile({required this.businessId, required this.branch});

  final String businessId;
  final Branch branch;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final String place = <String?>[
      branch.city,
      branch.country,
    ].whereType<String>().where((s) => s.isNotEmpty).join(', ');

    return DoryCard(
      padding: EdgeInsets.zero,
      onTap: () => context.go(AppRoutes.branch(businessId, branch.id)),
      child: ListTile(
        leading: const CircleAvatar(
          backgroundColor: AppColors.primaryContainer,
          foregroundColor: AppColors.primary,
          child: Icon(Icons.store_mall_directory_outlined),
        ),
        title: Text(branch.name),
        subtitle: Text(
          <String>[
            if (place.isNotEmpty) place,
            l10n.businessStatsServices(branch.servicesCount ?? 0),
          ].join(' · '),
        ),
        trailing: activeChip(l10n, isActive: branch.isActive),
      ),
    );
  }
}
