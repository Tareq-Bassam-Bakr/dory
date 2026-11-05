import 'package:material_ui/material_ui.dart';

/// Maps a category's API icon key to an icon. Unknown keys (categories
/// added on the server later) get a generic storefront icon.
IconData categoryIcon(String key) => switch (key) {
  'restaurant' => Icons.restaurant_outlined,
  'healthcare' => Icons.local_hospital_outlined,
  'beauty' => Icons.content_cut_rounded,
  'banking' => Icons.account_balance_outlined,
  'government' => Icons.apartment_outlined,
  'telecom' => Icons.cell_tower_rounded,
  'automotive' => Icons.directions_car_outlined,
  'education' => Icons.school_outlined,
  'retail' => Icons.shopping_bag_outlined,
  'professional' => Icons.work_outline_rounded,
  _ => Icons.storefront_outlined,
};
