import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/dory_button.dart';
import 'package:material_ui/material_ui.dart';

/// Optional call to action of a status view.
class DoryStatusAction {
  const DoryStatusAction({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;
}

/// Icon, title, message and optional action, centered.
///
/// Shared layout behind [DoryEmptyState] and [DoryErrorState]; use those in
/// features so intent stays explicit.
class DoryStatusView extends StatelessWidget {
  const DoryStatusView({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
    this.iconColor,
    this.iconBackgroundColor,
  });

  final IconData icon;
  final String title;
  final String? message;
  final DoryStatusAction? action;
  final Color? iconColor;
  final Color? iconBackgroundColor;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String? message = this.message;
    final DoryStatusAction? action = this.action;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: AppLayout.maxFormWidth),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              DecoratedBox(
                decoration: BoxDecoration(
                  color:
                      iconBackgroundColor ?? theme.colorScheme.primaryContainer,
                  shape: BoxShape.circle,
                ),
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Icon(
                    icon,
                    size: 32,
                    color: iconColor ?? theme.colorScheme.primary,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                title,
                style: theme.textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              if (message != null) ...<Widget>[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  message,
                  style: theme.textTheme.bodyMedium,
                  textAlign: TextAlign.center,
                ),
              ],
              if (action != null) ...<Widget>[
                const SizedBox(height: AppSpacing.xl),
                DoryButton(
                  label: action.label,
                  onPressed: action.onPressed,
                  variant: DoryButtonVariant.secondary,
                  expand: false,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
