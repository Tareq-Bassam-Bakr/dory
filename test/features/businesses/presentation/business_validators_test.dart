import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/businesses/data/models/business_models.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/validation/business_validators.dart';
import 'package:dory_app/features/team/presentation/providers/team_providers.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  late AppLocalizations l10n;

  setUpAll(() async {
    l10n = await AppLocalizations.delegate.load(const Locale('en'));
  });

  group('business form validation', () {
    test('names are 2 to 150 characters', () {
      expect(BusinessValidators.name(l10n, 'A'), l10n.businessNameInvalid);
      expect(
        BusinessValidators.name(l10n, 'x' * 151),
        l10n.businessNameInvalid,
      );
      expect(BusinessValidators.name(l10n, 'مطعم الشام'), isNull);
    });

    test('contact fields are optional but must be well formed', () {
      expect(BusinessValidators.optionalEmail(l10n, ''), isNull);
      expect(
        BusinessValidators.optionalEmail(l10n, 'clinic@'),
        l10n.authEmailInvalid,
      );
      expect(BusinessValidators.optionalPhone(l10n, ''), isNull);
      expect(
        BusinessValidators.optionalPhone(l10n, '011 1234'),
        l10n.authPhoneInvalid,
      );
      expect(BusinessValidators.optionalWebsite(l10n, ''), isNull);
      expect(
        BusinessValidators.optionalWebsite(l10n, 'https://shifa.example'),
        isNull,
      );
      for (final String bad in <String>[
        'shifa.example',
        'javascript:alert(1)',
        'ftp://x.example',
        'https://localhost',
      ]) {
        expect(
          BusinessValidators.optionalWebsite(l10n, bad),
          l10n.businessWebsiteInvalid,
          reason: bad,
        );
      }
    });

    test('time zones and country codes', () {
      expect(
        BusinessValidators.optionalTimezone(l10n, 'Asia/Damascus'),
        isNull,
      );
      expect(
        BusinessValidators.optionalTimezone(
          l10n,
          'America/Argentina/Buenos_Aires',
        ),
        isNull,
      );
      expect(
        BusinessValidators.optionalTimezone(l10n, 'Damascus'),
        l10n.timezoneInvalid,
      );
      expect(BusinessValidators.optionalCountry(l10n, 'sy'), isNull);
      expect(
        BusinessValidators.optionalCountry(l10n, 'Syria'),
        l10n.branchCountryInvalid,
      );
    });

    test('coordinates are WGS 84 ranges, both or neither', () {
      expect(BusinessValidators.latitude(l10n, '', ''), isNull);
      expect(BusinessValidators.latitude(l10n, '33.51', '36.27'), isNull);
      expect(BusinessValidators.latitude(l10n, '-90', '180'), isNull);
      expect(
        BusinessValidators.latitude(l10n, '90.1', '36'),
        l10n.branchLatitudeInvalid,
      );
      expect(
        BusinessValidators.longitude(l10n, '-180.5', '33'),
        l10n.branchLongitudeInvalid,
      );
      expect(
        BusinessValidators.latitude(l10n, '33.5', ''),
        l10n.branchCoordinatesPair,
      );
      expect(
        BusinessValidators.latitude(l10n, '', '36.2'),
        l10n.branchCoordinatesPair,
      );
      expect(
        BusinessValidators.latitude(l10n, 'north', '36'),
        l10n.branchLatitudeInvalid,
      );
    });

    test('service durations are whole minutes up to a day', () {
      expect(BusinessValidators.duration(l10n, '15'), isNull);
      expect(BusinessValidators.duration(l10n, '1440'), isNull);
      for (final String bad in <String>['0', '1441', '12.5', '', 'ten']) {
        expect(
          BusinessValidators.duration(l10n, bad),
          l10n.serviceDurationInvalid,
          reason: bad,
        );
      }
    });
  });

  group('request bodies', () {
    test('blank optional fields are left out of a new business', () {
      final Map<String, Object?> body = BusinessModels.createBody(
        const BusinessDraft(
          name: ' Shifa ',
          categorySlug: 'healthcare-clinics',
          branch: BranchDraft(
            name: 'Main',
            latitude: '33.5',
            longitude: '36.2',
          ),
        ),
      );

      expect(body.keys, containsAll(<String>['name', 'category', 'branch']));
      expect(body.containsKey('email'), isFalse);
      expect(body['name'], 'Shifa');
      final Map<String, Object?> branch =
          body['branch']! as Map<String, Object?>;
      expect(branch['latitude'], 33.5);
      expect(branch.containsKey('services'), isFalse);
    });

    test('branch updates clear blank fields explicitly', () {
      final Map<String, Object?> body = BusinessModels.branchUpdateBody(
        const BranchDraft(name: 'Main', isActive: false),
      );

      expect(body['address'], isNull);
      expect(body.containsKey('address'), isTrue);
      expect(body['latitude'], isNull);
      expect(body['status'], 'inactive');
    });
  });

  group('invitation links', () {
    final String token = 'A' * 32 + 'b' * 32;

    test('tokens are read from app links, web links or the bare code', () {
      expect(invitationTokenFrom('dory://app/invitations/$token'), token);
      expect(
        invitationTokenFrom('https://dory.example/invitations/$token?x=1'),
        token,
      );
      expect(invitationTokenFrom('  $token  '), token);
    });

    test('anything else is rejected', () {
      expect(invitationTokenFrom('https://evil.example/login'), isNull);
      expect(invitationTokenFrom(token.substring(1)), isNull);
      expect(invitationTokenFrom('${token}x'), isNull);
    });
  });
}
