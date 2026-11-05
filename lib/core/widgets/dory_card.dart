import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Rounded white surface used to group content.
class DoryCard extends StatelessWidget {
  const DoryCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  /// Makes the whole card tappable with an ink ripple.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final Widget content = Padding(padding: padding, child: child);
    return Card(
      child: onTap == null ? content : InkWell(onTap: onTap, child: content),
    );
  }
}
