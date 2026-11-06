import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Business Queues Overview: every queue of the user's businesses, with
/// whether it is open right now.
class BusinessQueuesPage extends ConsumerWidget {
  const BusinessQueuesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Business>> businesses = ref.watch(
      myBusinessesProvider,
    );

    return DoryPageScaffold(
      title: l10n.queuesTitle,
      body: AsyncValueView<List<Business>>(
        value: businesses,
        onRetry: () => ref.invalidate(myBusinessesProvider),
        data: (items) => items.isEmpty
            ? DoryCard(
                child: DoryEmptyState(
                  icon: Icons.format_list_numbered_rounded,
                  title: l10n.queuesEmptyTitle,
                  message: l10n.queuesNoBusiness,
                ),
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  for (final Business business in items)
                    _BusinessQueues(
                      business: business,
                      showName: items.length > 1,
                    ),
                ],
              ),
      ),
    );
  }
}

class _BusinessQueues extends ConsumerWidget {
  const _BusinessQueues({required this.business, required this.showName});

  final Business business;
  final bool showName;

  bool get _canCreate =>
      business.role == BusinessRole.owner ||
      business.role == BusinessRole.manager;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<QueueConfig>> queues = ref.watch(
      businessQueuesProvider(business.id),
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (showName) DorySectionHeader(title: business.name),
          AsyncValueView<List<QueueConfig>>(
            value: queues,
            onRetry: () => ref.invalidate(businessQueuesProvider(business.id)),
            data: (items) => items.isEmpty
                ? DoryCard(
                    child: DoryEmptyState(
                      icon: Icons.format_list_numbered_rounded,
                      title: l10n.queuesEmptyTitle,
                      message: l10n.queuesEmptyMessage,
                    ),
                  )
                : DoryCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: <Widget>[
                        for (final QueueConfig queue in items)
                          ListTile(
                            leading: const Icon(
                              Icons.format_list_numbered_rounded,
                            ),
                            title: Text(queue.name),
                            subtitle: Text(
                              l10n.queueTileSubtitle(
                                queue.branch.name,
                                firstTicketExample(queue.ticketPrefix),
                              ),
                            ),
                            trailing: queue.isActive
                                ? sessionStatusChip(
                                    l10n,
                                    queue.currentSession?.status,
                                  )
                                : sessionStatusChip(l10n, null),
                            onTap: () => context.go(AppRoutes.queue(queue.id)),
                          ),
                      ],
                    ),
                  ),
          ),
          if (_canCreate) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.queueCreate,
              icon: Icons.add_rounded,
              variant: DoryButtonVariant.secondary,
              onPressed: () => context.go(AppRoutes.queueCreate(business.id)),
            ),
          ],
        ],
      ),
    );
  }
}
