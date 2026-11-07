import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// A branch and the services a customer can pick there.
///
/// Availability comes only from each queue's live state: there are no
/// opening hours in the backend, so none are shown or implied.
class BranchDetailsPage extends ConsumerWidget {
  const BranchDetailsPage({super.key, required this.branchId});

  final String branchId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<PublicBranch> branch = ref.watch(
      publicBranchProvider(branchId),
    );

    return DoryPageScaffold(
      title: branch.value?.name,
      body: AsyncValueView<PublicBranch>(
        value: branch,
        onRetry: () => ref.invalidate(publicBranchProvider(branchId)),
        data: (data) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            _Header(branch: data),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.branchDetailsServices),
            SectionValue<List<PublicService>>(
              value: ref.watch(branchServicesProvider(branchId)),
              onRetry: () => ref.invalidate(branchServicesProvider(branchId)),
              data: (services) => services.isEmpty
                  ? DoryCard(
                      child: DoryEmptyState(
                        icon: Icons.room_service_outlined,
                        title: l10n.branchDetailsNoServices,
                      ),
                    )
                  : CardColumn(
                      children: <Widget>[
                        for (final PublicService service in services)
                          _ServiceCard(service: service),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.branch});

  final PublicBranch branch;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String place = placeLine(
      l10n,
      city: branch.city,
      distanceKm: branch.distanceKm,
    );

    return DoryCard(
      onTap: () =>
          context.push(AppRoutes.customerBusiness(branch.business.slug)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          BusinessLogo(
            url: branch.business.logoUrl,
            category: branch.business.category,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(branch.business.name, style: text.titleMedium),
                Text(branch.name, style: text.bodyMedium),
                if (branch.address != null)
                  Text(branch.address!, style: text.bodySmall),
                if (place.isNotEmpty) Text(place, style: text.bodySmall),
                if (branch.phone != null)
                  Text(
                    branch.phone!,
                    style: text.bodySmall,
                    textDirection: TextDirection.ltr,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service});

  final PublicService service;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final QueueAvailability? queue = service.queue;

    return DoryCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: queue == null
          ? null
          : () => context.push(AppRoutes.customerQueue(queue.id)),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(service.name, style: text.titleSmall),
                if (service.description != null)
                  Text(
                    service.description!,
                    style: text.bodySmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                const SizedBox(height: AppSpacing.xs),
                if (queue == null)
                  Text(l10n.branchDetailsNoQueue, style: text.bodySmall)
                else
                  Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.xs,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: <Widget>[
                      queueStateChip(l10n, queue),
                      if (queue.acceptingJoins)
                        Text(
                          waitLabel(l10n, queue.estimatedMinutes),
                          style: text.labelMedium,
                        ),
                    ],
                  ),
              ],
            ),
          ),
          if (queue != null)
            const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }
}
