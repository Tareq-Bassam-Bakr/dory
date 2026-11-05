import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// Queues managed by the business. Empty until the queue engine exists.
class BusinessQueuesPage extends StatelessWidget {
  const BusinessQueuesPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return DoryPageScaffold(
      title: l10n.queuesTitle,
      scrollable: false,
      body: DoryEmptyState(
        icon: Icons.format_list_numbered_rounded,
        title: l10n.queuesEmptyTitle,
        message: l10n.queuesEmptyMessage,
      ),
    );
  }
}
