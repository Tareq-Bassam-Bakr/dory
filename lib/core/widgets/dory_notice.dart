import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Tone of a [DoryNotice].
enum DoryNoticeTone { info, warning }

/// Inline message banner, e.g. to explain why an action is unavailable.
class DoryNotice extends StatelessWidget {
  const DoryNotice({
    super.key,
    required this.message,
    this.tone = DoryNoticeTone.info,
  });

  final String message;
  final DoryNoticeTone tone;

  @override
  Widget build(BuildContext context) {
    final (Color background, Color foreground, IconData icon) = switch (tone) {
      DoryNoticeTone.info => (
        AppColors.primaryContainer,
        AppColors.onPrimaryContainer,
        Icons.info_outline_rounded,
      ),
      DoryNoticeTone.warning => (
        AppColors.warningContainer,
        AppColors.warning,
        Icons.warning_amber_rounded,
      ),
    };

    return Semantics(
      container: true,
      liveRegion: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: background,
          borderRadius: AppRadii.mdAll,
        ),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(icon, color: foreground, size: 20),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  message,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(color: foreground),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
