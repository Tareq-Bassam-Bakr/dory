import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Visual tone of a [StatusChip].
enum StatusTone { positive, neutral, warning, negative }

/// Small rounded label for a status (active, inactive, suspended) or role.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.label, required this.tone});

  final String label;
  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final (Color background, Color foreground) = switch (tone) {
      StatusTone.positive => (AppColors.successContainer, AppColors.success),
      StatusTone.neutral => (AppColors.surfaceStrong, AppColors.textSecondary),
      StatusTone.warning => (AppColors.warningContainer, AppColors.warning),
      StatusTone.negative => (
        AppColors.errorContainer,
        AppColors.onErrorContainer,
      ),
    };
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: AppRadii.fullAll,
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xxs,
        ),
        child: Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.labelSmall?.copyWith(color: foreground),
        ),
      ),
    );
  }
}
