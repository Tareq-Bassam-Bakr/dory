import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Horizontally scrolling list of business categories, loaded from the API
/// (categories are never hardcoded in the app).
class CategoryStrip extends ConsumerWidget {
  const CategoryStrip({super.key, required this.onSelected});

  final ValueChanged<BusinessCategory> onSelected;

  static const double _tileWidth = 88;
  static const double _height = 112;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<BusinessCategory>> categories = ref.watch(
      businessCategoriesProvider,
    );

    return SizedBox(
      height: _height,
      child: switch (categories) {
        AsyncData(:final List<BusinessCategory> value) => ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: value.length,
          separatorBuilder: (context, index) =>
              const SizedBox(width: AppSpacing.sm),
          itemBuilder: (context, index) => SizedBox(
            width: _tileWidth,
            child: _CategoryTile(
              category: value[index],
              onTap: () => onSelected(value[index]),
            ),
          ),
        ),
        AsyncError() => Center(
          child: Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              Text(
                l10n.businessCategoriesLoadError,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              TextButton(
                onPressed: () => ref.invalidate(businessCategoriesProvider),
                child: Text(l10n.commonRetry),
              ),
            ],
          ),
        ),
        _ => const DoryLoadingIndicator(),
      },
    );
  }
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({required this.category, required this.onTap});

  final BusinessCategory category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final String label = category.nameFor(
      Localizations.localeOf(context).languageCode,
    );
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
                  child: Icon(
                    categoryIcon(category.icon),
                    color: AppColors.primary,
                  ),
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
