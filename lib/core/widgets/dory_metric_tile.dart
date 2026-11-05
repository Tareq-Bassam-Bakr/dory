import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/dory_card.dart';
import 'package:material_ui/material_ui.dart';

/// A labelled figure, e.g. "Waiting: 12".
///
/// When [value] is `null` a dash is shown and screen readers announce
/// [noValueLabel] instead, so missing data is never presented as zero.
class DoryMetricTile extends StatelessWidget {
  const DoryMetricTile({
    super.key,
    required this.label,
    required this.icon,
    required this.noValueLabel,
    this.value,
  });

  final String label;
  final IconData icon;
  final String? value;
  final String noValueLabel;

  static const String _noValueGlyph = '—';

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String? value = this.value;

    return MergeSemantics(
      child: DoryCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(icon, color: theme.colorScheme.primary, size: 22),
            const SizedBox(height: AppSpacing.md),
            Semantics(
              label: value ?? noValueLabel,
              excludeSemantics: true,
              child: Text(
                value ?? _noValueGlyph,
                style: theme.textTheme.headlineMedium,
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              label,
              style: theme.textTheme.bodySmall,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
