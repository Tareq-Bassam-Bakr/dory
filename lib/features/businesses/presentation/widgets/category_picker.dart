import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/shared/components/category_icon.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Chooses a business category from the API list. A form field, so the
/// surrounding form can require a choice.
class CategoryPicker extends ConsumerWidget {
  const CategoryPicker({
    super.key,
    required this.selected,
    required this.onChanged,
    this.errorText,
    this.enabled = true,
  });

  final String? selected;
  final ValueChanged<String> onChanged;
  final String? errorText;
  final bool enabled;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final String language = Localizations.localeOf(context).languageCode;
    final AsyncValue<List<BusinessCategory>> categories = ref.watch(
      businessCategoriesProvider,
    );

    return FormField<String>(
      initialValue: selected,
      validator: (_) => selected == null ? l10n.businessCategoryRequired : null,
      builder: (field) {
        final String? error = field.errorText ?? errorText;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              l10n.businessCategoryLabel,
              style: Theme.of(context).textTheme.titleSmall,
            ),
            const SizedBox(height: AppSpacing.sm),
            switch (categories) {
              AsyncData(:final List<BusinessCategory> value) => Wrap(
                spacing: AppSpacing.sm,
                runSpacing: AppSpacing.sm,
                children: <Widget>[
                  for (final BusinessCategory category in value)
                    ChoiceChip(
                      avatar: Icon(categoryIcon(category.icon), size: 18),
                      label: Text(category.nameFor(language)),
                      selected: category.slug == selected,
                      onSelected: enabled
                          ? (_) {
                              onChanged(category.slug);
                              field.didChange(category.slug);
                            }
                          : null,
                    ),
                ],
              ),
              AsyncError() => DoryNotice(
                message: l10n.businessCategoriesLoadError,
                tone: DoryNoticeTone.error,
              ),
              _ => const SizedBox(height: 48, child: DoryLoadingIndicator()),
            },
            if (categories is AsyncError)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  onPressed: () => ref.invalidate(businessCategoriesProvider),
                  icon: const Icon(Icons.refresh_rounded),
                  label: Text(l10n.commonRetry),
                ),
              ),
            if (error != null)
              Padding(
                padding: const EdgeInsets.only(top: AppSpacing.sm),
                child: Text(
                  error,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
