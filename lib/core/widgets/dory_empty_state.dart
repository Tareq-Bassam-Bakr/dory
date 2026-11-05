import 'package:dory_app/core/widgets/dory_status_view.dart';
import 'package:material_ui/material_ui.dart';

/// Shown when a section or screen has nothing to display yet.
class DoryEmptyState extends StatelessWidget {
  const DoryEmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final DoryStatusAction? action;

  @override
  Widget build(BuildContext context) {
    return DoryStatusView(
      icon: icon,
      title: title,
      message: message,
      action: action,
    );
  }
}
