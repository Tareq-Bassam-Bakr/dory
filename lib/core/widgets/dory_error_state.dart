import 'package:dory_app/core/widgets/dory_status_view.dart';
import 'package:material_ui/material_ui.dart';

/// Shown when loading or an action failed. Offers a retry when possible.
class DoryErrorState extends StatelessWidget {
  const DoryErrorState({
    super.key,
    required this.title,
    this.message,
    this.retry,
    this.icon = Icons.error_outline_rounded,
  });

  final String title;
  final String? message;

  /// Retry action. Omit when retrying cannot help.
  final DoryStatusAction? retry;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return DoryStatusView(
      icon: icon,
      title: title,
      message: message,
      action: retry,
      iconColor: colors.error,
      iconBackgroundColor: colors.errorContainer,
    );
  }
}
