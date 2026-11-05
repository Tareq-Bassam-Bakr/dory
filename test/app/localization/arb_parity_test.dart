import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Guards translation completeness: every message exists in every language,
/// is non-empty, and is documented for translators.
void main() {
  const String arbDir = 'lib/app/localization/arb';

  Map<String, dynamic> readArb(String fileName) {
    final String raw = File('$arbDir/$fileName').readAsStringSync();
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Set<String> messageKeys(Map<String, dynamic> arb) =>
      arb.keys.where((key) => !key.startsWith('@')).toSet();

  final Map<String, dynamic> english = readArb('app_en.arb');
  final Map<String, dynamic> arabic = readArb('app_ar.arb');

  test('Arabic and English define the same messages', () {
    final Set<String> en = messageKeys(english);
    final Set<String> ar = messageKeys(arabic);

    expect(en.difference(ar), isEmpty, reason: 'Missing in app_ar.arb');
    expect(ar.difference(en), isEmpty, reason: 'Missing in app_en.arb');
  });

  test('no message is empty', () {
    for (final Map<String, dynamic> arb in <Map<String, dynamic>>[
      english,
      arabic,
    ]) {
      for (final String key in messageKeys(arb)) {
        expect((arb[key] as String).trim(), isNotEmpty, reason: key);
      }
    }
  });

  test('every English message has a description for translators', () {
    for (final String key in messageKeys(english)) {
      final Object? metadata = english['@$key'];
      expect(metadata, isA<Map<String, dynamic>>(), reason: key);
      final Object? description =
          (metadata! as Map<String, dynamic>)['description'];
      expect(description, isA<String>(), reason: key);
    }
  });

  test('Arabic messages are actually written in Arabic', () {
    // Names that are intentionally identical in every locale.
    const Set<String> sharedAcrossLocales = <String>{
      'languageEnglish',
      'languageArabic',
    };
    final RegExp arabicScript = RegExp(r'[؀-ۿ]');
    for (final String key in messageKeys(arabic)) {
      if (sharedAcrossLocales.contains(key)) {
        continue;
      }
      expect(
        arabicScript.hasMatch(arabic[key] as String),
        isTrue,
        reason: '$key looks untranslated',
      );
    }
  });
}
