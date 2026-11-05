import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';
import 'package:dory_app/features/services/presentation/providers/service_providers.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The services of one branch, in display order.
class ServicesListPage extends ConsumerWidget {
  const ServicesListPage({
    super.key,
    required this.businessId,
    required this.branchId,
  });

  final String businessId;
  final String branchId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Service>> services = ref.watch(
      servicesProvider(branchId),
    );
    final bool canManage =
        ref.watch(branchProvider(branchId)).value?.canManageServices ?? false;

    return DoryPageScaffold(
      title: l10n.servicesTitle,
      body: AsyncValueView<List<Service>>(
        value: services,
        onRetry: () => ref.invalidate(servicesProvider(branchId)),
        data: (items) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (items.isEmpty)
              DoryCard(
                child: DoryEmptyState(
                  icon: Icons.design_services_outlined,
                  title: l10n.servicesEmptyTitle,
                  message: l10n.servicesEmptyMessage,
                ),
              )
            else
              DoryCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: <Widget>[
                    for (final Service service in items)
                      ListTile(
                        leading: const Icon(Icons.design_services_outlined),
                        title: Text(service.name),
                        subtitle: Text(
                          l10n.serviceDurationValue(service.durationMinutes),
                        ),
                        trailing: activeChip(l10n, isActive: service.isActive),
                        onTap: canManage
                            ? () => context.go(
                                AppRoutes.serviceEdit(
                                  businessId,
                                  branchId,
                                  service.id,
                                ),
                              )
                            : null,
                      ),
                  ],
                ),
              ),
            if (canManage) ...<Widget>[
              const SizedBox(height: AppSpacing.xl),
              DoryButton(
                label: l10n.serviceAdd,
                icon: Icons.add_rounded,
                onPressed: () =>
                    context.go(AppRoutes.serviceCreate(businessId, branchId)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
