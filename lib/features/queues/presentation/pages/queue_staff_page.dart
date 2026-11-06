import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/presentation/widgets/business_labels.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:dory_app/features/team/domain/entities/team_entities.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/enums/business_role.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Assign Employees: who operates the queue besides owners and managers.
class QueueStaffPage extends ConsumerStatefulWidget {
  const QueueStaffPage({super.key, required this.queueId});

  final String queueId;

  @override
  ConsumerState<QueueStaffPage> createState() => _QueueStaffPageState();
}

class _QueueStaffPageState extends ConsumerState<QueueStaffPage> {
  String? _busyId;

  Future<void> _run(
    String id,
    Future<void> Function() action,
    String message,
  ) async {
    setState(() => _busyId = id);
    await runQueueAction(context, action, successMessage: message);
    if (mounted) {
      setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<QueueConfig> queue = ref.watch(
      queueProvider(widget.queueId),
    );
    final AsyncValue<List<QueueStaffAssignment>> staff = ref.watch(
      queueStaffProvider(widget.queueId),
    );

    return DoryPageScaffold(
      title: l10n.queueStaffTitle,
      body: AsyncValueView<List<QueueStaffAssignment>>(
        value: staff,
        onRetry: () => ref.invalidate(queueStaffProvider(widget.queueId)),
        data: (assignments) {
          final bool canManage = queue.value?.canManage ?? false;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                l10n.queueStaffMessage,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.lg),
              DorySectionHeader(title: l10n.queueStaffAssignedTitle),
              DoryCard(
                padding: assignments.isEmpty
                    ? const EdgeInsets.all(AppSpacing.lg)
                    : EdgeInsets.zero,
                child: assignments.isEmpty
                    ? Text(l10n.queueStaffEmpty)
                    : Column(
                        children: <Widget>[
                          for (final QueueStaffAssignment assignment
                              in assignments)
                            ListTile(
                              leading: const Icon(Icons.badge_outlined),
                              title: Text(assignment.name),
                              subtitle: Text(
                                roleLabel(
                                  l10n,
                                  BusinessRole.fromApi(assignment.role),
                                ),
                              ),
                              trailing: canManage
                                  ? TextButton(
                                      onPressed: _busyId != null
                                          ? null
                                          : () => _run(
                                              assignment.id,
                                              () => ref
                                                  .read(queueActionsProvider)
                                                  .unassign(
                                                    widget.queueId,
                                                    assignment.id,
                                                  ),
                                              l10n.queueStaffRemoved,
                                            ),
                                      child: Text(l10n.commonRemove),
                                    )
                                  : null,
                            ),
                        ],
                      ),
              ),
              if (canManage && queue.value != null) ...<Widget>[
                const SizedBox(height: AppSpacing.xl),
                _Candidates(
                  queue: queue.requireValue,
                  assigned: <String>{
                    for (final QueueStaffAssignment assignment in assignments)
                      assignment.memberId,
                  },
                  busyId: _busyId,
                  onAssign: (member) => _run(
                    member.id,
                    () => ref
                        .read(queueActionsProvider)
                        .assign(widget.queueId, member.id),
                    l10n.queueStaffAssigned,
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// Active managers and employees of the business who reach the queue's
/// branch and are not assigned yet. The server re-checks eligibility.
class _Candidates extends ConsumerWidget {
  const _Candidates({
    required this.queue,
    required this.assigned,
    required this.busyId,
    required this.onAssign,
  });

  final QueueConfig queue;
  final Set<String> assigned;
  final String? busyId;
  final ValueChanged<TeamMember> onAssign;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final String businessId = queue.businessId;
    final AsyncValue<List<TeamMember>> members = ref.watch(
      teamMembersProvider(businessId),
    );

    return AsyncValueView<List<TeamMember>>(
      value: members,
      onRetry: () => ref.invalidate(teamMembersProvider(businessId)),
      data: (items) {
        final List<TeamMember> candidates = items
            .where(
              (member) =>
                  member.role != BusinessRole.owner &&
                  !assigned.contains(member.id) &&
                  (member.allBranches ||
                      member.branches.any(
                        (branch) => branch.id == queue.branch.id,
                      )),
            )
            .toList();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            DorySectionHeader(title: l10n.queueStaffAvailable),
            DoryCard(
              padding: candidates.isEmpty
                  ? const EdgeInsets.all(AppSpacing.lg)
                  : EdgeInsets.zero,
              child: candidates.isEmpty
                  ? Text(l10n.queueStaffNoneAvailable)
                  : Column(
                      children: <Widget>[
                        for (final TeamMember member in candidates)
                          ListTile(
                            leading: const Icon(Icons.person_outline_rounded),
                            title: Text(member.name),
                            subtitle: Text(roleLabel(l10n, member.role)),
                            trailing: FilledButton.tonal(
                              onPressed: busyId != null
                                  ? null
                                  : () => onAssign(member),
                              child: Text(l10n.queueStaffAssign),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        );
      },
    );
  }
}
