import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/authentication/presentation/validation/auth_validators.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  late AppLocalizations l10n;

  setUpAll(() async {
    l10n = await AppLocalizations.delegate.load(const Locale('en'));
  });

  group('registration validation', () {
    test('email must be present and well formed', () {
      expect(AuthValidators.email(l10n, ''), l10n.authEmailRequired);
      expect(AuthValidators.email(l10n, 'lina@'), l10n.authEmailInvalid);
      expect(
        AuthValidators.email(l10n, 'lina dory@test.com'),
        l10n.authEmailInvalid,
      );
      expect(AuthValidators.email(l10n, ' lina@dory.test '), isNull);
    });

    test('name must be 2 to 120 characters', () {
      expect(AuthValidators.name(l10n, 'L'), l10n.authNameInvalid);
      expect(AuthValidators.name(l10n, 'x' * 121), l10n.authNameInvalid);
      expect(AuthValidators.name(l10n, 'لينا'), isNull);
    });

    test('phone is optional but must be E.164 when given', () {
      expect(AuthValidators.optionalPhone(l10n, ''), isNull);
      expect(AuthValidators.optionalPhone(l10n, '+963 912-345-678'), isNull);
      expect(
        AuthValidators.optionalPhone(l10n, '0912345678'),
        l10n.authPhoneInvalid,
      );
      expect(
        AuthValidators.optionalPhone(l10n, '+0123456789'),
        l10n.authPhoneInvalid,
      );
      expect(
        AuthValidators.normalizePhone('+963 (91) 234-5678'),
        '+963912345678',
      );
    });

    test('new passwords follow the server policy', () {
      expect(AuthValidators.newPassword(l10n, ''), l10n.authPasswordRequired);
      for (final String weak in <String>[
        'Short1A',
        'alllowercase1',
        'ALLUPPERCASE1',
        'NoDigitsHere',
      ]) {
        expect(
          AuthValidators.newPassword(l10n, weak),
          l10n.authPasswordTooWeak,
          reason: weak,
        );
      }
      expect(AuthValidators.newPassword(l10n, 'Correct1Horse'), isNull);
    });

    test('the confirmation must match', () {
      expect(
        AuthValidators.confirmation(l10n, 'Correct1Horse', 'Correct1Horse'),
        isNull,
      );
      expect(
        AuthValidators.confirmation(l10n, 'correct1horse', 'Correct1Horse'),
        l10n.authPasswordMismatch,
      );
    });

    test('login only checks that a password was typed', () {
      expect(AuthValidators.loginPassword(l10n, ''), l10n.authPasswordRequired);
      expect(AuthValidators.loginPassword(l10n, 'legacy'), isNull);
    });
  });
}
