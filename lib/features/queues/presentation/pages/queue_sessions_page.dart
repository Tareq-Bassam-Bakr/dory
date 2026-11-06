import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Queue Session History, newest first.
class QueueSessionsPage extends ConsumerWidget {
  const QueueSessionsPage({super.key, required this.queueId});

  final String queueId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<QueueSession>> sessions = ref.watch(
      queueSessionsProvider(queueId),
    );

    return DoryPageScaffold(
      title: l10n.queueHistoryTitle,
      body: AsyncValueView<List<QueueSession>>(
        value: sessions,
        onRetry: () => ref.invalidate(queueSessionsProvider(queueId)),
        data: (items) => items.isEmpty
            ? DoryCard(
                child: DoryEmptyState(
                  icon: Icons.history_rounded,
                  title: l10n.queueHistoryEmpty,
                  message: '',
                ),
              )
            : DoryCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: <Widget>[
                    for (final QueueSession session in items)
                      ListTile(
                        leading: const Icon(Icons.event_note_outlined),
                        title: Text(
                          l10n.queueHistoryItem(
                            formatBusinessDate(context, session.businessDate),
                            session.sessionNumber,
                          ),
                        ),
                        subtitle: Text(
                          <String>[
                            l10n.queueHistoryTickets(session.ticketsCount ?? 0),
                            '${formatClock(context, session.openedAt)}'
                                '${session.closedAt == null ? '' : ' – ${formatClock(context, session.closedAt!)}'}',
                            ?closeReasonLabel(l10n, session.closeReason),
                          ].join(' · '),
                        ),
                        trailing: sessionStatusChip(l10n, session.status),
                        onTap: () =>
                            context.go(AppRoutes.queueSession(session.id)),
                      ),
                  ],
                ),
              ),
      ),
    );
  }
}
