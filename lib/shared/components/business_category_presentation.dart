import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/shared/enums/business_category.dart';
import 'package:material_ui/material_ui.dart';

/// Icon and localized label of a [BusinessCategory].
extension BusinessCategoryPresentation on BusinessCategory {
  IconData get icon => switch (this) {
    BusinessCategory.restaurants => Icons.restaurant_outlined,
    BusinessCategory.clinics => Icons.local_hospital_outlined,
    BusinessCategory.salons => Icons.content_cut_rounded,
    BusinessCategory.banks => Icons.account_balance_outlined,
    BusinessCategory.government => Icons.apartment_outlined,
    BusinessCategory.repair => Icons.build_outlined,
    BusinessCategory.other => Icons.storefront_outlined,
  };

  String label(AppLocalizations l10n) => switch (this) {
    BusinessCategory.restaurants => l10n.categoryRestaurants,
    BusinessCategory.clinics => l10n.categoryClinics,
    BusinessCategory.salons => l10n.categorySalons,
    BusinessCategory.banks => l10n.categoryBanks,
    BusinessCategory.government => l10n.categoryGovernment,
    BusinessCategory.repair => l10n.categoryRepair,
    BusinessCategory.other => l10n.categoryOther,
  };
}
