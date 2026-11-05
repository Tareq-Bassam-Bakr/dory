import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/home/presentation/widgets/category_strip.dart';
import 'package:dory_app/features/home/presentation/widgets/home_search_entry.dart';
import 'package:dory_app/shared/components/coming_soon.dart';
import 'package:material_ui/material_ui.dart';

/// Customer home: search entry, categories, and nearby businesses.
///
/// Phase 01 shows no business data. Nearby businesses arrive with the
/// Discovery and Maps phases.
class CustomerHomePage extends StatelessWidget {
  const CustomerHomePage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;

    return DoryPageScaffold(
      titleWidget: const DoryWordmark(fontSize: 22),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            l10n.homeTitle,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: AppSpacing.lg),
          HomeSearchEntry(
            hint: l10n.homeSearchHint,
            onTap: () => showComingSoonMessage(context),
          ),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.homeCategoriesTitle),
          CategoryStrip(onSelected: (_) => showComingSoonMessage(context)),
          const SizedBox(height: AppSpacing.xl),
          DorySectionHeader(title: l10n.homeNearbyTitle),
          DoryCard(
            child: DoryEmptyState(
              icon: Icons.location_on_outlined,
              title: l10n.homeNearbyEmptyTitle,
              message: l10n.homeNearbyEmptyMessage,
            ),
          ),
        ],
      ),
    );
  }
}
