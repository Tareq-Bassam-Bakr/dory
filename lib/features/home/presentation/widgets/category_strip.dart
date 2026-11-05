import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/shared/components/business_category_presentation.dart';
import 'package:dory_app/shared/enums/business_category.dart';
import 'package:material_ui/material_ui.dart';

/// Horizontally scrolling list of business categories.
class CategoryStrip extends StatelessWidget {
  const CategoryStrip({super.key, required this.onSelected});

  final ValueChanged<BusinessCategory> onSelected;

  static const double _tileWidth = 88;
  static const double _height = 112;

  @override
  Widget build(BuildContext context) {
    const List<BusinessCategory> categories = BusinessCategory.values;
    return SizedBox(
      height: _height,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: categories.length,
        separatorBuilder: (context, index) =>
            const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final BusinessCategory category = categories[index];
          return SizedBox(
            width: _tileWidth,
            child: _CategoryTile(
              category: category,
              onTap: () => onSelected(category),
            ),
          );
        },
      ),
    );
  }
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({required this.category, required this.onTap});

  final BusinessCategory category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final String label = category.label(context.l10n);
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadii.mdAll,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
          child: Column(
            children: <Widget>[
              DecoratedBox(
                decoration: const BoxDecoration(
                  color: AppColors.surface,
                  shape: BoxShape.circle,
                  border: Border.fromBorderSide(
                    BorderSide(color: AppColors.border),
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md + AppSpacing.xxs),
                  child: Icon(category.icon, color: AppColors.primary),
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                label,
                style: Theme.of(context).textTheme.labelMedium,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
