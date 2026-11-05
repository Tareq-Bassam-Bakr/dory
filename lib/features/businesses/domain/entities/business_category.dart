import 'package:flutter/foundation.dart';

/// A kind of business, loaded from the API (never hardcoded in the app).
///
/// Categories only describe and group businesses: no app behaviour may
/// branch on them.
@immutable
class BusinessCategory {
  const BusinessCategory({
    required this.slug,
    required this.names,
    required this.icon,
    this.children = const <BusinessCategory>[],
  });

  /// Stable public identifier, e.g. `healthcare-clinics`.
  final String slug;

  /// Names by language code (`en`, `ar`).
  final Map<String, String> names;

  /// Icon key; the app maps it to an icon with a generic fallback.
  final String icon;

  /// Subcategories (none at launch).
  final List<BusinessCategory> children;

  /// The name in [languageCode], falling back to English, then the slug.
  String nameFor(String languageCode) =>
      names[languageCode] ?? names['en'] ?? slug;

  @override
  bool operator ==(Object other) =>
      other is BusinessCategory && other.slug == slug;

  @override
  int get hashCode => slug.hashCode;
}
