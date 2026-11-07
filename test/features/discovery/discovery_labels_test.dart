import 'package:dory_app/app/localization/generated/app_localizations.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_labels.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  final AppLocalizations arabic = lookupAppLocalizations(const Locale('ar'));
  final AppLocalizations english = lookupAppLocalizations(const Locale('en'));

  group('place line', () {
    // Seen on a phone: without isolation, a Latin city in an Arabic line
    // pulled the number away from its unit.
    test('keeps the city and the distance in their own direction', () {
      final String distance = distanceLabel(arabic, 1.1);

      expect(
        placeLine(arabic, city: 'Damascus', distanceKm: 1.1),
        '\u2068Damascus\u2069 · \u2068$distance\u2069',
      );
    });

    test('a single part needs no isolation', () {
      expect(placeLine(english, city: 'Damascus'), 'Damascus');
      expect(placeLine(english, distanceKm: 0.35), '350 m');
    });

    test('nothing known gives an empty line', () {
      expect(placeLine(english), isEmpty);
      expect(placeLine(english, city: ''), isEmpty);
    });
  });
}
