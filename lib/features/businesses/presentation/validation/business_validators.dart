import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/features/authentication/presentation/validation/auth_validators.dart';

/// Client-side checks mirroring the API rules for businesses, branches and
/// services. The server validates again and is the authority.
abstract final class BusinessValidators {
  static final RegExp _country = RegExp(r'^[A-Za-z]{2}$');
  static final RegExp _timezone = RegExp(
    r'^(UTC|[A-Za-z]+(/[A-Za-z0-9_+\-]+)+)$',
  );

  static String? name(AppLocalizations l10n, String? value) {
    final int length = (value ?? '').trim().length;
    return length >= 2 && length <= 150 ? null : l10n.businessNameInvalid;
  }

  static String? optionalEmail(AppLocalizations l10n, String? value) {
    return (value ?? '').trim().isEmpty
        ? null
        : AuthValidators.email(l10n, value);
  }

  static String? optionalPhone(AppLocalizations l10n, String? value) =>
      AuthValidators.optionalPhone(l10n, value);

  static String? optionalWebsite(AppLocalizations l10n, String? value) {
    final String website = (value ?? '').trim();
    if (website.isEmpty) {
      return null;
    }
    final Uri? uri = Uri.tryParse(website);
    final bool valid =
        uri != null &&
        (uri.scheme == 'https' || uri.scheme == 'http') &&
        uri.host.contains('.') &&
        website.length <= 255;
    return valid ? null : l10n.businessWebsiteInvalid;
  }

  static String? optionalTimezone(AppLocalizations l10n, String? value) {
    final String timezone = (value ?? '').trim();
    return timezone.isEmpty || _timezone.hasMatch(timezone)
        ? null
        : l10n.timezoneInvalid;
  }

  static String? optionalCountry(AppLocalizations l10n, String? value) {
    final String country = (value ?? '').trim();
    return country.isEmpty || _country.hasMatch(country)
        ? null
        : l10n.branchCountryInvalid;
  }

  /// Latitude in [-90, 90], longitude in [-180, 180]; both or neither.
  static String? latitude(
    AppLocalizations l10n,
    String? value,
    String longitude,
  ) => _coordinate(l10n, value, longitude, 90, l10n.branchLatitudeInvalid);

  static String? longitude(
    AppLocalizations l10n,
    String? value,
    String latitude,
  ) => _coordinate(l10n, value, latitude, 180, l10n.branchLongitudeInvalid);

  static String? _coordinate(
    AppLocalizations l10n,
    String? value,
    String partner,
    double limit,
    String rangeError,
  ) {
    final String text = (value ?? '').trim();
    if (text.isEmpty) {
      return partner.trim().isEmpty ? null : l10n.branchCoordinatesPair;
    }
    final double? number = double.tryParse(text);
    if (number == null || number < -limit || number > limit) {
      return rangeError;
    }
    return partner.trim().isEmpty ? l10n.branchCoordinatesPair : null;
  }

  static String? serviceName(AppLocalizations l10n, String? value) {
    final int length = (value ?? '').trim().length;
    return length >= 2 && length <= 150 ? null : l10n.serviceNameInvalid;
  }

  static String? duration(AppLocalizations l10n, String? value) {
    final int? minutes = int.tryParse((value ?? '').trim());
    return minutes != null && minutes >= 1 && minutes <= 1440
        ? null
        : l10n.serviceDurationInvalid;
  }
}
