import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// The customer's queue tickets. Empty until queues exist (later phase).
class TicketsPage extends StatelessWidget {
  const TicketsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return DoryPageScaffold(
      title: l10n.ticketsTitle,
      scrollable: false,
      body: DoryEmptyState(
        icon: Icons.confirmation_number_outlined,
        title: l10n.ticketsEmptyTitle,
        message: l10n.ticketsEmptyMessage,
      ),
    );
  }
}
